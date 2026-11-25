# Branding, Home Page, and Dashboard Plan

## Goal

Replace every user-facing Laravel starter-kit brand element with the supplied IEEE AIUB Student Branch identity, turn the public home page into a focused Members Orientation 2026 entry point, and replace the dashboard placeholders with useful attendance information and a lightweight chart.

This document is a plan only. No application code, image assets, configuration, or tests are changed as part of this planning step.

## Current State

- The shared `app-logo-icon` component, the browser favicons, and the public welcome page still use Laravel artwork.
- The sidebar and alternate header layout still expose Laravel starter-kit repository and documentation links.
- The public home page is the stock Laravel welcome page.
- The authenticated dashboard contains four placeholder panels and receives no data.
- The attendee table already has the fields needed for useful dashboard reporting: `received` and `received_at`.
- The supplied blue and white IEEE AIUB Student Branch PNGs have transparent backgrounds. The source canvases contain substantial transparent padding, so directly displaying them would waste bytes and make sizing inconsistent.
- The frontend has Tailwind CSS, Livewire, and Flux UI, but no charting package.

## Scope

### In scope

1. Replace user-visible Laravel artwork and starter-kit references across the public page, authenticated layouts, auth layouts, favicons, and touch icon.
2. Add optimized, tightly cropped blue and white variants of the supplied logo under `public/images/brand/`.
3. Rebuild the public home page around the actual product: IEEE AIUB Student Branch Members Orientation 2026 check-in.
4. Replace the dashboard placeholders with attendance totals and a received-versus-pending chart.
5. Add focused feature coverage for the home page, dashboard data, and removal of user-facing Laravel starter-kit content.
6. Verify responsive layout, dark mode, accessibility, frontend build output, browser errors, and request/query performance.

### Out of scope

- No new charting, image-processing, icon, or UI dependency.
- No database migration, historical attendance chart, scan audit table, polling, or real-time dashboard updates.
- No changes to scanner behavior, attendee management, email delivery, authentication flow, or authorization rules.
- Framework namespaces, Composer metadata, internal bootstrap comments, and other non-user-facing Laravel references remain untouched.

## Design Direction

The visual subject is an IEEE student event operations portal for staff and administrators. Its single public-page job is to identify the event and get authorized users into the check-in system.

### Brand tokens

- IEEE blue: `#00629B` (primary actions, chart received segment, links, and restrained accents).
- Deep navy: `#001F33` (light-mode headings and high-contrast surfaces).
- Signal cyan: `#00A6D6` (small focus/accent details only).
- Paper: `#F7FAFC` (light background).
- Ink: `#111827` (body text).
- Night: `#07131C` (dark background).
- White: `#FFFFFF` (dark-mode logo and text).
- Keep the installed Instrument Sans stack; do not add web-font requests for a branding-only change.

### Public home page

Use a restrained, event-specific landing page rather than a generic marketing layout:

```text
+-------------------------------------------------------+
| IEEE AIUB Student Branch logo              Sign in   |
+-------------------------------------------------------+
| Members Orientation 2026                              |
| Fast, secure member check-in for event staff.         |
| [Open dashboard / Staff sign in]                      |
|                                                       |
| 26 November 2026 | 3:30 PM | Multipurpose Hall       |
+-------------------------------------------------------+
```

- Show the blue logo on light surfaces and the white logo on dark surfaces.
- Use one clear action: guests see “Staff sign in”; authenticated users see “Open dashboard”.
- Keep the event date, time, and venue already established by the invitation email.
- Remove Laravel ecosystem copy, documentation cards, cloud promotion, and decorative Laravel illustration.
- Keep motion optional and minimal; the page must remain complete with reduced motion enabled.

### Authenticated brand treatment

- Rework the shared logo component so sidebar/header consumers inherit one consistent responsive wordmark treatment.
- Use a compact wordmark in wide navigation and an appropriately cropped brand mark where only a square icon fits.
- Update the simple, card, and split auth layouts through the same shared brand asset/component instead of repeating image markup.
- Preserve accessible alternative text and the existing screen-reader application name.

### Dashboard

Replace the four placeholders with:

1. Total attendees.
2. Received attendees.
3. Pending attendees.
4. An “Attendance progress” card containing a received-versus-pending donut and the exact counts/percentage in text.

The donut should use a native CSS `conic-gradient` or a small inline SVG, whichever produces the clearest accessible markup during implementation. It must not require JavaScript. The exact values must also be present as text so color is never the only way to read the result. The empty state should display `0%` without division-by-zero errors.

## Implementation Tasks

### Task 1: Prepare the brand assets

**Files likely touched:**

- `public/images/brand/ieee-aiub-blue.png` (new)
- `public/images/brand/ieee-aiub-white.png` (new)
- `public/favicon.svg`
- `public/favicon.ico`
- `public/apple-touch-icon.png`

**Work:**

- Copy the two supplied source images into the project only during implementation.
- Losslessly crop transparent padding and resize each output for its actual display density; preserve transparency and aspect ratio.
- Create non-Laravel favicon and touch-icon assets derived from the supplied IEEE AIUB artwork without redrawing or altering the wordmark.
- Confirm all assets remain sharp at their rendered sizes and do not cause layout shift by declaring dimensions/aspect ratio.

**Acceptance criteria:**

- No browser or application surface displays the Laravel logo.
- Blue and white variants remain legible on light and dark backgrounds.
- Brand images have useful intrinsic dimensions and materially less transparent padding than the supplied source files.

**Verification:**

- Inspect favicon, touch icon, sidebar, mobile navigation, and each auth layout in light and dark mode.
- Confirm image responses are cached as static public assets and no oversized source file is downloaded unnecessarily.

### Task 2: Centralize the shared application branding

**Files likely touched:**

- `resources/views/components/app-logo-icon.blade.php`
- `resources/views/components/app-logo.blade.php`
- `resources/views/layouts/auth/simple.blade.php`
- `resources/views/layouts/auth/card.blade.php`
- `resources/views/layouts/auth/split.blade.php`
- `.env.example`

**Work:**

- Replace the inline Laravel SVG in the shared icon component with the new project brand treatment.
- Make the existing `app-logo` component the single source used by app and auth layouts, with only the minimum size/layout variants already required by those consumers.
- Change the example/default public application name from Laravel to `IEEE AIUB Student Branch` where appropriate; do not touch the active environment file unless explicitly requested during implementation.

**Acceptance criteria:**

- All existing logo consumers render the IEEE AIUB identity without duplicated brand markup.
- Logo links, dark mode, mobile collapse behavior, and screen-reader naming continue to work.
- No user-visible page falls back to the word “Laravel” as the application name.

**Verification:**

- Render the sidebar, header variant, and three auth layouts at mobile and desktop widths.
- Search rendered HTML for unintended user-facing Laravel logo/name references.

### Task 3: Replace the stock public home page

**Files likely touched:**

- `resources/views/welcome.blade.php`
- `tests/Feature/ExampleTest.php` or a focused home-page feature test

**Work:**

- Replace the generated stock page and embedded compiled CSS with normal Blade markup using the existing Vite/Tailwind asset pipeline and shared head partial.
- Add the event-specific content and conditional guest/authenticated call to action described above.
- Remove Laravel documentation, repository, ecosystem, and cloud links from the page.

**Acceptance criteria:**

- The home page identifies IEEE AIUB Student Branch and Members Orientation 2026.
- Guests can reach sign-in and authenticated users can reach the dashboard.
- Date, time, and venue are readable on mobile and desktop.
- The page has no user-facing Laravel starter-kit copy or artwork.

**Verification:**

- Add feature assertions for brand/event copy, guest CTA, authenticated CTA, and absence of Laravel documentation links.
- Run the frontend production build and manually check responsive, dark, focus, and reduced-motion states.

### Task 4: Remove starter-kit links from authenticated navigation

**Files likely touched:**

- `resources/views/layouts/app/sidebar.blade.php`
- `resources/views/layouts/app/header.blade.php`

**Work:**

- Remove the Laravel starter-kit repository and documentation items from desktop and mobile navigation.
- Do not replace them with speculative links. Existing product navigation already covers the required destinations.

**Acceptance criteria:**

- No authenticated navigation variant links to Laravel documentation or the starter-kit repository.
- Removing the links leaves balanced spacing and does not affect the user menu or primary navigation.

**Verification:**

- Check sidebar and header variants at desktop and mobile widths.
- Confirm keyboard navigation skips directly through useful product actions.

### Task 5: Add the lightweight attendance dashboard

**Files likely touched:**

- `app/Http/Controllers/DashboardController.php` (new, invokable)
- `routes/web.php`
- `resources/views/dashboard.blade.php`
- `tests/Feature/DashboardTest.php`

**Work:**

- Replace the static dashboard view route with an invokable controller that returns one aggregate query over attendees.
- Derive total, received, pending, and percentage from that single result; do not load attendee rows.
- Render metric cards with existing Flux/Blade/Tailwind patterns.
- Render the received-versus-pending donut without a chart library or client-side hydration.
- Keep the existing `auth` and `verified` middleware behavior unchanged.

**Acceptance criteria:**

- Counts and percentage are correct for mixed, all-received, none-received, and empty datasets.
- Guests remain redirected and verified authenticated users can view the dashboard.
- The chart has a text equivalent and is understandable without color.
- Dashboard rendering performs one bounded aggregate attendee query, transfers no attendee records, and loads no new JavaScript package.

**Verification:**

- Extend `DashboardTest` with the four data states and authorization coverage.
- Run the focused dashboard tests and then the relevant feature suite.
- Inspect query count/timing and confirm the page introduces no N+1 queries.

### Task 6: End-to-end visual and performance verification

**Files likely touched:** None unless verification finds a defect.

**Work:**

- Build production assets.
- Test home, login/auth layouts, dashboard, sidebar, mobile menu, favicon, and dark mode in a real browser.
- Check console/network failures, accessibility basics, response issues, and layout stability.
- Check dashboard request timing and query diagnostics through Lerd after loading the page with real traffic.

**Acceptance criteria:**

- Focused tests and the frontend build pass.
- No broken images, missing Vite assets, browser errors, failed requests, N+1 queries, or new slow-route finding appears.
- The home page and dashboard work at phone, tablet, and desktop widths.
- Visible focus, alternative text, contrast, and reduced-motion behavior meet the existing accessibility baseline.

## Dependency Order

```text
Optimized brand assets
        |
        +--> Shared branding --> Auth/app layouts
        |
        +--> Public home page

Attendee aggregate query --> Dashboard cards/chart

All UI work --> Browser, accessibility, and performance verification
```

Tasks 3 and 5 can be implemented independently after Task 1, but the final browser/performance pass must wait for all tasks.

## Performance Budget

- Add no frontend dependency and no dashboard JavaScript.
- Use one aggregate attendee query per dashboard request; select no attendee rows or personal fields.
- Keep logo variants tightly cropped and sized for their actual contexts.
- Use static public asset caching and explicit image dimensions to avoid repeated transfers and layout shift.
- Do not add caching for attendance totals initially: the aggregate is cheap at the current scale, while stale check-in data would make the operational dashboard misleading. Reconsider only if measured route timing shows a problem.

## Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| The supplied square canvases make the wide wordmark appear tiny | Medium | Produce tightly cropped display assets before wiring any view. |
| A full wordmark is unreadable in favicon/icon slots | Medium | Derive the smallest faithful crop from the supplied artwork and verify at 16–48 px; do not invent a new logo. |
| Chart math fails with no attendees | Medium | Define the empty state as zero received, zero pending, and `0%`; cover it in a feature test. |
| Dashboard totals become stale if cached | Medium | Do not cache until profiling proves the aggregate query needs it. |
| Brand replacement misses a starter-kit surface | Low | Search both source and rendered HTML for user-facing Laravel artwork, repository, documentation, and cloud references. |
| Existing auth layout changes regress unrelated authentication | Medium | Reuse the shared brand component and run focused auth/dashboard feature tests. |

## Definition of Done

- Every user-visible Laravel logo and starter-kit link has been replaced or removed.
- The public page is a concise IEEE AIUB Members Orientation 2026 entry point.
- The dashboard shows accurate total, received, and pending attendance with an accessible lightweight chart.
- No new dependency, migration, polling, or speculative feature has been introduced.
- Focused tests, frontend build, responsive browser checks, accessibility checks, and Lerd request/query diagnostics pass.

