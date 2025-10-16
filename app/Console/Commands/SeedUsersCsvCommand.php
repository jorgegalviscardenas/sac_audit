<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedUsersCsvCommand extends Command
{
    use CsvSeedCommandTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:users-csv
                            {count=100 : The number of users to create}
                            {--tenant= : The tenant UUID (required)}
                            {--start-date= : Start date for records (Y-m-d format)}
                            {--end-date= : End date for records (Y-m-d format)}
                            {--keep-csv : Keep the CSV file after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed users and user audit tables using CSV import (very fast for large datasets)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');
        $tenantId = $this->option('tenant');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $keepCsv = $this->option('keep-csv');

        if (!$tenantId) {
            $this->error('Please provide a tenant UUID using --tenant option');
            return Command::FAILURE;
        }

        // Validate tenant exists
        if (!$this->validateTenant($tenantId)) {
            $this->error("Tenant with ID {$tenantId} does not exist");
            return Command::FAILURE;
        }

        // Calculate monthly distribution
        try {
            $distribution = $this->calculateMonthlyDistribution($count, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        // Display distribution info
        if ($startDate && $endDate) {
            $this->info("Distributing {$count} users across " . count($distribution) . " month(s):");
            foreach ($distribution as $period) {
                $this->info("  - {$period['start']->format('Y-m-d')} to {$period['end']->format('Y-m-d')}: {$period['count']} records");
            }
        }

        $usersCsvPath = $this->generateCsvPath('users');
        $auditCsvPath = $this->generateCsvPath('user_audit');

        $this->info("Generating CSV files with {$count} users and " . ($count * 7) . " audit records...");
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        // Generate CSV files
        $usersFile = fopen($usersCsvPath, 'w');
        $auditFile = fopen($auditCsvPath, 'w');

        $recordIndex = 0;

        // Generate users and audit records simultaneously to save memory
        foreach ($distribution as $period) {
            for ($i = 0; $i < $period['count']; $i++) {
                $userId = Str::uuid();
                $timestamp = $this->generateRandomTimestamp($period['start'], $period['end']);
                $userName = "User " . $this->generateRandomName();
                $userEmail = "user{$recordIndex}_" . uniqid() . "@example.com";
                $isEnabled = (mt_rand(1, 100) <= 90);

                // Write user record
                fputcsv($usersFile, [
                    $userId,
                    $tenantId,
                    $userEmail,
                    $userName,
                    $isEnabled ? 't' : 'f', // PostgreSQL boolean format
                    $timestamp->format('Y-m-d H:i:s'),
                    $timestamp->format('Y-m-d H:i:s'),
                ]);

                // Write audit records immediately
                $transactionHash = hash('sha256', $userId . time() . $recordIndex);

                // 1 CREATE audit
                fputcsv($auditFile, [
                    $tenantId,
                    $userId,
                    1, // type: CREATE
                    json_encode([
                        'old' => null,
                        'new' => [
                            'id' => $userId,
                            'tenant_id' => $tenantId,
                            'email' => $userEmail,
                            'full_name' => $userName,
                            'enabled' => $isEnabled,
                            'created_at' => $timestamp->format('Y-m-d H:i:s'),
                            'updated_at' => $timestamp->format('Y-m-d H:i:s'),
                        ]
                    ]),
                    $transactionHash . '_create',
                    $userId,
                    "System User",
                    $timestamp->format('Y-m-d H:i:s'),
                ]);

                // 6 UPDATE audits (spread over time after creation)
                for ($j = 1; $j <= 6; $j++) {
                    $updateTime = $timestamp->copy()->addSeconds($j * 3600);
                    $oldEnabled = $j === 1 ? $isEnabled : !$isEnabled;
                    $newEnabled = !$oldEnabled;

                    fputcsv($auditFile, [
                        $tenantId,
                        $userId,
                        2, // type: UPDATE
                        json_encode([
                            'old' => [
                                'id' => $userId,
                                'tenant_id' => $tenantId,
                                'email' => $userEmail,
                                'full_name' => $userName,
                                'enabled' => $oldEnabled,
                                'updated_at' => $timestamp->copy()->addSeconds(($j - 1) * 3600)->format('Y-m-d H:i:s'),
                            ],
                            'new' => [
                                'id' => $userId,
                                'tenant_id' => $tenantId,
                                'email' => $userEmail,
                                'full_name' => $userName,
                                'enabled' => $newEnabled,
                                'updated_at' => $updateTime->format('Y-m-d H:i:s'),
                            ]
                        ]),
                        $transactionHash . '_update_' . $j,
                        $userId,
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

        fclose($usersFile);
        fclose($auditFile);
        $progressBar->finish();
        $this->newLine();

        $this->displayFileSize($usersCsvPath, 'Users');
        $this->displayFileSize($auditCsvPath, 'Audit');

        // Import using COPY command
        $this->info("Importing users using PostgreSQL COPY...");

        if (!$this->importCsvWithCopy('users', 'id, tenant_id, email, full_name, enabled, created_at, updated_at', $usersCsvPath)) {
            $this->cleanupCsvFiles([$usersCsvPath, $auditCsvPath], $keepCsv);
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Successfully imported {$count} users!");
        $this->displayTableCount('users', $tenantId);

        // Import audit records
        $this->info("Importing user audit records using PostgreSQL COPY...");

        if (!$this->importCsvWithCopy('user_audits', 'tenant_id, object_id, type, diffs, transaction_hash, blame_id, blame_user, created_at', $auditCsvPath)) {
            $this->cleanupCsvFiles([$usersCsvPath, $auditCsvPath], $keepCsv);
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Successfully imported " . ($count * 7) . " audit records!");
        $this->displayTableCount('user_audits', $tenantId, 'user audit records');

        // Clean up CSV files
        $this->cleanupCsvFiles([$usersCsvPath, $auditCsvPath], $keepCsv, ['Users', 'Audit']);

        return Command::SUCCESS;
    }
}
