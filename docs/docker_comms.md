# Sync the production VM after a pull

From the project directory on the VM, after `git pull`:

```sh
bin/backup-database
docker compose --env-file .env.production build
docker compose --env-file .env.production run --rm app php artisan migrate --force
docker compose --env-file .env.production up -d --remove-orphans
docker compose --env-file .env.production ps
curl --fail --show-error https://your-domain.example/up
```

Replace `your-domain.example` with the production domain. If the health check fails, inspect the application and queue logs:

```sh
docker compose --env-file .env.production logs --tail=100 app web queue
```

## Check the live queue

```sh
docker compose --env-file .env.production ps queue
docker compose --env-file .env.production top queue
docker compose --env-file .env.production logs --tail=100 --follow queue
```

The queue service should be `Up`, and `top` should show `php artisan queue:work`. While following the logs, send one test invitation from the app and confirm its status changes to sent. Press `Ctrl+C` to stop following the logs; the queue keeps running.

Check for failed jobs with:

```sh
docker compose --env-file .env.production exec app php artisan queue:failed
```
