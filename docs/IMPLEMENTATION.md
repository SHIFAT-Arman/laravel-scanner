# Implementation snapshot

- Laravel 13 / PHP 8.5 app using Livewire 4, Fortify, Flux UI, Blaze, Tailwind 4, and Vite Plus.
- Current product surface includes Fortify auth, the verified dashboard, Staff QR scanning, Admin attendee display settings, and Admin attendee CRUD/search/CSV plus QR preview/download.
- Routes live in `routes/web.php` and `routes/settings.php`; application code is mainly in `app/Actions/Fortify`, `app/Livewire`, `app/Models`, and Blade page components under `resources/views`.
- Persistence is connected to Supabase PostgreSQL with the starter tables plus an existing `attendees` table keyed by unique `uuid`; receipt fields and atomic receipt marking are implemented. `DatabaseSeeder` creates `test@example.com`.
- The Staff scanner looks up only configured attendee fields, marks receipt with a conditional update, and preserves the first receipt timestamp on duplicate scans.
- Admin attendee management supports UUID/name search, receipt filtering, validated create/edit/delete operations, bounded all-or-nothing CSV import, CSV export, and read-only receipt state.
- Admins can queue one or up to 100 QR invitation emails with queued/sending/sent/failed feedback. Jobs retry three times, suppress duplicate dispatches, and attach the attendee UUID QR. Development mode redirects every invitation to the initiating admin instead of members.
- Tests are Pest feature coverage for auth, dashboard, and settings, plus example unit coverage.
- Lerd serves `my-scanner-app.test` with PHP 8.5, Node 22, HTTPS, Redis, Meilisearch, and Mailpit; the queue and Vite workers are running, while schedule is configured but stopped.
- Production deployment assets now provide a PHP-FPM app, Caddy-managed HTTPS, a queue worker, a health check, and PostgreSQL backup/restore commands. Cloud provisioning and the production phone, SMTP, and restore drills remain outstanding.
- Working tree already had unrelated edits in `config/fortify.php` and `resources/views/pages/auth/login.blade.php`; preserve them unless explicitly asked.
