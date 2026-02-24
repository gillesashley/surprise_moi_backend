# Ticket-005: Add Jobs View to Admin Dashboard

## 1. Problem Statement

Admins currently lack visibility into background job performance and execution. We need a dedicated jobs view in the admin dashboard that displays recent job logs with virtual scrolling, success/failure statistics, and monitoring capabilities. Using Laravel Pulse for comprehensive job monitoring will provide real-time insights into queue performance.

## 2. Proposed Solution

Integrate Laravel Pulse for job queue monitoring and create a new admin dashboard page featuring:

- Real-time job metrics via Pulse
- Virtual scrolled list of recent job logs
- Success/failure rate statistics
- Job queue status and performance indicators

## 3. Acceptance Criteria

- Admin dashboard includes a "Jobs" section accessible to admins
- Laravel Pulse provides real-time job monitoring metrics
- Recent job logs display with virtual scrolling for performance
- Statistics show success rates, failure rates, and queue throughput
- Page loads efficiently even with large log volumes

## 4. Technical Considerations

- Laravel Pulse requires database storage for metrics
- Virtual scrolling implemented in React frontend
- Job logs queried from database with pagination
- Stats calculated from job_batches and failed_jobs tables
- Performance optimized for high-volume job processing

## 5. Dependencies

None - all previous tickets completed

## 6. Subtask Checklist

- [x] Task 1: Install and configure Laravel Pulse
    - **Problem**: Need Pulse for job monitoring capabilities
    - **Test**: Pulse dashboard accessible and showing job metrics
    - **Subtasks**:
        - [x] Subtask 1.1: Install Laravel Pulse package
            - **Objective**: Add Pulse to composer dependencies
            - **Test**: Package installed without conflicts
        - [x] Subtask 1.2: Configure Pulse database storage
            - **Objective**: Set up Pulse migrations and storage
            - **Test**: Pulse tables created in database
        - [x] Subtask 1.3: Publish and configure Pulse assets
            - **Objective**: Publish Pulse config and views
            - **Test**: Pulse config file exists and is configured

- [x] Task 2: Create jobs dashboard API endpoints
    - **Problem**: Need backend APIs for job data and stats
    - **Test**: APIs return correct job data and statistics
    - **Subtasks**:
        - [x] Subtask 2.1: Create job logs API endpoint
            - **Objective**: API to fetch paginated job logs
            - **Test**: Returns job logs with pagination
        - [x] Subtask 2.2: Create job statistics API endpoint
            - **Objective**: API to calculate success/failure rates
            - **Test**: Returns accurate job statistics
        - [x] Subtask 2.3: Add admin authorization to endpoints
            - **Objective**: Ensure only admins can access job data
            - **Test**: Non-admin requests denied

- [x] Task 3: Create jobs dashboard frontend page
    - **Problem**: Need UI for displaying job monitoring data
    - **Test**: Admin can navigate to and view jobs dashboard
    - **Subtasks**:
        - [x] Subtask 3.1: Add jobs route to admin routing
            - **Objective**: Create route for jobs dashboard page
            - **Test**: Route accessible and protected by admin middleware
        - [x] Subtask 3.2: Create jobs dashboard controller
            - **Objective**: Laravel controller to serve jobs page
            - **Test**: Controller renders jobs dashboard view
        - [x] Subtask 3.3: Create jobs dashboard React component
            - **Objective**: Main React component for jobs view
            - **Test**: Component renders without errors

- [x] Task 4: Implement virtual scrolling for job logs
    - **Problem**: Large log volumes need efficient rendering
    - **Test**: Logs scroll smoothly without performance issues
    - **Subtasks**:
        - [x] Subtask 4.1: Install virtual scrolling library
            - **Objective**: Add react-window or similar to package.json
            - **Test**: Library installed and available
        - [x] Subtask 4.2: Implement virtual scrolled logs component
            - **Objective**: Component renders logs with virtual scrolling
            - **Test**: Large log lists scroll efficiently
        - [x] Subtask 4.3: Integrate with job logs API
            - **Objective**: Virtual component fetches data from API
            - **Test**: Infinite scroll loads more logs

- [x] Task 5: Add job statistics and Pulse integration
    - **Problem**: Need success/failure stats and Pulse monitoring
    - **Test**: Statistics display correctly and Pulse metrics show
    - **Subtasks**:
        - [x] Subtask 5.1: Add job statistics components
            - **Objective**: Display success/failure rates and counts
            - **Test**: Stats update with real job data
        - [x] Subtask 5.2: Integrate Pulse metrics
            - **Objective**: Embed Pulse dashboard in jobs view
            - **Test**: Pulse metrics display in admin dashboard
        - [x] Subtask 5.3: Add refresh/real-time updates
            - **Objective**: Stats update periodically or on refresh
            - **Test**: Data refreshes without page reload

- [x] Task 6: Test and optimize jobs dashboard
    - **Problem**: Ensure dashboard works correctly under load
    - **Test**: Dashboard loads quickly and displays accurate data
    - **Subtasks**:
        - [x] Subtask 6.1: Test with sample job data
            - **Objective**: Verify display with various job scenarios
            - **Test**: Handles success, failure, and pending jobs
        - [x] Subtask 6.2: Performance testing
            - **Objective**: Ensure good performance with large datasets
            - **Test**: Page loads within acceptable time limits
        - [x] Subtask 6.3: Admin access testing
            - **Objective**: Confirm proper authorization
            - **Test**: Only admins can access jobs dashboard
