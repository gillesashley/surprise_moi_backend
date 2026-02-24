# Marketer Pages Not Showing - Ticket 007

## 1. Problem Statement

Marketer users are unable to access their dashboard pages. When navigating to `/marketer/dashboard` or other marketer routes, the pages either:

- Show a blank/error page
- Redirect incorrectly
- Fail to render properly

### Impact

- Marketers cannot view their targets, earnings, or payouts
- Unable to track quarterly performance metrics
- Complete loss of functionality for marketer role

## 2. Proposed Solution

### Root Cause Analysis

The investigation revealed that the breadcrumb in the marketer dashboard page has an **incorrect `href` property** that points to the **API endpoint** instead of the **web route**:

**Current (broken):**

```typescript
href: '/api/v1/marketer/dashboard'; // ❌ API endpoint
```

**Should be:**

```typescript
href: '/marketer/dashboard'; // ✓ Web route
```

This causes Inertia.js navigation issues because:

1. Breadcrumbs are clickable navigation elements
2. Clicking the breadcrumb tries to navigate to the API endpoint
3. API endpoints return JSON, not Inertia page data
4. This breaks the SPA navigation flow

### Files to Fix

1. `/resources/js/pages/marketer/dashboard.tsx` - Line 18: Fix breadcrumb href
2. `/resources/js/pages/marketer/dashboard.tsx` - Lines 95-107: Add default prop values to prevent TypeError
3. Verify other marketer pages (targets.tsx, earnings.tsx, payouts.tsx) don't have similar issues
4. Check if any imports or component props are affected

### Technical Changes Required

**Task 1: Fix Dashboard Breadcrumb**

- Change breadcrumb href from API endpoint to web route
- Ensure proper Inertia navigation works
- Test the page renders correctly

**Task 4: Fix TypeError from Undefined Props**

- Add default values for all props (stats, active_targets, recent_sign_on_bonuses)
- Prevent "Cannot read properties of undefined (reading 'length')" error
- Ensure dashboard renders correctly when data is unavailable

**Task 2: Verify Other Pages**

- Check targets.tsx for similar breadcrumb issues
- Check earnings.tsx for similar breadcrumb issues
- Check payouts.tsx for similar breadcrumb issues

**Task 3: Integration Testing**

- Test navigation between all marketer pages
- Verify sidebar links work correctly
- Test breadcrumb functionality

## 3. Acceptance Criteria

- [x] Marketer dashboard loads and displays correctly at `/marketer/dashboard`
- [x] Breadcrumb navigation works without errors
- [x] All marketer pages accessible: dashboard, targets, earnings, payouts
- [x] Sidebar navigation links function properly
- [x] No API endpoint URLs in breadcrumb hrefs
- [x] Inertia.js handles page transitions smoothly

## 4. Technical Considerations

### Architecture

- Frontend: React + Inertia.js + TypeScript
- Backend: Laravel with web routes at `/marketer/*`
- API routes at `/api/v1/marketer/*` (separate from web routes)

### Dependencies

- No blocking dependencies
- Related to existing marketer functionality in routes/web.php and routes/api.php

### Testing Strategy

1. Manual browser testing of all marketer routes
2. Verify breadcrumb click navigation
3. Test sidebar link navigation
4. Check page data loads correctly from API

## 5. Subtask Checklist

### Task 1: Fix Dashboard Breadcrumb

- **Problem**: Breadcrumb href points to API endpoint instead of web route
- **Test**: Navigate to /marketer/dashboard and click breadcrumb
- **Subtasks**:
    - [x] Subtask 1.1: Fix href in dashboard.tsx breadcrumb array
        - **Objective**: Change href from '/api/v1/marketer/dashboard' to '/marketer/dashboard'
        - **Test**: Click breadcrumb and verify page reloads correctly
    - [x] Subtask 1.2: Verify no other broken hrefs in dashboard.tsx
        - **Objective**: Scan file for any other incorrect API URLs
        - **Test**: Review all hrefs and links in the file

### Task 2: Verify Other Marketer Pages

- **Problem**: Other pages may have similar breadcrumb issues
- **Test**: Review and test all marketer page files
- **Subtasks**:
    - [x] Subtask 2.1: Check and fix targets.tsx
        - **Objective**: Verify breadcrumb href is correct
        - **Test**: Navigate to /marketer/targets and test breadcrumb
    - [x] Subtask 2.2: Check and fix earnings.tsx
        - **Objective**: Verify breadcrumb href is correct
        - **Test**: Navigate to /marketer/earnings and test breadcrumb
    - [x] Subtask 2.3: Check and fix payouts.tsx
        - **Objective**: Verify breadcrumb href is correct
        - **Test**: Navigate to /marketer/payouts and test breadcrumb

### Task 3: Integration Testing

- **Problem**: Ensure all navigation works together
- **Test**: Complete user journey through marketer pages
- **Subtasks**:
    - [x] Subtask 3.1: Test sidebar navigation
        - **Objective**: Click all sidebar links for marketer role
        - **Test**: Verify each page loads correctly
    - [x] Subtask 3.2: Test breadcrumb navigation
        - **Objective**: Click breadcrumbs on each page
        - **Test**: Verify navigation works without errors
    - [x] Subtask 3.3: Test page data loading
        - **Objective**: Verify API calls still fetch data correctly
        - **Test**: Check that stats, targets, earnings load from API

### Task 4: Fix TypeError from Undefined Props

- **Problem**: Component throws TypeError when props are undefined
- **Test**: Load dashboard without providing props
- **Subtasks**:
    - [x] Subtask 4.1: Add default values for stats prop
        - **Objective**: Provide fallback values for all stats properties
        - **Test**: Verify stats display 0 values when no data
    - [x] Subtask 4.2: Add default values for active_targets and recent_sign_on_bonuses
        - **Objective**: Set default to empty arrays to prevent length errors
        - **Test**: Verify no TypeError occurs when data is unavailable

## 6. Implementation Notes

### Files Modified

1. `/resources/js/pages/marketer/dashboard.tsx` - Line 18
    - Change: `href: '/api/v1/marketer/dashboard'` → `href: '/marketer/dashboard'`

2. `/resources/js/pages/marketer/dashboard.tsx` - Lines 95-107
    - Added default values for all props:
        - stats: Default object with all properties set to 0 (including current_quarter and current_year)
        - active_targets: Default to empty array
        - recent_sign_on_bonuses: Default to empty array

### Verification Steps

1. Log in as marketer user
2. Navigate to /marketer/dashboard
3. Click breadcrumb "Dashboard" link
4. Verify page reloads without errors
5. Test sidebar links to other pages
6. Test breadcrumbs on targets, earnings, payouts pages

## 7. Related Code References

- **Routes**: `routes/web.php` lines 130-140 (marketer web routes)
- **Controller**: `app/Http/Controllers/MarketerDashboardController.php`
- **Pages**: `resources/js/pages/marketer/*.tsx`
- **Sidebar**: `resources/js/components/app-sidebar.tsx` lines 149-171
- **Middleware**: `app/Http/Middleware/EnsureDashboardAccess.php` lines 49-52
