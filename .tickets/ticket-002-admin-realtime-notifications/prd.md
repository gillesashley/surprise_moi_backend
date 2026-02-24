# Ticket-002: Admin Client Real-time Notifications via Laravel Echo

## 1. Problem Statement

The admin client currently lacks a unified notification system for receiving real-time events triggered by Laravel Reverb. While some events exist (vendor approvals, chat messages), there is no centralized notification UI or standardized event handling system. This leads to:

- No unified notification center for admins to view all real-time events
- Inconsistent real-time updates across different admin sections
- No notification history or read/unread status tracking
- Missing notifications when admin is not viewing the specific page
- No support for notification types beyond vendor approvals (chat, system events, etc.)

## 2. Proposed Solution

Implement a comprehensive real-time notification system in the admin client using Laravel Echo and Reverb. The system will provide:

- **Unified Notification Center**: A dropdown/panel in the admin header showing all notifications
- **Event Subscription System**: Centralized event handling for all broadcast events
- **Notification Types**: Support for vendor events, chat events, system events
- **Read/Unread Tracking**: Database-backed notification read status
- **Real-time UI Updates**: Toast/popup notifications for important events

Architecture Overview:

- **Backend**: Laravel Events broadcast via Reverb (already configured)
- **Client**: Laravel Echo listens to private admin channel
- **Notification Model**: Database table for persisting notifications with read status
- **UI Components**: Notification bell icon, dropdown panel, toast system

## 3. Acceptance Criteria

### Functional Requirements

- [x] Admin can view all notifications in a centralized notification dropdown
- [x] Notifications display with proper type icon, title, message, and timestamp
- [x] Unread notification count shown in notification bell badge
- [x] Clicking notification marks it as read
- [x] "Mark all as read" functionality works
- [x] Notifications persist in database (survive page refresh)
- [x] Real-time toast notifications appear for new incoming events
- [x] Support for multiple notification types:
  - Vendor applications (new, approved, rejected)
  - Chat messages
  - System notifications

### Quality Requirements

- [x] Notifications load within 500ms on page load
- [x] Real-time updates arrive within 100ms of server event
- [x] Graceful handling when Echo/Reverb connection fails (offline indicator)
- [x] Notification data includes: id, type, title, message, data, read_at, created_at
- [x] Proper TypeScript typing for notification objects

### Technical Requirements

- [x] Use existing `resources/js/lib/echo.ts` configuration
- [x] Subscribe to `admin` private channel for global admin events
- [x] Subscribe to user-specific channels as needed
- [x] Use existing notification models or create new ones
- [x] Follow existing React/TypeScript patterns in admin client

## 4. Technical Considerations

### Implementation Constraints

- Must use existing Laravel Echo setup (resources/js/lib/echo.ts)
- Must work with existing Reverb configuration
- Cannot duplicate existing event handlers (use existing events)
- Must integrate with existing admin authentication

### Existing Infrastructure

The project already has:
- `config/reverb.php` - Reverb server configuration
- `config/broadcasting.php` - Broadcasting configuration
- `resources/js/lib/echo.ts` - Echo client setup
- `routes/channels.php` - Channel authorization
- Events: `VendorApprovalSubmitted`, `VendorApproved`, `VendorRejected`, `MessageSent`, `MessagesRead`, `UserTyping`
- `use-vendor-approval-events.ts` hook (foundation for new system)

### Database Schema

Create/extend notification table:

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');
    $table->string('title');
    $table->text('message');
    $table->json('data')->nullable();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

### Integration Points

- Laravel Echo for real-time event listening
- Existing event classes for broadcasting
- Laravel Notifications for server-side notification creation
- React context for global notification state

## 5. Dependencies

### Dependencies

- Ticket-001 (Email/Token Job Queue) - Can be done independently

### External Requirements

- Laravel Reverb server running
- Redis for Reverb (already configured)
- Existing admin authentication system

## 6. Subtask Checklist

- [x] Task 1: Create database migration for notifications table
    - **Problem**: Need persistent storage for admin notifications
    - **Test**: Migration runs successfully and creates notifications table
    - **Subtasks**:
        - [x] Subtask 1.1: Create migration for notifications table
            - **Objective**: Database table for storing notifications
            - **Test**: `php artisan migrate` creates table with all required columns
        - [x] Subtask 1.2: Create Notification model with proper relationships
            - **Objective**: Eloquent model for notification queries
            - **Test**: Model can be queried by user_id, read_at status

- [x] Task 2: Create notification service for managing notifications
    - **Problem**: Need service to create, read, update notifications
    - **Test**: Service methods work correctly
    - **Subtasks**:
        - [x] Subtask 2.1: Create NotificationService class
            - **Objective**: Business logic for notification CRUD
            - **Test**: Can create, list, mark as read notifications
        - [x] Subtask 2.2: Add API endpoints for notification management
            - **Objective**: Frontend can fetch and update notifications
            - **Test**: Endpoints return correct JSON responses

- [x] Task 3: Create notification context and hooks for React
    - **Problem**: Need global state management for notifications in React
    - **Test**: Components can access notification state
    - **Subtasks**:
        - [x] Subtask 3.1: Create NotificationContext
            - **Objective**: Global React context for notification state
            - **Test**: Components consume context correctly
        - [x] Subtask 3.2: Create useNotifications hook
            - **Objective**: Convenience hook for notification actions
            - **Test**: Hook provides notification data and actions

- [x] Task 4: Create notification UI components
    - **Problem**: Need UI components to display notifications
    - **Test**: Components render correctly
    - **Subtasks**:
        - [x] Subtask 4.1: Create NotificationBell component
            - **Objective**: Bell icon with unread count badge
            - **Test**: Shows correct unread count, opens dropdown
        - [x] Subtask 4.2: Create NotificationDropdown component
            - **Objective**: Dropdown panel listing notifications
            - **Test**: Displays notification list, handles clicks
        - [x] Subtask 4.3: Create Toast notification component
            - **Objective**: Popup notifications for new events
            - **Test**: Toast appears and auto-dismisses

- [x] Task 5: Integrate Laravel Echo event listeners
    - **Problem**: Connect frontend to Reverb events
    - **Test**: Events trigger notification updates
    - **Subtasks**:
        - [x] Subtask 5.1: Create useEchoNotifications hook
            - **Objective**: Listen to admin channel events
            - **Test**: Events received and processed correctly
        - [x] Subtask 5.2: Handle vendor approval events
            - **Objective**: Show notifications for vendor events
            - **Test**: Vendor approval events create notifications
        - [x] Subtask 5.3: Handle chat message events
            - **Objective**: Show notifications for new messages
            - **Test**: MessageSent events create notifications

- [x] Task 6: Add notification components to admin layout
    - **Problem**: Integrate notification UI into existing admin pages
    - **Test**: Notification system visible and functional
    - **Subtasks**:
        - [x] Subtask 6.1: Add NotificationBell to admin header
            - **Objective**: Bell visible in admin navigation
            - **Test**: Bell appears in header on all admin pages
        - [x] Subtask 6.2: Add Toast provider to app
            - **Objective**: Enable toast notifications globally
            - **Test**: Toasts appear from anywhere in app

- [x] Task 7: Write tests for notification system
    - **Problem**: Ensure notification functionality works correctly
    - **Test**: All tests pass
    - **Subtasks**:
        - [x] Subtask 7.1: Write unit tests for NotificationService
            - **Objective**: Test service methods
            - **Test**: All unit tests pass
        - [x] Subtask 7.2: Write component tests for UI
            - **Objective**: Test React components
            - **Test**: Component tests pass

- [x] Task 8: Verify implementation and run linting
    - **Problem**: Ensure code quality
    - **Test**: Linting passes
    - **Subtasks**:
        - [x] Subtask 8.1: Run TypeScript type checking
            - **Objective**: No type errors in TypeScript code
            - **Test**: `npm run typecheck` passes
        - [x] Subtask 8.2: Run ESLint
            - **Objective**: Code follows linting rules
            - **Test**: `npm run lint` passes
