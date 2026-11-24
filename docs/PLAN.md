# QR Receipt App Plan

## Goal

Staff-only web app: a staff member scans an attendee UUID QR code with a phone camera, sees the Admin-selected attendee fields, and marks the record received. A second scan shows “Already received”.

## Existing data

- Supabase PostgreSQL is the target database.
- Existing `attendees` table uses `uuid` as its unique primary key and currently contains attendee/contact/payment fields.
- Add only the minimum receipt fields: `received` (boolean, default false) and `received_at` (nullable timestamp). No scan-history table for v1.

## Roles and access

- Add a `role` to users: `admin` or `staff`; allow multiple admins.
- Disable public registration. Admins create, edit, deactivate, and delete staff/admin users.
- Staff can access scanning and attendee lookup only.
- Admins can do everything staff can, plus attendee CRUD, CSV import/export, QR/email management, and display-field settings.

## Staff workflow

1. Staff opens the mobile-friendly scanner and grants camera access.
2. The scanner reads the UUID from a QR code and looks up the matching `attendees.uuid`.
3. For a valid, unreceived record, show the enabled fields and a clear “Mark received” action.
4. Marking received updates `received` and `received_at` atomically, then confirms success.
5. If already received, show “Already received” and the existing receipt time. Invalid UUIDs show a safe “Not found” message.

## Admin workflow

- Toggle which attendee columns appear on the scan result; default to safe fields such as name and AIUB ID, excluding sensitive payment/contact fields unless enabled.
- Generate downloadable QR images for existing UUIDs and send them to each attendee by email.
- Support attendee create/edit/delete, CSV import, and CSV export. Preserve UUID uniqueness and validate imported rows before changing data.
- Provide basic receipt search/filtering by UUID, name, and received status.

## Architecture decisions

- Keep the Laravel app as the only server-side API boundary. The phone scanner sends the UUID to Laravel; the browser never connects directly to Supabase with privileged credentials.
- Use the existing UUID primary key for lookup. Select only the enabled display columns instead of loading every attendee field.
- Treat receipt marking as an idempotent conditional update: `UPDATE attendees SET received=true, received_at=now() WHERE uuid=? AND received=false`. If zero rows change, fetch the record and report “Already received”. This closes the race where two staff phones scan the same QR at once; no application-level lock is needed.
- Do not cache attendee details or receipt status: stale data could incorrectly show a person as unreceived. Cache only the small admin display-field configuration with a short TTL and clear it whenever Admin saves settings.
- Add database indexes only where needed: UUID is already indexed; add an index for `received` if receipt filtering proves slow. Do not add Redis as a correctness dependency.
- Keep the scan path to one small read and one atomic write, return compact JSON/Livewire state, and rate-limit scan submissions per authenticated user. 800 requests per day is a very small load for this design.
- Queue QR-email delivery so scanning and admin pages never wait on SMTP. Show queued/sent/failed state; do not silently claim an email was delivered.
- Use HTTPS because phone camera access requires a secure context. Keep Supabase credentials server-only and never expose service-role keys to the browser.

## Delivery order

1. Confirm Supabase connection and add the receipt columns/migration.
2. Implement roles, admin/staff authorization, and remove public registration.
3. Add attendee model, validation, CRUD, import/export, and tests.
4. Add configurable scan-field settings and the mobile scanning screen.
5. Add atomic receipt update, duplicate-scan behavior, and tests.
6. Add QR generation, email templates, queued sending, and delivery failure feedback.
7. Run the full test suite and verify the phone-camera flow on the deployed HTTPS URL.

## No-paid-host deployment

Recommended replacement for Oracle: Google Cloud Compute Engine's ongoing Free Tier with one `e2-micro` VM, plain Docker Compose, and the existing Supabase database. Google lists one non-preemptible `e2-micro`, 30 GB standard persistent disk, and 1 GB monthly outbound transfer in eligible US regions. This is enough for roughly 800 requests/day when the app, queue worker, and HTTPS proxy are kept small. The account may still require identity/billing verification, and usage outside the free limits can cost money.

- Host: Ubuntu VM, Laravel container, queue worker, and Caddy/Nginx HTTPS proxy. Use plain Compose; Coolify is open-source but adds unnecessary control-plane overhead on a 1 GB VM.
- Database: existing Supabase PostgreSQL; verify its free-plan storage/egress quotas before production use.
- Email: use an SMTP provider with a free allowance or an organization mail relay; email sending is not guaranteed by a free host.
- Backups: scheduled Supabase/database exports copied outside the VM and a tested restore procedure.

Fallbacks:

- Google Cloud Run: suitable for the stateless web container and has a permanent free request allowance, but queue workers and long-running processes need a separate design.
- Render or Koyeb free services: easy for a demo, but not preferred for the event app because free instances sleep, can have cold starts, and have production/storage restrictions.
- Office computer/NAS: truly no hosting fee; expose the Compose stack through a Cloudflare Tunnel, but availability depends on the local machine and internet connection.

References: [Google Cloud Free Tier](https://docs.cloud.google.com/free/docs/free-cloud-features), [Google Cloud Run free allowance](https://cloud.google.com/serverless), [Render free limitations](https://render.com/docs/free), [Koyeb free instance limits](https://www.koyeb.com/docs/reference/instances), [Cloudflare Tunnel](https://developers.cloudflare.com/tunnel/get-started/), and [Supabase free-plan bandwidth](https://supabase.com/docs/guides/storage/serving/bandwidth).

## v1 acceptance criteria

- Unauthenticated users cannot access the app.
- Staff can scan a real QR code on a phone and receive the correct result.
- A successful first scan records `received=true` and `received_at` once.
- Repeated scans never reset or overwrite the original receipt time.
- Admin field toggles immediately control scan-result visibility.
- Admins can manage users and attendee data, import/export records, generate QR codes, and send emails.

## Explicit v1 limits

No per-scan audit history, invalid/expired QR states, event/location restrictions, offline scanning, or QR revocation until the basic workflow proves it needs them.
