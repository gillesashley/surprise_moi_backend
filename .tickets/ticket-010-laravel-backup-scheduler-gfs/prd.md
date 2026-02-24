# Ticket: Implement Laravel Scheduler for Database Backup with GFS Cleanup

## Problem Statement

The project currently has a bash-based backup system with a simple retention policy (keep last 7 days), but it lacks support for Grandfather-Father-Son (GFS) rotation. GFS provides better long-term backup retention with daily, weekly, and monthly backups.

## Proposed Solution

Enhance the existing bash backup script with GFS rotation support and integrate it with Laravel's scheduler for automated daily backups.

## Implementation Plan

### Phase 1: Enhance Backup Script with GFS Retention

1. **Modify do_backup function** - Replace simple retention with GFS policy
2. **Add backup classification logic** - Identify daily, weekly, monthly backups
3. **Implement GFS cleanup rules** - Delete backups according to GFS schedule
4. **Enhance logging** - Track which backups are retained/deleted

### Phase 2: Create Laravel Scheduler Foundation

1. **Create missing Kernel.php file** - Establish the scheduler configuration
2. **Set up basic scheduler structure** - Define the schedule method and command registration

### Phase 3: Create Laravel Console Command

1. **Create BackupCommand.php** - Wrap the bash script in a Laravel console command
2. **Add configuration options** - Allow customization via command arguments/options
3. **Implement logging** - Log backup activity to Laravel logs
4. **Add error handling** - Gracefully handle backup failures

### Phase 4: Configure Scheduler

1. **Define daily backup schedule** - Schedule the backup command to run daily
2. **Add cleanup task** - Schedule GFS cleanup as part of backup process
3. **Configure notification** - Set up failure notifications via email/SMS
4. **Test the scheduler** - Verify the backup and cleanup process

### Phase 5: Verification & Testing

1. **Test manual execution** - Run the backup command manually to verify functionality
2. **Test scheduler execution** - Verify the schedule runs correctly
3. **Test backup restoration** - Ensure backups can be restored properly
4. **Monitor execution logs** - Check for errors or warnings

## Files to Modify

1. `app/Console/Kernel.php` - Create new file
2. `app/Console/Commands/BackupCommand.php` - Create new file
3. `scripts/manage.sh` - Enhance existing backup script with GFS retention
4. `config/filesystems.php` - Verify storage configuration
5. `.env.example` - Add backup configuration variables

## Configuration Variables (to be added to .env)

```env
# Backup Configuration
BACKUP_RETENTION_DAILY=7
BACKUP_RETENTION_WEEKLY=4
BACKUP_RETENTION_MONTHLY=12
BACKUP_PATH=./backups
BACKUP_EXCLUDE_TABLES=migrations,password_reset_tokens,sessions,failed_jobs,job_batches,jobs,cache,cache_locks
BACKUP_NOTIFICATION_EMAIL=admin@example.com
```

## GFS Retention Policy Details

- **Daily backups**: Keep last 7 days (Mon-Sun)
- **Weekly backups**: Keep last 4 weeks (every Sunday)
- **Monthly backups**: Keep last 12 months (first day of month)
- **Backup naming pattern**: `surprisemoi_{type}_{timestamp}.sql.gz` where type is full or data

## Expected Outcome

- Automated daily database backups with GFS retention
- Automatic cleanup of old backups according to GFS policy
- Detailed logging of backup activity
- Email notifications for backup failures
- Manual backup command available: `php artisan backup:run`
- Scheduler runs daily at 2:00 AM (configurable)

## Risk Assessment

- **Low risk** - Enhancement of existing system, no breaking changes
- **Backward compatible** - Existing backup script remains functional
- **Testable** - Can be tested in staging before production deployment
