# Ticket-001: Job Queue System for Email and Token Sending

## 1. Problem Statement

Currently, email sending and token generation during authentication/registration are performed synchronously. When these operations fail (due to SMTP issues, network problems, rate limiting, etc.), there is no visibility into failures, no automatic retry mechanism, and administrators cannot monitor job status. This leads to:

- Failed user registrations where verification emails never arrive
- Locked-out users who never receive password reset tokens
- No visibility into system health regarding notification delivery
- Manual intervention required to identify and fix delivery failures
- Poor user experience with no feedback on email delivery issues

Business Impact:

- User registration abandonment due to undelivered verification emails
- Customer support tickets for "missing" password reset emails
- Inability to audit or troubleshoot notification delivery issues
- No retry mechanism means transient failures result in permanent failures

## 2. Proposed Solution

Implement a robust job queue system using Laravel's built-in queue infrastructure with Redis as the backing store. The system will:

- **Queue Infrastructure**: Use Laravel's Redis queue driver (already configured in docker-compose.yml)
- **Job Classes**: Create Laravel Job classes for email and token operations
- **Automatic Retry**: Configure exponential backoff retry strategy for failed jobs via `retry_after` and max attempts
- **Failed Jobs Table**: Use Laravel's native `failed_jobs` table for admin visibility and audit trail
- **Admin Dashboard**: Create API endpoints for admins to view job status, failed jobs, and trigger manual retries
- **Job Types**: Separate queues for:
    - `emails`: Verification, password reset, notifications
    - `tokens`: Token generation and delivery

Architecture Overview:

- Producer: Authentication/registration services dispatch jobs to Redis queues
- Queue: Laravel Redis driver with Bull queue semantics (already built-in)
- Worker: `php artisan queue:work` processes jobs with retry logic
- Database: Laravel's `failed_jobs` table for job status persistence
- Admin API: Endpoints for job monitoring and management

## 3. Acceptance Criteria

### Functional Requirements

- [x] All email sending operations (verification, password reset, notifications) dispatched to `emails` queue
- [x] All token generation operations dispatched to `tokens` queue
- [x] Jobs automatically retry on failure with exponential backoff (max 3 attempts)
- [x] Failed jobs are persisted in `failed_jobs` table with exception details
- [x] Admin API endpoint to list failed jobs with filtering by queue
- [x] Admin API endpoint to view failed job details including exception stack trace
- [x] Admin API endpoint to manually retry failed jobs via `queue:retry`
- [x] Job status visible through `failed_jobs` table: uuid, connection, queue, payload, exception

### Quality Requirements

- [x] Zero data loss - all failed jobs are persisted and recoverable
- [x] Jobs include timeout protection (max 60 seconds per attempt via `retry_after`)
- [x] Concurrency handling - multiple queue workers can process jobs safely
- [x] Idempotent operations - retrying jobs doesn't cause duplicate emails (use unique job IDs)
- [x] Job payload includes user context and metadata for debugging

### Performance Requirements

- [x] Job dispatching adds < 10ms overhead to request processing
- [x] Failed job visibility accessible within 5 seconds of failure
- [x] Support for 100+ jobs per minute during peak loads

### Security Requirements

- [x] Admin endpoints require admin authentication (existing auth middleware)
- [x] Job payload does not expose sensitive information (tokens masked in logs)

## 4. Technical Considerations

### Implementation Constraints

- Must use existing Redis instance for queue backing (already in docker-compose.yml)
- Must integrate with existing Laravel Eloquent models
- Cannot block user registration/authentication flows
- Must be backward compatible with existing email providers

### Laravel Queue Configuration

The project already has:

- `QUEUE_CONNECTION=redis` in .env
- Redis service available in docker-compose.yml
- Laravel queue config supporting Redis driver

Required configuration updates:

- Set `REDIS_QUEUE=emails,tokens` for separate queues
- Configure `retry_after=90` for reasonable backoff
- Configure `maxAttempts=3` for failed job retries

### Performance Requirements

- Queue should handle burst traffic (100+ jobs in < 1 second)
- Job processing should not block event loop (queue workers run in separate process)
- Memory usage bounded by Redis queue depth

### Security Considerations

- Tokens must not be logged in plaintext
- Failed job payload encryption at rest in database
- Admin-only access to job failure details

### Integration Points

- Existing email service provider (SMTP configured in .env)
- Existing Laravel Fortify authentication system
- Existing Laravel Sanctum for API authentication
- Existing Redis instance (already configured)

### Database Schema Extensions

Laravel's native `failed_jobs` table will be used. Migration required:

```php
php artisan queue:failed-table
php artisan migrate
```

## 5. Dependencies

### Dependencies

- None - this is the first ticket

### External Requirements

- Redis server (already available in docker-compose.yml)
- Existing SMTP email credentials (no new integrations needed)
- Laravel's built-in queue infrastructure (already installed)

## 6. Subtask Checklist

- [x] Task 1: Configure Laravel queue settings for Redis
    - **Problem**: Need to configure Laravel's Redis queue for reliable async processing
    - **Test**: Redis queue accepts and processes test jobs
    - **Subtasks**:
        - [x] Subtask 1.1: Update .env with queue configuration
            - **Objective**: Configure Redis queues for emails and tokens
            - **Test**: `QUEUE_CONNECTION=redis` with `REDIS_QUEUE=emails,tokens`
        - [x] Subtask 1.2: Create queue migration for failed_jobs table
            - **Objective**: Laravel's failed_jobs table for tracking failures
            - **Test**: `php artisan migrate` creates failed_jobs table
        - [x] Subtask 1.3: Update config/queue.php with retry settings
            - **Objective**: Configure retry_after and max attempts
            - **Test**: Queue config reflects exponential backoff settings

- [x] Task 2: Create base Job class with common functionality
    - **Problem**: Need reusable Job base class with retry logic and logging
    - **Test**: Base Job class works with Laravel's queue system
    - **Subtasks**:
        - [x] Subtask 2.1: Create abstract BaseJob class
            - **Objective**: Reusable job infrastructure with retry support
            - **Test**: BaseJob extends ShouldQueue and has $maxAttempts
        - [x] Subtask 2.2: Add job logging and monitoring
            - **Objective**: Track job lifecycle in logs
            - **Test**: Job start/complete/fail logged appropriately
        - [x] Subtask 2.3: Configure exponential backoff
            - **Objective**: Retry logic with increasing delays
            - **Test**: Jobs retry at increasing intervals (60s, 180s, 300s)

- [x] Task 3: Implement email sending job queue
    - **Problem**: Move synchronous email sending to async job queue
    - **Test**: Emails are successfully sent via Laravel queue
    - **Subtasks**:
        - [x] Subtask 3.1: Create SendEmail job class
            - **Objective**: Generic email job for any email type
            - **Test**: Job dispatches and processes email successfully
        - [x] Subtask 3.2: Create registration verification email job
            - **Objective**: Handle user verification emails
            - **Test**: Verification email sent via queue on registration
        - [x] Subtask 3.3: Create password reset email job
            - **Objective**: Handle password reset token emails
            - **Test**: Reset email sent via queue on password reset request
        - [x] Subtask 3.4: Update registration controller to dispatch email job
            - **Objective**: Verification emails dispatched to queue
            - **Test**: User registration triggers SendVerificationEmail job

- [x] Task 4: Implement token generation job queue
    - **Problem**: Move synchronous token generation to async job queue
    - **Test**: Tokens are generated and delivered via job queue
    - **Subtasks**:
        - [x] Subtask 4.1: Create GenerateToken job class
            - **Objective**: Async token generation with secure handling
            - **Test**: Token job generates and delivers token correctly
        - [x] Subtask 4.2: Create SendPasswordResetToken job
            - **Objective**: Password reset token via queue
            - **Test**: Reset token sent via queue on password reset request
        - [x] Subtask 4.3: Update password reset flow to dispatch token job
            - **Objective**: Password reset tokens dispatched to queue
            - **Test**: Password reset flow uses token generation job

- [x] Task 5: Create admin API endpoints for job monitoring
    - **Problem**: Admins need visibility into job status and failures
    - **Test**: Admin can list failed jobs and trigger retries via API
    - **Subtasks**:
        - [x] Subtask 5.1: Create GET /api/admin/jobs/failed endpoint
            - **Objective**: List failed jobs with filtering by queue
            - **Test**: Endpoint returns paginated list of failed jobs
        - [x] Subtask 5.2: Create GET /api/admin/jobs/failed/{id} endpoint
            - **Objective**: Get detailed failed job information
            - **Test**: Endpoint returns job payload and exception details
        - [x] Subtask 5.3: Create POST /api/admin/jobs/failed/{id}/retry endpoint
            - **Objective**: Manually retry a failed job
            - **Test**: Failed job is re-enqueued for processing
        - [x] Subtask 5.4: Create POST /api/admin/jobs/retry-all endpoint
            - **Objective**: Retry all failed jobs for a queue
            - **Test**: All failed jobs for queue are re-enqueued

- [x] Task 6: Configure queue worker and supervisor
    - **Problem**: Ensure queue workers run reliably in production
    - **Test**: Queue workers process jobs continuously
    - **Subtasks**:
        - [x] Subtask 6.1: Create supervisor config for queue workers
            - **Objective**: Auto-restart failed queue workers
            - **Test**: Supervisor manages queue:work processes
        - [x] Subtask 6.2: Create docker-compose service for queue worker
            - **Objective**: Queue workers in containerized environment
            - **Test**: `docker compose up` includes queue worker service
        - [x] Subtask 6.3: Configure queue worker startup script
            - **Objective**: Proper worker initialization
            - **Test**: Queue workers start with correct settings

- [x] Task 7: Write tests for job queue system
    - **Problem**: Ensure job queue reliability and correctness
    - **Test**: All tests pass (unit, integration)
    - **Subtasks**:
        - [x] Subtask 7.1: Write unit tests for Job classes
            - **Objective**: Test job handle() methods
            - **Test**: All unit tests pass
        - [x] Subtask 7.2: Write integration tests for job dispatching
            - **Objective**: Test complete job flow from dispatch to completion
            - **Test**: Integration tests verify job processing
        - [x] Subtask 7.3: Write tests for admin API endpoints
            - **Objective**: Test API endpoint responses and error handling
            - **Test**: API tests verify authentication and responses

- [x] Task 8: Verify implementation and run linting
    - **Problem**: Ensure code quality and proper integration
    - **Test**: Linting passes, no errors
    - **Subtasks**:
        - [x] Subtask 8.1: Run PHP code sniffer/linter
            - **Objective**: Code follows Laravel style guidelines
            - **Test**: `composer lint` or `phpcs` passes
        - [x] Subtask 8.2: Run static analysis
            - **Objective**: No type errors in PHP code
            - **Test**: `composer analyse` passes
        - [x] Subtask 8.3: Verify Docker builds successfully
            - **Objective**: Application containerizes correctly
            - **Test**: `docker compose build` succeeds
