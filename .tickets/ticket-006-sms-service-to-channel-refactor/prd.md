# Ticket-006: SMS Service to Notification Channel Refactor

## 1. Problem Statement

The current `KairosAfrikaSmsService` is used via direct dependency injection throughout the codebase. While functional, this approach:

- Tightly couples SMS functionality to specific controllers
- Makes it difficult to switch SMS providers in the future
- Cannot leverage Laravel's notification system for multi-channel notifications
- Requires manual phone number formatting in every controller
- Lacks a standardized way to queue SMS messages

**Business Impact:**

- Reduced flexibility in changing SMS providers
- Code duplication across multiple controllers
- Inability to easily add SMS to existing notifications

## 2. Proposed Solution

Refactor the SMS service into a proper Laravel Notification Channel architecture while maintaining 100% backward compatibility.

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│  Existing Usage (Still Works)                               │
│  $smsService->sendOtp($phone)                               │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  New Usage (Now Available)                                  │
│  $user->notify(new OtpNotification($code))                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
         ┌─────────────┴─────────────┐
         │                           │
         ▼                           ▼
┌─────────────────┐       ┌─────────────────────┐
│ SmsChannel      │       │ SmsProviderInterface│
│ - send()        │       │ - send()            │
│ - extract phone │       │ - sendOtp()         │
└────────┬────────┘       └──────────┬──────────┘
         │                           │
         │    ┌──────────────────────┘
         │    │
         ▼    ▼
┌──────────────────────────────┐
│ KairosAfrikaSmsService       │
│ (implements interface)       │
│ - All existing methods       │
│ + send() for channel         │
└──────────────────────────────┘
```

### Key Implementation Decisions

1. **Interface-based design**: Use `SmsProviderInterface` to allow future provider swaps
2. **Channel pattern**: Implement proper Laravel Notification Channel
3. **Backward compatibility**: Keep all existing methods and signatures unchanged
4. **Message DTO**: Create `SmsMessage` class for structured SMS data
5. **Reusable trait**: Add `HasSmsChannel` trait for easy notification adoption

## 3. Acceptance Criteria

- [x] `KairosAfrikaSmsService` implements `SmsProviderInterface`
- [x] New `SmsChannel` class exists and integrates with Laravel Notifications
- [x] `SmsMessage` DTO class provides fluent API
- [x] All existing `sendOtp()` calls continue working without modification
- [x] All existing `validateOtp()` calls continue working without modification
- [x] New notifications can use SMS channel via `$user->notify()`
- [x] Interface is bound in service provider for DI
- [x] Example `OtpNotification` class demonstrates usage
- [x] All tests pass with new implementation

## 4. Technical Considerations

### Implementation Constraints

- Must maintain exact same return types: `array{success: bool, message: string, data: array|null}`
- Must not break existing controller code
- Must work with existing configuration in `config/services.php`

### Performance Requirements

- No performance degradation for existing direct service calls
- Notification channel should support queuing via `ShouldQueue`

### Security Considerations

- Preserve existing phone number masking in logs
- Maintain SSL verification bypass only in local environment
- Keep API keys in configuration (no hardcoding)

### Integration Points

- `AppServiceProvider`: Interface binding
- Existing controllers: Continue working unchanged
- `config/services.php`: No changes required

## 5. Dependencies

**None** - This is a self-contained refactor that only adds new capabilities.

**Future dependencies** (out of scope):

- Other tickets may adopt the new notification pattern
- Future SMS providers can implement `SmsProviderInterface`

## 6. Subtask Checklist

### Task 1: Create Foundation (Contracts & Messages)

**Problem**: Need contracts and message structures for the SMS channel architecture

**Test**:

- Files created in correct locations
- Interfaces have required methods
- Message class has fluent API

**Subtasks**:

- [x] Subtask 1.1: Create `app/Contracts/Sms/SmsProviderInterface.php`
    - **Objective**: Define contract that all SMS providers must implement
    - **Test**: Interface has `send()`, `sendOtp()`, and `validateOtp()` method signatures

- [x] Subtask 1.2: Create `app/Notifications/Messages/SmsMessage.php`
    - **Objective**: Create DTO for SMS messages with fluent API
    - **Test**: Class has `to()`, `content()`, `from()` methods that return `$this`

- [x] Subtask 1.3: Create directory structure
    - **Objective**: Ensure `app/Contracts/Sms/` and `app/Notifications/Messages/` directories exist
    - **Test**: Directories created with proper permissions

### Task 2: Create Laravel Notification Channel

**Problem**: Need to implement Laravel's notification channel pattern for SMS

**Test**:

- Channel class follows Laravel convention
- Can extract SMS data from notifications
- Integrates with notification system

**Subtasks**:

- [x] Subtask 2.1: Create `app/Channels/SmsChannel.php`
    - **Objective**: Implement notification channel that routes SMS through provider
    - **Test**: Channel has `send()` method accepting `$notifiable` and `$notification`

- [x] Subtask 2.2: Create `app/Notifications/Concerns/HasSmsChannel.php`
    - **Objective**: Create reusable trait for notifications that support SMS
    - **Test**: Trait provides `toSms()` method with default implementation

- [x] Subtask 2.3: Create `app/Notifications/Sms/OtpNotification.php` (example)
    - **Objective**: Demonstrate new notification pattern
    - **Test**: Notification uses `SmsChannel` and implements `toSms()`

### Task 3: Refactor KairosAfrikaSmsService

**Problem**: Add interface implementation while maintaining backward compatibility

**Test**:

- Service implements `SmsProviderInterface`
- All existing methods still work
- New `send()` method added for channel

**Subtasks**:

- [x] Subtask 3.1: Add interface to KairosAfrikaSmsService
    - **Objective**: Make service implement `SmsProviderInterface`
    - **Test**: `class KairosAfrikaSmsService implements SmsProviderInterface`

- [x] Subtask 3.2: Add `send()` method for channel support
    - **Objective**: Add generic send method that channel can use
    - **Test**: Method signature matches interface: `send(string $to, string $message): array`

- [x] Subtask 3.3: Verify all existing methods unchanged
    - **Objective**: Ensure `sendOtp()` and `validateOtp()` remain identical
    - **Test**: Existing code calling these methods works without modification

### Task 4: Configure Service Provider

**Problem**: Need to bind interface to implementation for dependency injection

**Test**:

- Interface resolves to service via DI
- Can inject interface in constructors
- Configuration-driven if needed

**Subtasks**:

- [x] Subtask 4.1: Add binding to `AppServiceProvider`
    - **Objective**: Register interface-to-implementation binding
    - **Test**: `App::bind(SmsProviderInterface::class, KairosAfrikaSmsService::class)`

- [x] Subtask 4.2: Verify DI works
    - **Objective**: Test that injecting interface resolves to concrete service
    - **Test**: Create simple test showing interface injection works

### Task 5: Testing & Verification

**Problem**: Need to ensure everything works correctly

**Test**:

- All existing functionality preserved
- New channel functionality works
- No regressions introduced

**Subtasks**:

- [x] Subtask 5.1: Verify backward compatibility
    - **Objective**: Test that existing service calls work
    - **Test**: Run existing tests or manual test of OTP sending

- [x] Subtask 5.2: Test new notification channel
    - **Objective**: Verify notification pattern works
    - **Test**: Create test notification and verify SMS channel receives it

- [x] Subtask 5.3: Code review and cleanup
    - **Objective**: Review all changes for quality
    - **Test**: All files have proper namespace, imports, and formatting

## 7. File Structure

```
app/
├── Channels/
│   └── SmsChannel.php                    # NEW
├── Contracts/
│   └── Sms/
│       └── SmsProviderInterface.php      # NEW
├── Notifications/
│   ├── Messages/
│   │   └── SmsMessage.php                # NEW
│   ├── Concerns/
│   │   └── HasSmsChannel.php             # NEW
│   └── Sms/
│       └── OtpNotification.php           # NEW (example)
└── Services/
    └── KairosAfrikaSmsService.php        # MODIFIED
```

## 8. Usage Examples

### Existing Usage (Still Works)

```php
class AuthController extends Controller
{
    public function __construct(
        protected KairosAfrikaSmsService $smsService
    ) {}

    public function register(Request $request)
    {
        // Still works exactly as before
        $result = $this->smsService->sendOtp($request->phone);
        return response()->json($result);
    }
}
```

### New Usage (Now Available)

```php
class AuthController extends Controller
{
    public function register(Request $request)
    {
        // New notification-based approach
        $user->notify(new OtpNotification($otpCode));
        return response()->json(['message' => 'OTP sent']);
    }
}

// The notification class
class OtpNotification extends Notification
{
    use HasSmsChannel;

    public function __construct(private string $code) {}

    public function via($notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms($notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->to($notifiable->phone)
            ->content("Your code is {$this->code}");
    }
}
```

## 9. Risk Assessment

**Low Risk** - This refactor is purely additive:

- All existing code paths remain unchanged
- New code is isolated to new files (except interface addition)
- Can be gradually adopted
- Easy to revert if needed

## 10. Post-Implementation Notes

After this refactor:

- Future tickets can adopt the notification pattern
- Additional SMS providers can be added by implementing `SmsProviderInterface`
- SMS can be easily added to existing notifications
- Service remains directly injectable for specialized use cases
