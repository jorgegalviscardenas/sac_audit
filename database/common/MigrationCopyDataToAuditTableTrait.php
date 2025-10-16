<?php

namespace Database\Common;

use Carbon\Carbon;
use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait MigrationCopyDataToAuditTableTrait
{
    use MigrationCreateAuditTableTrait;

    public function up(): void
    {
        $this->executeTableMigration();
        $this->createPartitions();
        $this->migrateData();
    }

    protected function executeTableMigration(): void
    {
        // Create partitioned table for audit DB
        $this->createPartitionedTable();
    }

    protected function createPartitionedTable(): void
    {
        $tableName = $this->getTableName();

        echo "Creating partitioned table {$tableName} in audit database...\n";

        DB::connection(DB_CONN::AUDIT)->statement("
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id BIGSERIAL,
                tenant_id UUID NOT NULL,
                object_id UUID NOT NULL,
                type SMALLINT NOT NULL,
                diffs JSONB NOT NULL,
                transaction_hash VARCHAR(255) NOT NULL,
                blame_id VARCHAR(255) NOT NULL,
                blame_user VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        ");

        echo "Partitioned table {$tableName} created successfully.\n";
    }

    protected function getConnectionDB(): Builder
    {
        return Schema::connection(DB_CONN::AUDIT);
    }

    protected function migrateData(): void
    {
        $operationalConnection = DB::connection(DB_CONN::OPERATIONAL);
        $auditConnection = DB::connection(DB_CONN::AUDIT);
        $tableName = $this->getTableName();

        // Create storage directory if it doesn't exist
        $storagePath = storage_path('app/migration_data');
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        // Remove old migration files for this table
        $this->cleanupOldMigrationFiles($tableName, $storagePath);

        // Get min and max dates from operational table
        $dateRange = $operationalConnection->table($tableName)
            ->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')
            ->first();

        if (! $dateRange || ! $dateRange->min_date) {
            echo "No data to migrate from {$tableName}.\n";

            return;
        }

        $minDate = Carbon::parse($dateRange->min_date)->startOfMonth();
        $maxDate = Carbon::parse($dateRange->max_date)->endOfMonth();

        // Create temporary table once for all batches
        $tempTable = $this->createTemporaryImportTable($auditConnection, $tableName);

        // Generate monthly periods
        $currentPeriod = $minDate->copy();

        while ($currentPeriod->lte($maxDate)) {
            $periodStart = $currentPeriod->copy()->startOfMonth();
            $periodEnd = $currentPeriod->copy()->endOfMonth();

            echo "Processing {$tableName} period: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}\n";

            // Export data for this period to CSV
            $csvFileName = "{$tableName}_{$periodStart->format('Y_m')}.csv";
            $csvPath = "{$storagePath}/{$csvFileName}";

            // Check if period has data before processing
            $periodCount = $operationalConnection->table($tableName)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            if ($periodCount === 0) {
                echo "No records found for period {$periodStart->format('Y-m')}\n";
                $currentPeriod->addMonth();

                continue;
            }

            echo "Found {$periodCount} records for period {$periodStart->format('Y-m')}\n";

            // Stream records to CSV using chunking to avoid memory issues
            $fp = fopen($csvPath, 'w');
            $recordsExported = 0;

            $operationalConnection->table($tableName)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->orderBy('id')
                ->chunk(100000, function ($records) use ($fp, &$recordsExported) {
                    foreach ($records as $record) {
                        $line = implode("\t", [
                            $record->id,
                            $record->tenant_id,
                            $record->object_id,
                            $record->type,
                            str_replace(["\r", "\n", "\t", '\\'], ['\\r', '\\n', '\\t', '\\\\'], $record->diffs),
                            $record->transaction_hash,
                            $record->blame_id,
                            $record->blame_user,
                            $record->created_at,
                        ])."\n";

                        fwrite($fp, $line);
                        $recordsExported++;
                    }

                    // Show progress every 30000 records
                    echo "Exported {$recordsExported} records...\n";
                });

            fclose($fp);

            echo "Exported {$recordsExported} records to {$csvFileName}\n";

            // Import data - PostgreSQL will automatically route to correct partition
            echo "Importing {$recordsExported} records to audit database...\n";
            $this->importDataFromCSV($auditConnection, $tableName, $tempTable, $csvPath);

            echo "Imported {$recordsExported} records to audit database\n";

            // Optionally delete the CSV file after successful import
            // unlink($csvPath);

            $currentPeriod->addMonth();
        }

        // Drop temporary table after all batches
        $this->dropTemporaryImportTable($auditConnection, $tempTable);

        echo "Data migration completed for {$tableName}.\n";

        // Validate migration integrity
        $this->validateMigration($operationalConnection, $auditConnection, $tableName, $storagePath);

        // Clean up migration files after successful validation
        $this->cleanupOldMigrationFiles($tableName, $storagePath);
    }

    protected function createTemporaryImportTable($connection, string $tableName): string
    {
        $tempTable = "{$tableName}_temp_import";

        echo "Creating temporary table {$tempTable} for batch imports...\n";

        DB::connection(DB_CONN::AUDIT)->statement("
            CREATE TEMPORARY TABLE {$tempTable} (
                id BIGINT,
                tenant_id UUID,
                object_id UUID,
                type SMALLINT,
                diffs JSONB,
                transaction_hash VARCHAR(255),
                blame_id VARCHAR(255),
                blame_user VARCHAR(255),
                created_at TIMESTAMP
            )
        ");

        echo "Temporary table {$tempTable} created.\n";

        return $tempTable;
    }

    protected function importDataFromCSV($connection, string $tableName, string $tempTable, string $csvPath): void
    {
        // Truncate temp table for reuse
        DB::connection(DB_CONN::AUDIT)->statement("TRUNCATE TABLE {$tempTable}");

        // Load data into temp table using COPY
        $pdo = $connection->getPdo();
        $pdo->pgsqlCopyFromFile(
            "{$tempTable} (id, tenant_id, object_id, type, diffs, transaction_hash, blame_id, blame_user, created_at)",
            $csvPath
        );

        // Insert from temp table to partitioned table (PostgreSQL routes to correct partition)
        DB::connection(DB_CONN::AUDIT)->statement("
            INSERT INTO {$tableName} (id, tenant_id, object_id, type, diffs, transaction_hash, blame_id, blame_user, created_at)
            SELECT id, tenant_id, object_id, type, diffs, transaction_hash, blame_id, blame_user, created_at
            FROM {$tempTable}
        ");
    }

    protected function dropTemporaryImportTable($connection, string $tempTable): void
    {
        echo "Dropping temporary table {$tempTable}...\n";

        DB::connection(DB_CONN::AUDIT)->statement("DROP TABLE IF EXISTS {$tempTable}");

        echo "Temporary table dropped.\n";
    }

    protected function validateMigration($operationalConnection, $auditConnection, string $tableName, string $storagePath): void
    {
        // Validate by comparing checksums of ID lists
        echo "Validating data integrity using ID checksum comparison...\n";

        $operationalIdsFile = "{$storagePath}/{$tableName}_operational_ids.txt";
        $auditIdsFile = "{$storagePath}/{$tableName}_audit_ids.txt";

        // Export operational IDs using COPY TO with ORDER BY
        echo "Exporting operational IDs to file...\n";
        $operationalPdo = $operationalConnection->getPdo();
        $operationalPdo->pgsqlCopyToFile(
            "(SELECT id FROM {$tableName} ORDER BY id)",
            $operationalIdsFile
        );

        // Export audit IDs using COPY (SELECT ...) variant for partitioned tables
        echo "Exporting audit IDs to file...\n";
        $auditPdo = $auditConnection->getPdo();
        $auditPdo->pgsqlCopyToFile(
            "(SELECT id FROM {$tableName} ORDER BY id)",
            $auditIdsFile
        );

        // Calculate checksums
        $operationalChecksum = hash_file('sha256', $operationalIdsFile);
        $auditChecksum = hash_file('sha256', $auditIdsFile);

        echo "Operational IDs checksum: {$operationalChecksum}\n";
        echo "Audit IDs checksum: {$auditChecksum}\n";

        if ($operationalChecksum !== $auditChecksum) {
            // Get counts for error message
            $operationalCount = $operationalConnection->table($tableName)->count('id');
            $auditCount = $auditConnection->table($tableName)->count('id');

            throw new \Exception(
                "Data migration validation failed for {$tableName}. ".
                'ID checksums do not match. '.
                "Operational count: {$operationalCount}, Audit count: {$auditCount}. ".
                "Check files: {$operationalIdsFile} and {$auditIdsFile}"
            );
        }

        // Clean up ID files after successful validation
        unlink($operationalIdsFile);
        unlink($auditIdsFile);

        echo "Validation passed: ID checksums match perfectly.\n";
    }

    protected function cleanupOldMigrationFiles(string $tableName, string $storagePath): void
    {
        // Find all files matching pattern: {table_name}_*
        $pattern = "{$storagePath}/{$tableName}_*";
        $files = glob($pattern);

        if (empty($files)) {
            echo "No old migration files found for {$tableName}.\n";

            return;
        }

        $deletedCount = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $deletedCount++;
            }
        }

        echo "Cleaned up {$deletedCount} old migration file(s) for {$tableName}.\n";
    }

    protected function createPartitions(): void
    {
        $operationalConnection = DB::connection(DB_CONN::OPERATIONAL);
        $tableName = $this->getTableName();

        echo "Creating partitions for {$tableName} in audit database...\n";

        // Get min and max dates from operational table
        $dateRange = $operationalConnection->table($tableName)
            ->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')
            ->first();

        if (! $dateRange || ! $dateRange->min_date) {
            echo "No data found in operational {$tableName}, skipping partition creation.\n";

            return;
        }

        $minDate = Carbon::parse($dateRange->min_date)->startOfMonth();
        $maxDate = Carbon::parse($dateRange->max_date)->endOfMonth();

        // Create partitions for each month in the data range
        $currentPeriod = $minDate->copy();
        $partitionsCreated = 0;

        while ($currentPeriod->lte($maxDate)) {
            $periodStart = $currentPeriod->copy()->startOfMonth();
            $periodEnd = $currentPeriod->copy()->addMonth()->startOfMonth(); // Start of next month (exclusive upper bound)

            $partitionName = "{$tableName}_{$periodStart->format('Y_m')}";

            $this->createPartition($tableName, $partitionName, $periodStart, $periodEnd);
            $partitionsCreated++;

            $currentPeriod->addMonth();
        }

        // Create 3 additional future partitions
        for ($i = 0; $i < 3; $i++) {
            $periodStart = $currentPeriod->copy()->startOfMonth();
            $periodEnd = $currentPeriod->copy()->addMonth()->startOfMonth(); // Start of next month (exclusive upper bound)

            $partitionName = "{$tableName}_{$periodStart->format('Y_m')}";

            $this->createPartition($tableName, $partitionName, $periodStart, $periodEnd);
            $partitionsCreated++;

            $currentPeriod->addMonth();
        }

        echo "Successfully created {$partitionsCreated} partitions for {$tableName} in audit database.\n";
    }

    protected function createPartition(string $tableName, string $partitionName, Carbon $start, Carbon $end): void
    {
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        echo "Creating partition {$partitionName} for range [{$startStr}, {$endStr})...\n";

        DB::connection(DB_CONN::AUDIT)->statement("
            CREATE TABLE {$partitionName} PARTITION OF {$tableName}
            FOR VALUES FROM ('{$startStr}') TO ('{$endStr}')
        ");

        echo "Partition {$partitionName} created successfully.\n";
    }
}
