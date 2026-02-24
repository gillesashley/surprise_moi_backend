# Ticket-012: Client Error Log Dashboard with Pulse Analytics

## 1. Problem Statement

Admins currently have no visibility into client-side errors occurring in the web/mobile applications. We need a dedicated admin dashboard page to:
- View all client errors in a searchable/filterable list
- See detailed error information in a side panel
- Analyze client error trends and metrics via Laravel Pulse
- Track OS, device types, screen resolution, and network conditions

## 2. Proposed Solution

Create a new admin dashboard page (`/dashboard/client-errors`) that displays:
1. **Error List View**: Paginated list of client errors with key info (error type, timestamp, user)
2. **Detail Panel**: Slide-out/drawer showing full error details, stack trace, device info
3. **Pulse Analytics Dashboard**: Charts showing:
   - Errors by OS (Windows, macOS, iOS, Android, etc.)
   - Errors by device type (desktop, tablet, mobile)
   - Errors by browser
   - Errors by screen resolution
   - Errors by network type (4G, 5G, WiFi, etc.)
   - Error frequency over time

**Architecture**:
- Backend: Add route + controller method to AdminDashboardController
- Frontend: New React page at `resources/js/pages/admin/dashboard/client-errors/index.tsx`
- Pulse: Create custom Pulse recorders for client error metrics

## 3. Acceptance Criteria

### Functional Requirements
- [ ] Admin can access `/dashboard/client-errors` route
- [ ] Error list shows: error message, timestamp, user (if logged in), device type
- [ ] Clicking an error opens detail panel on right side
- [ ] Detail panel shows: full error message, stack trace, device info (OS, browser, resolution), network info, payload
- [ ] Pagination works (20 errors per page)
- [ ] List is sorted by most recent first

### Analytics Requirements (Pulse)
- [ ] Error count by OS displayed
- [ ] Error count by device type displayed
- [ ] Error count by browser displayed
- [ ] Error count by screen resolution displayed
- [ ] Error count by network type displayed
- [ ] Error trend over time (daily/weekly) displayed

### Technical Requirements
- [ ] Follows existing admin dashboard patterns (like jobs/index.tsx)
- [ ] Responsive design (works on desktop and tablet)
- [ ] Proper TypeScript types
- [ ] Passes lint, format, and type checks

## 4. Technical Considerations

### Existing ClientError Model
The `ClientError` model already exists with:
- `user_id` - nullable foreign key to users
- `device_info` - JSON field containing: OS, device, browser, resolution, network
- `occurred_at` - timestamp
- `error` - error message/text
- `payload` - additional context JSON
- `ip_address`
- `user_agent`

### Pulse Integration
- Create custom Pulse recorders to aggregate client error metrics
- Use Pulse's Livewire components for the analytics cards
- Alternatively, use server-side aggregation with Chart.js/recharts

### Device Info Schema (from frontend)
```json
{
  "os": "iOS 17.0",
  "os_version": "17.0",
  "device": "iPhone 15 Pro",
  "device_type": "mobile",
  "browser": "Safari",
  "browser_version": "17.0",
  "screen_resolution": "1179x2556",
  "network": "5G",
  "language": "en-US",
  "timezone": "America/New_York"
}
```

## 5. Dependencies

- Ticket-005: Admin dashboard jobs view (for patterns and Pulse integration reference)
- Laravel Pulse package already installed via composer

## 6. Subtask Checklist

- [x] Task 1: Add client-errors route and controller method
  - **Problem**: Need endpoint to fetch client errors with pagination
  - **Test**: Visit `/dashboard/client-errors` returns 200
  - **Subtasks**:
    - [x] Subtask 1.1: Add route in web.php for client-errors
      - **Objective**: Add GET route `/dashboard/client-errors`
      - **Test**: Route exists and points to controller method
    - [x] Subtask 1.2: Add clientErrors() method to AdminDashboardController
      - **Objective**: Return paginated client errors with statistics
      - **Test**: Returns Inertia response with errors and stats

- [x] Task 2: Create Pulse analytics for client errors
  - **Problem**: Need to track and display error metrics by OS, device, etc.
  - **Test**: Pulse shows error metrics
  - **Subtasks**:
    - [x] Subtask 2.1: Create Pulse config and custom recorders
      - **Objective**: Track client error metrics in Pulse
      - **Test**: Metrics stored in pulse_entries table
    - [x] Subtask 2.2: Add analytics data to controller response
      - **Objective**: Pass aggregated stats to frontend
      - **Test**: Stats appear in page props

- [x] Task 3: Create frontend error list page
  - **Problem**: Need admin dashboard page to display errors
  - **Test**: Page renders with error list
  - **Subtasks**:
    - [x] Subtask 3.1: Create page component at admin/dashboard/client-errors/index.tsx
      - **Objective**: Build error list UI following jobs page pattern
      - **Test**: List displays with pagination
    - [x] Subtask 3.2: Add detail drawer/sidebar
      - **Objective**: Show full error details when clicked
      - **Test**: Clicking error shows all details on right

- [x] Task 4: Add analytics cards to page
  - **Problem**: Need to display Pulse metrics on the page
  - **Test**: Charts/cards show error breakdowns
  - **Subtasks**:
    - [x] Subtask 4.1: Add statistics cards for OS, device, browser
      - **Objective**: Display error counts by category
      - **Test**: Cards render with data
    - [x] Subtask 4.2: Add trend chart
      - **Objective**: Show error frequency over time
      - **Test**: Chart displays with time series data

- [x] Task 5: Verify and test
  - **Problem**: Ensure everything works correctly
  - **Test**: All acceptance criteria met
  - **Subtasks**:
    - [x] Subtask 5.1: Run full quality check (lint, format, types, tests)
      - **Objective**: Code passes all checks
      - **Test**: `pnpm run lint && pnpm run format:check && pnpm run types && ./vendor/bin/pint --test && php artisan test`
    - [x] Subtask 5.2: Manual verification of page
      - **Objective**: Page loads and displays correctly
      - **Test**: Visit page in browser, verify UI
