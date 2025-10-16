# SAC Audit

## Database Seeding Commands

All seeding commands use PostgreSQL's `COPY FROM STDIN` for optimal performance. Each entity automatically generates 7 audit records (1 CREATE + 6 UPDATE).

### Common Options

All commands support the following options:

- `--start-date=YYYY-MM-DD` - Start date for record creation timestamps
- `--end-date=YYYY-MM-DD` - End date for record creation timestamps
- `--keep-csv` - Keep CSV files after import (useful for debugging)
- `--audit-connection=operational|audit` - Database connection for audit tables (default: operational)

When using `--start-date` and `--end-date`, records are distributed evenly across months. For example, 120 records from September 1 to October 31 will create 60 records in September and 60 in October, each with random timestamps within their respective months.

### Audit Connection Options

The `--audit-connection` option allows you to choose where audit records are stored:

- `operational` (default): Stores audit records in the operational database (non-partitioned tables)
- `audit`: Stores audit records in the audit database (partitioned tables by month)

When using `--audit-connection=audit`, the command automatically uses a temporary table approach to handle partitioned tables correctly.

### 1. Seed Tenants

```bash
# Seed 100 tenants with 700 audit records
php artisan seed:tenants-csv 100

# Distribute 120 tenants across September and October (60 per month)
php artisan seed:tenants-csv 120 --start-date=2024-09-01 --end-date=2024-10-31

# Seed to audit database with partitioned tables
php artisan seed:tenants-csv 100 --audit-connection=audit

# Keep CSV files for inspection
php artisan seed:tenants-csv 100 --keep-csv
```

**Creates**:
- Tenants in `tenants` table (operational DB)
- 7 audit records per tenant in `tenant_audits` table (1 CREATE + 6 UPDATE)

---

### 2. Seed Users

```bash
# Seed 3.6 million users with 25.2 million audit records
php artisan seed:users-csv 3600000 --tenant=YOUR-TENANT-UUID

# Distribute users across a date range
php artisan seed:users-csv 1000 --tenant=YOUR-TENANT-UUID --start-date=2024-01-01 --end-date=2024-12-31

# Seed to audit database with partitioned tables
php artisan seed:users-csv 1000 --tenant=YOUR-TENANT-UUID --audit-connection=audit
```

**Creates**:
- Users in `users` table (operational DB)
- 7 audit records per user in `user_audits` table (1 CREATE + 6 UPDATE)

**Requirements**: Valid tenant UUID

---

### 3. Seed Courses

```bash
# Seed 50,000 courses with 350,000 audit records
php artisan seed:courses-csv 50000 --tenant=YOUR-TENANT-UUID

# Distribute courses across a date range
php artisan seed:courses-csv 500 --tenant=YOUR-TENANT-UUID --start-date=2024-01-01 --end-date=2024-06-30

# Seed to audit database with partitioned tables
php artisan seed:courses-csv 500 --tenant=YOUR-TENANT-UUID --audit-connection=audit
```

**Creates**:
- Courses in `courses` table (operational DB)
- 7 audit records per course in `course_audits` table (1 CREATE + 6 UPDATE)

**Requirements**: Valid tenant UUID

---

### 4. Seed Course Enrollments

```bash
# Seed 10 million enrollments with 70 million audit records
php artisan seed:course-enrollments-csv 10000000 --tenant=YOUR-TENANT-UUID

# Distribute enrollments across a date range
php artisan seed:course-enrollments-csv 5000 --tenant=YOUR-TENANT-UUID --start-date=2024-09-01 --end-date=2024-12-31

# Seed to audit database with partitioned tables
php artisan seed:course-enrollments-csv 5000 --tenant=YOUR-TENANT-UUID --audit-connection=audit
```

**Creates**:
- Enrollments in `course_enrollments` table (randomly assigns existing users to courses) (operational DB)
- 7 audit records per enrollment in `course_enrollment_audits` table (1 CREATE + 6 UPDATE)

**Requirements**:
- Valid tenant UUID
- Existing users and courses for the tenant (seed users and courses first)

---

## Recommended Seeding Order

1. **Tenants** - Create tenants first
2. **Users** - Create users for each tenant
3. **Courses** - Create courses for each tenant
4. **Enrollments** - Create enrollments (links users to courses)

## Date Distribution Examples

### Example 1: Simulate 6 months of user growth
```bash
# Create 6,000 users from July to December (1,000 per month)
php artisan seed:users-csv 6000 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-07-01 --end-date=2024-12-31
```

### Example 2: Historical data with enrollments
```bash
# Step 1: Create users over the year
php artisan seed:users-csv 12000 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-01-01 --end-date=2024-12-31

# Step 2: Create courses
php artisan seed:courses-csv 100 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-01-01 --end-date=2024-03-31

# Step 3: Create enrollments throughout the year
php artisan seed:course-enrollments-csv 50000 --tenant=YOUR-TENANT-UUID \
  --start-date=2024-01-01 --end-date=2024-12-31
```

## Common Functionality (Trait)

All commands use the `CsvSeedCommandTrait` which provides:

- **Tenant validation** - Ensures tenant exists before seeding
- **COPY FROM STDIN** - Uses PostgreSQL's fastest bulk import method
- **Partitioned table support** - Automatic temp table approach for partitioned audit tables
- **Monthly distribution** - Evenly distributes records across months
- **Random timestamps** - Generates realistic timestamps within date ranges
- **Progress tracking** - Visual progress bars for large datasets
- **Random data generators** - Names, company names, course titles
- **CSV file management** - Generates tab-delimited CSV files, automatically cleaned up unless `--keep-csv` is used

## Performance Notes

- **Best for**: Large datasets (10K+ records)
- **Speed**: 2-5x faster than bulk INSERT statements using PostgreSQL's COPY command
- **Memory**: Generates data in batches per monthly period to manage memory efficiently
- **Storage**: CSV files temporarily stored in `storage/app/seeder_data/`
- **Database connections**:
  - Main tables (tenants, users, courses, enrollments): Always use `operational` connection
  - Audit tables: Use `operational` or `audit` connection based on `--audit-connection` option
- **Import methods**:
  - Non-partitioned tables: Direct COPY FROM STDIN (fastest)
  - Partitioned tables (audit DB): Temporary table + INSERT SELECT (required for partitioned tables)

## Database Architecture

### Operational Database
- Contains main entity tables (tenants, users, courses, enrollments)
- Optionally contains audit tables (non-partitioned)

### Audit Database
- Contains partitioned audit tables (partitioned by `created_at` month)
- Automatically routes data to correct partition
- Optimized for time-series audit data queries

### Migration Commands

```bash
# Run migrations to create tables
php artisan migrate

# Migrate audit data from operational to audit DB with partitioning
php artisan migrate --path=database/migrations/2025_10_16_110609_migrate_tenant_audit_table_to_audit_db.php
```

The migration automatically:
- Creates partitioned table in audit DB
- Creates monthly partitions based on data range + 3 future partitions
- Exports data from operational DB using chunking (memory-efficient)
- Imports using temp table approach (works with partitioned tables)
- Validates data integrity via ID checksum comparison
