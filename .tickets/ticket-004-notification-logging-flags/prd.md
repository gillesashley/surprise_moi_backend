# Ticket-004: Add Notification Logging Flags

## 1. Problem Statement

Currently, SMS and email notifications are sent in all environments, which can be problematic for development and testing where real messages shouldn't be sent. We need environment flags to switch these notifications to logging mode instead of sending real messages, allowing developers to check logs for notification content without incurring costs or sending unwanted messages.

## 2. Proposed Solution

Add SMS_LOG_ONLY and EMAIL_LOG_ONLY environment variables that, when set to true, cause SMS and email notifications to be logged instead of sent via API/SMTP. This provides a safe way to test notification flows in development environments.

## 3. Acceptance Criteria

- SMS_LOG_ONLY=true causes SMS to be logged instead of sent via KairosAfrika API
- EMAIL_LOG_ONLY=true causes emails to be logged instead of sent via SMTP
- Existing functionality is preserved when flags are false (default)
- Logs include masked sensitive data (phone numbers, email addresses)
- No breaking changes to existing notification sending code

## 4. Technical Considerations

- Laravel config system for environment flags
- Modify KairosAfrikaSmsService for SMS logging
- Override mail driver dynamically for email logging
- Maintain consistent logging format with existing services
- No performance impact on production when flags disabled

## 5. Dependencies

None - all previous tickets completed

## 6. Subtask Checklist

- [x] Task 1: Add environment flags and config
    - **Problem**: Need flags to control notification logging mode
    - **Test**: Config values read correctly from environment
    - **Subtasks**:
        - [x] Subtask 1.1: Add SMS_LOG_ONLY and EMAIL_LOG_ONLY to config files
            - **Objective**: Define config keys in services.php and mail.php
            - **Test**: config() returns correct boolean values
        - [x] Subtask 1.2: Update .env.example with flag examples
            - **Objective**: Document flags for developers
            - **Test**: .env.example contains the new flags

- [x] Task 2: Implement SMS logging mode
    - **Problem**: SMS service always attempts API calls
    - **Test**: When flag true, no API calls made, logged instead
    - **Subtasks**:
        - [x] Subtask 2.1: Modify KairosAfrikaSmsService::sendOtp()
            - **Objective**: Check flag and log SMS instead of sending
            - **Test**: Log entries appear with masked data, no HTTP requests
        - [x] Subtask 2.2: Modify KairosAfrikaSmsService::validateOtp() if needed
            - **Objective**: Handle logging mode for OTP validation
            - **Test**: Validation works in log mode

- [x] Task 3: Implement email logging mode
    - **Problem**: Email uses MAIL_MAILER but needs consistent flag control
    - **Test**: When flag true, emails logged not sent via SMTP
    - **Subtasks**:
        - [x] Subtask 3.1: Override mail driver when EMAIL_LOG_ONLY=true
            - **Objective**: Dynamically set mail driver to 'log'
            - **Test**: Emails appear in Laravel logs instead of being sent

- [x] Task 4: Test notification logging functionality
    - **Problem**: Ensure flags work correctly in practice
    - **Test**: Logs contain expected notification data
    - **Subtasks**:
        - [x] Subtask 4.1: Test SMS logging with real service call
            - **Objective**: Trigger OTP send with flag enabled
            - **Test**: Check logs for masked SMS data, no API calls
        - [x] Subtask 4.2: Test email logging with real send attempt
            - **Objective**: Send email with flag enabled
            - **Test**: Check logs for email content, no SMTP sends
        - [x] Subtask 4.3: Verify normal operation when flags disabled
            - **Objective**: Ensure production behavior unchanged
            - **Test**: Real SMS/email sent when flags false
