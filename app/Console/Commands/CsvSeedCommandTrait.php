<?php

namespace App\Console\Commands;

use Carbon\Carbon;
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
        return DB::connection('operational')
            ->table('tenants')
            ->where('id', $tenantId)
            ->exists();
    }

    /**
     * Bulk insert records using raw SQL for optimal performance
     */
    protected function bulkInsert(string $tableName, array $columns, array $records, int $chunkSize = 1000): bool
    {
        try {
            $chunks = array_chunk($records, $chunkSize);
            $columnList = implode(', ', $columns);

            foreach ($chunks as $chunk) {
                $placeholders = [];
                $values = [];

                foreach ($chunk as $record) {
                    $recordPlaceholders = array_fill(0, count($record), '?');
                    $placeholders[] = '('.implode(', ', $recordPlaceholders).')';
                    $values = array_merge($values, array_values($record));
                }

                $placeholderString = implode(', ', $placeholders);
                $sql = "INSERT INTO {$tableName} ({$columnList}) VALUES {$placeholderString}";

                DB::connection('operational')->statement($sql, $values);
            }

            return true;
        } catch (\Exception $e) {
            $this->error("Failed to bulk insert into {$tableName}: ".$e->getMessage());

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
