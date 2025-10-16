<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
                            {--keep-csv : Keep the CSV file after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed tenants and tenant audit tables using CSV import (very fast for large datasets)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $keepCsv = $this->option('keep-csv');

        // Calculate monthly distribution
        try {
            $distribution = $this->calculateMonthlyDistribution($count, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        // Display distribution info
        if ($startDate && $endDate) {
            $this->info("Distributing {$count} tenants across " . count($distribution) . " month(s):");
            foreach ($distribution as $period) {
                $this->info("  - {$period['start']->format('Y-m-d')} to {$period['end']->format('Y-m-d')}: {$period['count']} records");
            }
        }

        $tenantsCsvPath = $this->generateCsvPath('tenants');
        $auditCsvPath = $this->generateCsvPath('tenant_audit');

        $this->info("Generating CSV files with {$count} tenants and " . ($count * 7) . " audit records...");
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        // Generate CSV files
        $tenantsFile = fopen($tenantsCsvPath, 'w');
        $auditFile = fopen($auditCsvPath, 'w');

        $recordIndex = 0;

        // Generate tenants and audit records simultaneously to save memory
        foreach ($distribution as $period) {
            for ($i = 0; $i < $period['count']; $i++) {
                $tenantId = Str::uuid();
                $timestamp = $this->generateRandomTimestamp($period['start'], $period['end']);
                $tenantName = "Tenant " . $this->generateCompanyName() . " " . $recordIndex;

                // Write tenant record
                fputcsv($tenantsFile, [
                    $tenantId,
                    $tenantName,
                    $timestamp->format('Y-m-d H:i:s'),
                    $timestamp->format('Y-m-d H:i:s'),
                ]);

                // Write audit records immediately
                $transactionHash = hash('sha256', $tenantId . time() . $recordIndex);

                // 1 CREATE audit
                fputcsv($auditFile, [
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
                        ]
                    ]),
                    $transactionHash . '_create',
                    $tenantId,
                    "System User",
                    $timestamp->format('Y-m-d H:i:s'),
                ]);

                // 6 UPDATE audits (spread over time after creation)
                for ($j = 1; $j <= 6; $j++) {
                    $updateTime = $timestamp->copy()->addSeconds($j * 3600);
                    $oldName = $j === 1 ? $tenantName : $tenantName . ' Updated ' . ($j - 1);
                    $newName = $tenantName . ' Updated ' . $j;

                    fputcsv($auditFile, [
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
                            ]
                        ]),
                        $transactionHash . '_update_' . $j,
                        $tenantId,
                        "System User",
                        $updateTime->format('Y-m-d H:i:s'),
                    ]);
                }

                $recordIndex++;

                if ($recordIndex % 10000 === 0) {
                    $progressBar->advance(10000);
                }
            }
        }

        // Advance progress bar for remaining records
        if ($recordIndex % 10000 !== 0) {
            $progressBar->advance($recordIndex % 10000);
        }

        fclose($tenantsFile);
        fclose($auditFile);
        $progressBar->finish();
        $this->newLine();

        $this->displayFileSize($tenantsCsvPath, 'Tenants');
        $this->displayFileSize($auditCsvPath, 'Audit');

        // Import using COPY command
        $this->info("Importing tenants using PostgreSQL COPY...");

        if (!$this->importCsvWithCopy('tenants', 'id, name, created_at, updated_at', $tenantsCsvPath)) {
            $this->cleanupCsvFiles([$tenantsCsvPath, $auditCsvPath], $keepCsv);
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Successfully imported {$count} tenants!");
        $this->displayTableCount('tenants');

        // Import audit records
        $this->info("Importing tenant audit records using PostgreSQL COPY...");

        if (!$this->importCsvWithCopy('tenant_audits', 'tenant_id, object_id, type, diffs, transaction_hash, blame_id, blame_user, created_at', $auditCsvPath)) {
            $this->cleanupCsvFiles([$tenantsCsvPath, $auditCsvPath], $keepCsv);
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Successfully imported " . ($count * 7) . " audit records!");
        $this->displayTableCount('tenant_audits', null, 'tenant audit records');

        // Clean up CSV files
        $this->cleanupCsvFiles([$tenantsCsvPath, $auditCsvPath], $keepCsv, ['Tenants', 'Audit']);

        return Command::SUCCESS;
    }
}
