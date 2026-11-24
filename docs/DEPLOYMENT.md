# Production deployment

## First deploy

1. Create an Ubuntu `e2-micro` VM in a Google Cloud Free Tier region, reserve its IP, allow TCP 80/443, and point the production domain at it.
2. Install Docker Engine with the Compose plugin, clone this repository, and copy `.env.production.example` to `.env.production`.
3. Set a generated `APP_KEY`, the Supabase PostgreSQL `DB_URL`, SMTP credentials, `APP_URL`, and `APP_DOMAIN`. Keep `.env.production` only on the VM.
4. Deploy:

   ```sh
   docker compose --env-file .env.production build
   docker compose --env-file .env.production run --rm app php artisan migrate --force
   docker compose --env-file .env.production up -d
   curl --fail https://your-domain.example/up
   ```

Caddy obtains and renews HTTPS certificates. The `queue` service processes invitation mail and restarts with the VM. Check it with `docker compose ps` and `docker compose logs queue`.

## Backups and restore

Run `bin/backup-database` from cron and copy `backups/` to storage outside the VM. Supabase-managed backups do not replace an independently tested export.

Test restoration against a disposable Supabase project first:

```sh
cp .env.production .env.production.restore-test
# Set DB_URL in the copied file to the disposable database.
ENV_FILE=.env.production.restore-test bin/restore-database 20261125T120000Z.dump
```

The restore command asks for confirmation and replaces database objects. Never run it against production merely to test a backup.

## Updates and rollback

Before each update, run `bin/backup-database` and record the current Git commit. Build the new commit, run migrations, start the services, then check `/up`, queue logs, login, scanning, and one test email.

To roll back, check out the recorded commit and rebuild. Run `php artisan migrate:rollback --force` only when the reverted release's migration is explicitly reversible and no newer data depends on it; otherwise restore the pre-deploy dump with `bin/restore-database`.

Configure a Google Cloud budget alert even when targeting the Free Tier. Before launch, test HTTPS camera access on the actual phone, queue delivery through the production SMTP transport, and a disposable-database restore.
