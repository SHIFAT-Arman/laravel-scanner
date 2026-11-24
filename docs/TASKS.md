# Implementation Tasks: QR Receipt App

Source: [PLAN.md](./PLAN.md)

## Rules for the agent

- Work in order unless a dependency is explicitly satisfied.
- Keep each task small; do not combine unrelated features.
- Preserve the existing Fortify/login edits.
- Use Laravel migrations, models, policies, Livewire pages, and Pest feature tests following existing project conventions.
- Do not add scan-history, QR revocation, event restrictions, offline mode, or new dependencies unless explicitly approved.
- After each task, run its focused tests and update this checklist only when the acceptance criteria pass.

## Phase 1: Database and access foundation

### Task 1: Verify the Supabase runtime connection

**Depends on:** None

**Do:** Confirm Laravel connects to the imported Supabase PostgreSQL database and can read `attendees`. Confirm the production connection uses server-side credentials only; do not expose a Supabase service-role key to JavaScript.

**Acceptance criteria:**

- [x] Laravel reads the existing `attendees` table.
- [x] `attendees.uuid` is unique and usable as the lookup key.
- [x] No secret values are committed or rendered to the browser.

**Verification:** Run a read-only schema/query check and the existing test suite.

**Likely files:** `.env`/deployment secrets only if needed, `config/database.php` only if needed.

### Task 2: Add receipt fields

**Depends on:** Task 1

**Do:** Add `received` with a false default and nullable `received_at` to `attendees`. Use a reversible migration compatible with the connected PostgreSQL database. Do not reset or rewrite existing attendee rows.

**Acceptance criteria:**

- [x] Existing attendees remain unchanged except for the new default receipt fields.
- [x] Rollback removes only the new fields.
- [x] Model casts receipt values correctly.

**Verification:** Run migration/rollback on a safe database and test a fresh migration path.

**Likely files:** `database/migrations/*`, `app/Models/Attendee.php`, model factory/test fixture if needed.

### Task 3: Implement roles and staff-only access

**Depends on:** Task 2

**Do:** Add `admin`/`staff` role handling, authorization middleware/policies, and an active/inactive account guard if account deactivation is implemented. Disable public registration. Keep password reset and email verification behavior consistent with the existing Fortify setup.

**Acceptance criteria:**

- [x] Unauthenticated users cannot access application pages.
- [x] Staff cannot access admin routes/actions.
- [x] Multiple admins are supported.
- [x] Public registration is unavailable.

**Verification:** Add focused authorization tests for unauthenticated, staff, and admin users.

**Likely files:** `app/Models/User.php`, `config/fortify.php`, `routes/web.php`, policies/middleware, auth tests.

### Checkpoint: Foundation

- [ ] Supabase connection and migrations work.
- [ ] Existing authentication tests pass.
- [ ] Role access tests pass.

## Phase 2: Core staff scan flow

### Task 4: Add an initial-admin bootstrap path

**Depends on:** Task 3

**Do:** Provide one safe way to create the first Admin without public registration, preferably a guarded seeder/Artisan command using environment-provided credentials. Document that credentials must be changed after first login.

**Acceptance criteria:**

- [x] A first admin can be created without opening registration.
- [x] Re-running the bootstrap does not create duplicates.
- [x] No default production password is committed.

**Verification:** Run the bootstrap in a test database and verify idempotency.

**Likely files:** `database/seeders/DatabaseSeeder.php` or `app/Console/Commands/*`, auth tests.

### Task 4a: Let Admin add Staff accounts

**Depends on:** Tasks 3–4

**Do:** Add an Admin-only Staff page that lists Staff accounts and creates a verified Staff account with a temporary password. Keep the role server-controlled and keep public registration disabled.

**Acceptance criteria:**

- [x] Admin can create a Staff account with validated name, unique email, and confirmed password.
- [x] New accounts always receive the `staff` role and can use verified application routes.
- [x] Guests and Staff cannot access Staff management.
- [x] Public registration remains unavailable.

**Verification:** Run focused Staff management and registration tests.

**Likely files:** `resources/views/pages/⚡staff.blade.php`, `routes/web.php`, Admin navigation, feature tests.

### Task 5: Build the attendee read boundary

**Depends on:** Tasks 2–3

**Do:** Add the Attendee model/query path keyed by UUID. Validate UUID input, select only fields allowed by the display configuration, and return a not-found result without leaking unrelated fields.

**Acceptance criteria:**

- [x] Valid UUIDs retrieve the expected attendee.
- [x] Invalid/nonexistent UUIDs return a safe not-found state.
- [x] Staff cannot request arbitrary columns or bypass field visibility.

**Verification:** Add feature tests for valid, invalid, and unauthorized field access.

**Likely files:** `app/Models/Attendee.php`, `app/Livewire/*` or controller/action, validation, tests.

### Task 6: Add Admin-controlled display fields

**Depends on:** Tasks 3 and 5

**Do:** Store the enabled attendee display-column list in the smallest suitable application settings representation. Provide an Admin-only toggle UI. Use a safe default such as name and AIUB ID; exclude payment/contact fields by default.

**Acceptance criteria:**

- [x] Admin can enable/disable supported fields.
- [x] Unsupported or sensitive fields cannot be enabled accidentally.
- [x] Staff scan results immediately respect saved settings.
- [x] Settings are cached only if useful, and writes invalidate the cache.

**Verification:** Test Admin writes, Staff reads, defaults, and rejected fields.

**Likely files:** settings model/storage, Admin Livewire page, attendee query/action, tests.

### Task 7: Build the mobile QR scanner

**Depends on:** Tasks 5–6

**Do:** Add a phone-friendly Staff scanner using an existing compatible browser QR reader only if the project has one; otherwise select the smallest maintained client library after approval. Handle camera permission, scan success, retry, invalid UUID, and camera-unavailable states.

**Acceptance criteria:**

- [x] Staff can open the scanner over HTTPS on a phone.
- [x] A QR containing an attendee UUID populates the scan flow.
- [x] Camera denial/unavailability has a usable fallback message.
- [x] No privileged database credential reaches the browser.

**Verification:** Run frontend build, browser tests where possible, and a real HTTPS phone test.

**Likely files:** `resources/views/pages/*`, `resources/js/*`, routes, `package.json` only if a dependency is approved, tests.

### Task 8: Mark receipt atomically

**Depends on:** Task 7

**Do:** Implement the conditional update: set `received=true` and `received_at=now()` only where the UUID is currently unreceived. If no row changes, read the existing receipt state and show “Already received” without overwriting the original timestamp.

**Acceptance criteria:**

- [x] First scan records receipt once.
- [x] Repeated scan reports “Already received”.
- [x] Concurrent scans cannot both claim first receipt.
- [x] Existing `received_at` is never reset by a duplicate scan.

**Verification:** Add a feature test for duplicate scans and a concurrency-oriented database test or transaction-level assertion.

**Likely files:** receipt action/service, Livewire/controller endpoint, tests.

### Checkpoint: Staff workflow

- [ ] Staff can log in, scan a real QR, see configured fields, and mark it received.
- [ ] Invalid and duplicate scans show correct states.
- [ ] Focused auth, scan, and receipt tests pass.

## Phase 3: Admin data operations

### Task 9: Add attendee Admin CRUD and receipt search

**Depends on:** Tasks 5–8

**Do:** Add Admin-only list/search/filter, create, edit, and delete operations for attendees. Preserve UUID uniqueness and validate all writable fields. Include received status/time in admin results without changing them accidentally.

**Acceptance criteria:**

- [x] Admin can search by UUID/name and filter received/unreceived.
- [x] Admin can create and edit valid attendee records.
- [x] Duplicate UUIDs are rejected safely.
- [x] Staff cannot access these actions.

**Verification:** Feature tests for CRUD, validation, authorization, and filtering.

**Likely files:** Admin Livewire pages/components, Attendee model/validation, routes, tests.

### Task 10: Add validated CSV import/export

**Depends on:** Task 9

**Do:** Add Admin-only CSV export and import. Validate the entire import before destructive changes, report row-level errors, preserve UUID uniqueness, and make the chosen duplicate policy explicit (reject duplicates by default).

**Acceptance criteria:**

- [x] Export includes the supported attendee fields and receipt state as appropriate.
- [x] Invalid imports make no partial database changes.
- [x] Duplicate UUID rows are reported clearly.
- [x] Large files are bounded or processed safely without exhausting memory.

**Verification:** Feature tests for valid import, invalid rollback, duplicates, and export content.

**Likely files:** import/export actions, Admin UI, validation, tests.

### Checkpoint: Admin data

- [ ] Admin can manage attendee records and safely import/export them.
- [ ] Staff remains restricted to staff functionality.
- [ ] Focused tests and frontend build pass.

## Phase 4: QR and email delivery

### Task 11: Generate QR images from existing UUIDs

**Depends on:** Tasks 5 and 9

**Do:** Add Admin actions to generate/download a QR image whose payload is exactly the attendee UUID. Do not invent a second identifier or put attendee personal data in the QR payload.

**Acceptance criteria:**

- [x] Each generated QR decodes to the correct UUID.
- [x] QR generation works for one attendee and a controlled batch.
- [x] Admin-only access is enforced.

**Verification:** Decode generated samples and add an authorization test. Check the selected QR library before adding it.

**Likely files:** QR action/service, Admin UI, approved dependency/config, tests.

### Task 12: Send QR emails through the queue

**Depends on:** Task 11

**Do:** Add an email template with the QR image/attachment and attendee instructions. Queue sends so the Admin request is fast. Record only the minimum delivery state needed to show queued/sent/failed feedback; do not claim delivery when SMTP/API failed.

**Acceptance criteria:**

- [x] Admin can send one or a controlled batch.
- [x] Queue jobs retry safely and do not create duplicate sends on retry.
- [x] Mail failures are visible to Admin.
- [x] No attendee data is sent to the wrong address.

**Verification:** Use Mailpit locally, test queued jobs and failure handling, then test the configured production mail transport.

**Likely files:** mail class/template, queued job/action, Admin UI, queue config, tests.

### Task 13: Add deployment and operational checks

**Depends on:** Tasks 1–12

**Do:** Package the app for the selected no-paid-host target: Google Compute Engine Free Tier with Docker Compose as the primary option, or the office/NAS fallback. Configure HTTPS, queue worker, environment secrets, health checks, logs, backups, and a rollback procedure.

**Acceptance criteria:**

- [ ] Production uses HTTPS and camera access works on a phone.
- [ ] Queue worker processes QR emails.
- [ ] Supabase credentials are stored as deployment secrets.
- [ ] Database backup and restore are tested.
- [ ] Free-tier usage alerts/limits are configured where available.

**Verification:** Run the full test suite, deploy a staging copy, test the complete scan/email flow, and perform a restore drill before production.

**Likely files:** `Dockerfile`, `docker-compose.yml`, deployment scripts/config, `.env.example`, health-check/config files.

### Final checkpoint

- [ ] All v1 acceptance criteria in [PLAN.md](./PLAN.md) pass.
- [ ] No deferred v1-limit feature was introduced accidentally.
- [ ] Full tests, build, phone scan, duplicate scan, CSV import/export, QR decode, email queue, and backup restore are verified.
- [ ] Existing unrelated working-tree changes remain intact.
