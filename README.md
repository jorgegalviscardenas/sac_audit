# SAC Audit

## Database Seeding Commands

All seeding commands use bulk inserts with raw SQL for optimal performance. Each entity automatically generates 7 audit records (1 CREATE + 6 UPDATE).

### Common Options

All commands support the following options:

- `--start-date=YYYY-MM-DD` - Start date for record creation timestamps
- `--end-date=YYYY-MM-DD` - End date for record creation timestamps

When using `--start-date` and `--end-date`, records are distributed evenly across months. For example, 120 records from September 1 to October 31 will create 60 records in September and 60 in October, each with random timestamps within their respective months.

### 1. Seed Tenants

```bash
# Seed 100 tenants with 700 audit records
php artisan seed:tenants-csv 10

# Distribute 120 tenants across September and October (60 per month)
php artisan seed:tenants-csv 12 --start-date=2024-09-01 --end-date=2024-10-31
```

**Creates**:
- Tenants in `tenants` table
- 7 audit records per tenant in `tenant_audit` table (1 CREATE + 6 UPDATE)

---

### 2. Seed Users

```bash
# Seed 3.6 million users with 25.2 million audit records
php artisan seed:users-csv 3600000 --tenant=YOUR-TENANT-UUID

# Distribute users across a date range
php artisan seed:users-csv 1000 --tenant=YOUR-TENANT-UUID --start-date=2024-01-01 --end-date=2024-12-31
```

**Creates**:
- Users in `users` table
- 7 audit records per user in `user_audit` table (1 CREATE + 6 UPDATE)

**Requirements**: Valid tenant UUID

---

### 3. Seed Courses

```bash
# Seed 50,000 courses with 350,000 audit records
php artisan seed:courses-csv 50000 --tenant=YOUR-TENANT-UUID

# Distribute courses across a date range
php artisan seed:courses-csv 500 --tenant=YOUR-TENANT-UUID --start-date=2024-01-01 --end-date=2024-06-30
```

**Creates**:
- Courses in `courses` table
- 7 audit records per course in `course_audit` table (1 CREATE + 6 UPDATE)

**Requirements**: Valid tenant UUID

---

### 4. Seed Course Enrollments

```bash
# Seed 10 million enrollments with 70 million audit records
php artisan seed:course-enrollments-csv 10000000 --tenant=YOUR-TENANT-UUID

# Distribute enrollments across a date range
php artisan seed:course-enrollments-csv 5000 --tenant=YOUR-TENANT-UUID --start-date=2024-09-01 --end-date=2024-12-31
```

**Creates**:
- Enrollments in `course_enrollments` table (randomly assigns existing users to courses)
- 7 audit records per enrollment in `course_enrollment_audit` table (1 CREATE + 6 UPDATE)

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
- **Bulk insert operations** - Efficient raw SQL bulk inserts (1000 records per chunk)
- **Monthly distribution** - Evenly distributes records across months
- **Random timestamps** - Generates realistic timestamps within date ranges
- **Progress tracking** - Visual progress bars for large datasets
- **Random data generators** - Names, company names, course titles

## Performance Notes

- **Best for**: Large datasets (10K+ records)
- **Speed**: Significantly faster than individual inserts, optimized for bulk operations
- **Memory**: Generates data in-memory before bulk insertion
- **Database**: Uses `operational` connection for all tables
- **Chunk size**: 1000 records per SQL statement for optimal performance
