# Field Agent Verification Page Redesign (Option A) — Design Spec

**Date:** 2026-04-22
**Status:** Draft — pending review
**Topic:** Redesigning the Field Agent Verification page to improve visual layout, status clarity, and document presentation.

## 1. Problem Statement
The current Field Agent verification page (`/field-agent/verification`) suffers from a "broken" layout where all content—personal details and large document images—is stacked in a single vertical column. This makes the page difficult to scan and requires excessive scrolling. Additionally, the status of the application is not prominently displayed, and there's no chronological context (e.g., when it was submitted or reviewed).

## 2. Proposed Solution (Option A: Classic Dashboard Grid)
This design focuses on organizing information into logical grids and using modern dashboard components to provide a professional, "Verification Center" feel.

### 2.1 UI/UX Enhancements

#### Section 1: Status & Chronology Header
- **Visuals:** A full-width header with a breadcrumb trail.
- **Components:**
  - A prominent status badge with high-contrast colors (Emerald for `approved`, Amber for `pending`, Red for `rejected`).
  - A sub-text line showing "Submitted on [Date]" and, if reviewed, "Reviewed on [Date]".
  - A descriptive icon (e.g., `ShieldCheck` or `Clock`) next to the status.

#### Section 2: Two-Column Info Grid
- **Organization:** Personal and location data will be split into two cards or a single card with a clear two-column grid on desktop.
  - **Left Column (Identity):** Full Name, Email, Contact Number, Ghana Card Number.
  - **Right Column (Location):** Region, City, Location, Status.
- **Component:** Re-use the `InfoRow` component but ensure the container uses `grid-cols-1 md:grid-cols-2` with proper `gap-6`.

#### Section 3: Document Gallery (ID & Selfie)
- **Layout:** A `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3` layout for the three primary images.
- **Images:**
  - Ghana Card (Front)
  - Ghana Card (Back) — *Only if available*
  - Selfie
- **Feature:** "Click to Expand" using a simple Modal or `dialog` component to view the high-resolution original image without leaving the page.

### 2.2 Backend & Data Fixes
- **Controller Adjustment:** Modify `FieldAgentVerificationController` to use the configured storage disk (S3/R2 vs Local) instead of hardcoding `public` to ensure images load in both development and production environments.
- **Data Enrichment:** Ensure `reviewed_at` and `created_at` are passed to the frontend for the chronology header.

## 3. Implementation Details

### 3.1 Components to Use
- **Inertia.js:** For seamless page transitions.
- **Tailwind CSS:** For the grid system and responsive spacing.
- **Shadcn/UI:** `Card`, `Badge`, `Button`, `Dialog` (for image expansion).
- **Lucide-React:** Icons for visual cues.

### 3.2 Error Handling
- **Empty State:** Maintain the current "No verification record" state for users without an application.
- **Missing Images:** Show a "Document not provided" placeholder if `ghana_card_back` is missing.
- **Broken Images:** Implement an `onError` handler on images to show a fallback icon.

## 4. Success Criteria
- [ ] No more single-column vertical stacking on desktop.
- [ ] Application status is the first thing the user sees.
- [ ] Images are organized in a horizontal grid on desktop.
- [ ] All data points from the `field_agent_applications` table are visible and legible.
