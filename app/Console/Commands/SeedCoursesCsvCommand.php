<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedCoursesCsvCommand extends Command
{
    use CsvSeedCommandTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:courses-csv
                            {count=100 : The number of courses to create}
                            {--tenant= : The tenant UUID (required)}
                            {--start-date= : Start date for records (Y-m-d format)}
                            {--end-date= : End date for records (Y-m-d format)}
                            {--keep-csv : Keep the CSV file after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed courses and course audit tables using CSV import (very fast for large datasets)';

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
            $this->info("Distributing {$count} courses across " . count($distribution) . " month(s):");
            foreach ($distribution as $period) {
                $this->info("  - {$period['start']->format('Y-m-d')} to {$period['end']->format('Y-m-d')}: {$period['count']} records");
            }
        }

        $coursesCsvPath = $this->generateCsvPath('courses');
        $auditCsvPath = $this->generateCsvPath('course_audit');

        $this->info("Generating CSV files with {$count} courses and " . ($count * 7) . " audit records...");
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        // Generate CSV files
        $coursesFile = fopen($coursesCsvPath, 'w');
        $auditFile = fopen($auditCsvPath, 'w');

        $recordIndex = 0;

        // Generate courses and audit records simultaneously to save memory
        foreach ($distribution as $period) {
            for ($i = 0; $i < $period['count']; $i++) {
                $courseId = Str::uuid();
                $timestamp = $this->generateRandomTimestamp($period['start'], $period['end']);
                $courseTitle = $this->generateCourseTitle() . " " . $recordIndex;
                $courseDescription = "Description for " . $courseTitle;

                // Write course record
                fputcsv($coursesFile, [
                    $courseId,
                    $tenantId,
                    $courseTitle,
                    $courseDescription,
                    $timestamp->format('Y-m-d H:i:s'),
                    $timestamp->format('Y-m-d H:i:s'),
                ]);

                // Write audit records immediately
                $transactionHash = hash('sha256', $courseId . time() . $recordIndex);

                // 1 CREATE audit
                fputcsv($auditFile, [
                    $tenantId,
                    $courseId,
                    1, // type: CREATE
                    json_encode([
                        'old' => null,
                        'new' => [
                            'id' => $courseId,
                            'tenant_id' => $tenantId,
                            'title' => $courseTitle,
                            'description' => $courseDescription,
                            'created_at' => $timestamp->format('Y-m-d H:i:s'),
                            'updated_at' => $timestamp->format('Y-m-d H:i:s'),
                        ]
                    ]),
                    $transactionHash . '_create',
                    $courseId,
                    "System User",
                    $timestamp->format('Y-m-d H:i:s'),
                ]);

                // 6 UPDATE audits (spread over time after creation)
                for ($j = 1; $j <= 6; $j++) {
                    $updateTime = $timestamp->copy()->addSeconds($j * 3600);
                    $oldDescription = $j === 1 ? $courseDescription : $courseDescription . ' v' . ($j - 1);
                    $newDescription = $courseDescription . ' v' . $j;

                    fputcsv($auditFile, [
                        $tenantId,
                        $courseId,
                        2, // type: UPDATE
                        json_encode([
                            'old' => [
                                'id' => $courseId,
                                'tenant_id' => $tenantId,
                                'title' => $courseTitle,
                                'description' => $oldDescription,
                                'updated_at' => $timestamp->copy()->addSeconds(($j - 1) * 3600)->format('Y-m-d H:i:s'),
                            ],
                            'new' => [
                                'id' => $courseId,
                                'tenant_id' => $tenantId,
                                'title' => $courseTitle,
                                'description' => $newDescription,
                                'updated_at' => $updateTime->format('Y-m-d H:i:s'),
                            ]
                        ]),
                        $transactionHash . '_update_' . $j,
                        $courseId,
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

        fclose($coursesFile);
        fclose($auditFile);
        $progressBar->finish();
        $this->newLine();

        $this->displayFileSize($coursesCsvPath, 'Courses');
        $this->displayFileSize($auditCsvPath, 'Audit');

        // Import using COPY command
        $this->info("Importing courses using PostgreSQL COPY...");

        if (!$this->importCsvWithCopy('courses', 'id, tenant_id, title, description, created_at, updated_at', $coursesCsvPath)) {
            $this->cleanupCsvFiles([$coursesCsvPath, $auditCsvPath], $keepCsv);
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Successfully imported {$count} courses!");
        $this->displayTableCount('courses', $tenantId);

        // Import audit records
        $this->info("Importing course audit records using PostgreSQL COPY...");

        if (!$this->importCsvWithCopy('course_audits', 'tenant_id, object_id, type, diffs, transaction_hash, blame_id, blame_user, created_at', $auditCsvPath)) {
            $this->cleanupCsvFiles([$coursesCsvPath, $auditCsvPath], $keepCsv);
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Successfully imported " . ($count * 7) . " audit records!");
        $this->displayTableCount('course_audits', $tenantId, 'course audit records');

        // Clean up CSV files
        $this->cleanupCsvFiles([$coursesCsvPath, $auditCsvPath], $keepCsv, ['Courses', 'Audit']);

        return Command::SUCCESS;
    }
}
