<?php

namespace App\Console\Commands;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedTenantsCsvCommand extends Command
{
    use CsvSeedCommandTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:tenants-csv
                            {count=100 : The number of tenants to create}
                            {--start-date= : Start date for records (Y-m-d format)}
                            {--end-date= : End date for records (Y-m-d format)}
                            {--keep-csv : Keep CSV files after import}
                            {--audit-connection=operational : Database connection for audit tables (operational or audit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed tenants and tenant audit tables using bulk inserts (optimized for large datasets)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $keepCsv = $this->option('keep-csv');
        $auditConnection = $this->option('audit-connection');

        // Calculate monthly distribution
        try {
            $distribution = $this->calculateMonthlyDistribution($count, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        // Display distribution info
        if ($startDate && $endDate) {
            $this->info("Distributing {$count} tenants across ".count($distribution).' month(s):');
            foreach ($distribution as $period) {
                $this->info("  - {$period['start']->format('Y-m-d')} to {$period['end']->format('Y-m-d')}: {$period['count']} records");
            }
        }

        $this->info("Generating and inserting {$count} tenants and ".($count * 7).' audit records...');
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $recordIndex = 0;

        // Generate and insert tenants and audit records per period
        foreach ($distribution as $period) {
            $tenants = [];
            $audits = [];

            for ($i = 0; $i < $period['count']; $i++) {
                $tenantId = Str::uuid();
                $timestamp = $this->generateRandomTimestamp($period['start'], $period['end']);
                $tenantName = 'Tenant '.$this->generateCompanyName().' '.$recordIndex;

                // Collect tenant record
                $tenants[] = [
                    $tenantId,
                    $tenantName,
                    $timestamp->format('Y-m-d H:i:s'),
                    $timestamp->format('Y-m-d H:i:s'),
                ];

                $transactionHash = hash('sha256', $tenantId.time().$recordIndex);

                // 1 CREATE audit
                $audits[] = [
                    $tenantId,
                    $tenantId,
                    1, // type: CREATE
                    json_encode([
                        'old' => null,
                        'new' => [
                            'id' => $tenantId,
                            'name' => $tenantName,
                            'created_at' => $timestamp->format('Y-m-d H:i:s'),
                            'updated_at' => $timestamp->format('Y-m-d H:i:s'),
                        ],
                    ]),
                    $transactionHash.'_create',
                    $tenantId,
                    'System User',
                    $timestamp->format('Y-m-d H:i:s'),
                ];

                // 6 UPDATE audits (spread over time after creation)
                for ($j = 1; $j <= 6; $j++) {
                    $updateTime = $timestamp->copy()->addSeconds($j * 3600);
                    $oldName = $j === 1 ? $tenantName : $tenantName.' Updated '.($j - 1);
                    $newName = $tenantName.' Updated '.$j;

                    $audits[] = [
                        $tenantId,
                        $tenantId,
                        2, // type: UPDATE
                        json_encode([
                            'old' => [
                                'id' => $tenantId,
                                'name' => $oldName,
                                'updated_at' => $timestamp->copy()->addSeconds(($j - 1) * 3600)->format('Y-m-d H:i:s'),
                            ],
                            'new' => [
                                'id' => $tenantId,
                                'name' => $newName,
                                'updated_at' => $updateTime->format('Y-m-d H:i:s'),
                            ],
                        ]),
                        $transactionHash.'_update_'.$j,
                        $tenantId,
                        'System User',
                        $updateTime->format('Y-m-d H:i:s'),
                    ];
                }

                $recordIndex++;

                if ($recordIndex % 10000 === 0) {
                    $progressBar->advance(10000);
                }
            }

            // Bulk insert for this period - direct COPY for main table
            if (! $this->bulkInsertWithCopy('tenants', ['id', 'name', 'created_at', 'updated_at'], $tenants, $keepCsv, DB_CONN::OPERATIONAL)) {
                $progressBar->finish();

                return Command::FAILURE;
            }

            // Bulk insert for audit - use partitioned method for audit connection
            if ($auditConnection === DB_CONN::AUDIT) {
                if (! $this->bulkInsertWithCopyPartitioned('tenant_audits', ['tenant_id', 'object_id', 'type', 'diffs', 'transaction_hash', 'blame_id', 'blame_user', 'created_at'], $audits, $keepCsv, $auditConnection)) {
                    $progressBar->finish();

                    return Command::FAILURE;
                }
            } else {
                if (! $this->bulkInsertWithCopy('tenant_audits', ['tenant_id', 'object_id', 'type', 'diffs', 'transaction_hash', 'blame_id', 'blame_user', 'created_at'], $audits, $keepCsv, $auditConnection)) {
                    $progressBar->finish();

                    return Command::FAILURE;
                }
            }

            // Clear arrays to free memory
            unset($tenants, $audits);
        }

        // Advance progress bar for remaining records
        if ($recordIndex % 10000 !== 0) {
            $progressBar->advance($recordIndex % 10000);
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully inserted {$count} tenants and ".($count * 7).' audit records!');
        $this->displayTableCount('tenants');
        $this->displayTableCount('tenant_audits', null, 'tenant audit records');

        return Command::SUCCESS;
    }
}
