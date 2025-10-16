<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Support\Facades\DB;

trait CsvSeedCommandTrait
{
    /**
     * Calculate record distribution by month
     */
    protected function calculateMonthlyDistribution(int $totalCount, ?string $startDate, ?string $endDate): array
    {
        if (! $startDate || ! $endDate) {
            // No date range specified, return all records for current time
            return [
                [
                    'count' => $totalCount,
                    'start' => now(),
                    'end' => now(),
                ],
            ];
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Validate dates
        if ($start->greaterThan($end)) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }

        // Calculate months between dates
        $months = [];
        $current = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        while ($current->lessThanOrEqualTo($endMonth)) {
            $monthStart = $current->copy();
            $monthEnd = $current->copy()->endOfMonth();

            // Adjust first month to actual start date
            if ($monthStart->lessThan($start)) {
                $monthStart = $start->copy();
            }

            // Adjust last month to actual end date
            if ($monthEnd->greaterThan($end)) {
                $monthEnd = $end->copy();
            }

            $months[] = [
                'start' => $monthStart,
                'end' => $monthEnd,
                'days' => $monthStart->diffInDays($monthEnd) + 1,
            ];

            $current->addMonth();
        }

        $totalMonths = count($months);
        $recordsPerMonth = (int) floor($totalCount / $totalMonths);
        $remainder = $totalCount % $totalMonths;

        // Distribute records
        $distribution = [];
        foreach ($months as $index => $month) {
            $count = $recordsPerMonth;

            // Distribute remainder across first months
            if ($index < $remainder) {
                $count++;
            }

            if ($count > 0) {
                $distribution[] = [
                    'count' => $count,
                    'start' => $month['start'],
                    'end' => $month['end'],
                ];
            }
        }

        return $distribution;
    }

    /**
     * Generate random timestamp within a date range
     */
    protected function generateRandomTimestamp(Carbon $start, Carbon $end): Carbon
    {
        $startTimestamp = $start->timestamp;
        $endTimestamp = $end->timestamp;

        $randomTimestamp = mt_rand($startTimestamp, $endTimestamp);

        return Carbon::createFromTimestamp($randomTimestamp);
    }

    /**
     * Validate that a tenant exists in the operational database
     */
    protected function validateTenant(string $tenantId): bool
    {
        return DB::connection(DB_CONN::OPERATIONAL)
            ->table('tenants')
            ->where('id', $tenantId)
            ->exists();
    }

    /**
     * Bulk insert records using PostgreSQL COPY FROM STDIN (direct)
     * For non-partitioned tables only
     */
    protected function bulkInsertWithCopy(string $tableName, array $columns, array $records, bool $keepCsv = false, string $connection = 'operational'): bool
    {
        try {
            // Create storage directory if it doesn't exist
            $storagePath = storage_path('app/seeder_data');
            if (! is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Generate CSV file
            $csvFileName = "{$tableName}_".time().'_'.uniqid().'.csv';
            $csvPath = "{$storagePath}/{$csvFileName}";

            $fp = fopen($csvPath, 'w');

            foreach ($records as $record) {
                // Escape special characters for PostgreSQL COPY
                $escapedRecord = array_map(function ($value) {
                    if ($value === null) {
                        return '\\N';
                    }
                    // Convert booleans to PostgreSQL format
                    if (is_bool($value)) {
                        return $value ? 't' : 'f';
                    }

                    // Escape special characters
                    return str_replace(["\r", "\n", "\t", '\\'], ['\\r', '\\n', '\\t', '\\\\'], $value);
                }, $record);

                fwrite($fp, implode("\t", $escapedRecord)."\n");
            }

            fclose($fp);

            // Direct COPY into table
            $columnList = implode(', ', $columns);
            $pdo = DB::connection($connection)->getPdo();
            $pdo->pgsqlCopyFromFile(
                "{$tableName} ({$columnList})",
                $csvPath
            );

            // Clean up CSV file unless --keep-csv is specified
            if (! $keepCsv) {
                unlink($csvPath);
            }

            return true;
        } catch (\Exception $e) {
            $this->error("Failed to bulk insert into {$tableName}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Bulk insert records using temp table approach
     * For partitioned tables (audit tables)
     */
    protected function bulkInsertWithCopyPartitioned(string $tableName, array $columns, array $records, bool $keepCsv = false, string $connection = 'operational'): bool
    {
        try {
            // Create storage directory if it doesn't exist
            $storagePath = storage_path('app/seeder_data');
            if (! is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Generate CSV file
            $csvFileName = "{$tableName}_".time().'_'.uniqid().'.csv';
            $csvPath = "{$storagePath}/{$csvFileName}";

            $fp = fopen($csvPath, 'w');

            foreach ($records as $record) {
                // Escape special characters for PostgreSQL COPY
                $escapedRecord = array_map(function ($value) {
                    if ($value === null) {
                        return '\\N';
                    }
                    // Convert booleans to PostgreSQL format
                    if (is_bool($value)) {
                        return $value ? 't' : 'f';
                    }

                    // Escape special characters
                    return str_replace(["\r", "\n", "\t", '\\'], ['\\r', '\\n', '\\t', '\\\\'], $value);
                }, $record);

                fwrite($fp, implode("\t", $escapedRecord)."\n");
            }

            fclose($fp);

            $dbConnection = DB::connection($connection);
            $pdo = $dbConnection->getPdo();
            $tempTable = "{$tableName}_temp_".uniqid();
            $columnList = implode(', ', $columns);

            // Create temporary table
            $columnDefinitions = [];
            foreach ($columns as $column) {
                // Basic type mapping - adjust based on your schema
                $type = match ($column) {
                    'id', 'object_id', 'tenant_id', 'user_id', 'course_id' => 'UUID',
                    'type' => 'SMALLINT',
                    'diffs' => 'JSONB',
                    'transaction_hash', 'blame_id', 'blame_user', 'name', 'email', 'full_name', 'title', 'description' => 'VARCHAR(255)',
                    'enabled', 'is_completed' => 'BOOLEAN',
                    'created_at', 'updated_at' => 'TIMESTAMP',
                    'enrolled_at' => 'DATE',
                    default => 'TEXT'
                };
                $columnDefinitions[] = "{$column} {$type}";
            }

            $columnDefsString = implode(', ', $columnDefinitions);
            DB::connection($connection)->statement("CREATE TEMPORARY TABLE {$tempTable} ({$columnDefsString})");

            // COPY into temporary table
            $pdo->pgsqlCopyFromFile(
                "{$tempTable} ({$columnList})",
                $csvPath
            );

            // Insert from temp table to partitioned table
            DB::connection($connection)->statement("
                INSERT INTO {$tableName} ({$columnList})
                SELECT {$columnList}
                FROM {$tempTable}
            ");

            // Drop temporary table
            DB::connection($connection)->statement("DROP TABLE IF EXISTS {$tempTable}");

            // Clean up CSV file unless --keep-csv is specified
            if (! $keepCsv) {
                unlink($csvPath);
            }

            return true;
        } catch (\Exception $e) {
            $this->error("Failed to bulk insert into partitioned table {$tableName}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Verify and display count for a table
     */
    protected function displayTableCount(string $tableName, ?string $tenantId = null, ?string $label = null): void
    {
        $query = DB::connection('operational')->table($tableName);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = $query->count();
        $displayLabel = $label ?? str_replace('_', ' ', $tableName);

        if ($tenantId) {
            $this->info("Total {$displayLabel} for tenant {$tenantId}: {$count}");
        } else {
            $this->info("Total {$displayLabel}: {$count}");
        }
    }

    /**
     * Generate a random name
     */
    protected function generateRandomName(): string
    {
        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'James', 'Emma', 'Robert', 'Olivia',
            'William', 'Ava', 'Richard', 'Isabella', 'Joseph', 'Sophia', 'Thomas', 'Mia', 'Charles', 'Charlotte'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
            'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];

        return $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
    }

    /**
     * Generate a random company name
     */
    protected function generateCompanyName(): string
    {
        $adjectives = ['Global', 'Tech', 'Digital', 'Smart', 'Innovative', 'Advanced', 'Modern', 'Future', 'Elite', 'Prime'];
        $nouns = ['Systems', 'Solutions', 'Technologies', 'Enterprises', 'Industries', 'Corporation', 'Group', 'Partners', 'Services', 'Dynamics'];

        return $adjectives[array_rand($adjectives)].' '.$nouns[array_rand($nouns)];
    }

    /**
     * Generate a random course title
     */
    protected function generateCourseTitle(): string
    {
        $subjects = ['Introduction to', 'Advanced', 'Complete', 'Mastering', 'Professional', 'Fundamentals of'];
        $topics = ['Web Development', 'Data Science', 'Machine Learning', 'Cloud Computing', 'Cybersecurity',
            'Mobile Development', 'DevOps', 'Blockchain', 'AI', 'Software Engineering'];

        return $subjects[array_rand($subjects)].' '.$topics[array_rand($topics)];
    }
}
