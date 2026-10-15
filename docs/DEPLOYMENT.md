# Deployment

Task Organizer is a standard Laravel 12 application. It runs on any host that has:

- PHP 8.2+ with the extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype` and `bcmath`
- MySQL 8 or MariaDB 10.6+
- a cron entry

You don't need Node.js on the server if you upload `public/build` from your machine.

## 1. Configure `.env`

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tasks.example.com
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=…
DB_DATABASE=task_organizer
DB_USERNAME=…
DB_PASSWORD=…

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# Files: local (storage/app/private) or s3
ATTACHMENTS_DISK=local

# Email (only needed for the optional email notifications)
MAIL_MAILER=smtp
MAIL_HOST=…
MAIL_FROM_ADDRESS=tasks@example.com

ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=…   # a strong password; change it after first sign-in
```

`APP_TIMEZONE` controls the following:

- what counts as "today" for due dates and delays
- when the 8:00 am briefing goes out

## 2. Install and migrate

Run these on the server:

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan key:generate
```

```bash
php artisan migrate --force --seed
```

```bash
php artisan optimize
```

Build the assets, either on the server or locally and then upload `public/build`:

```bash
npm ci && npm run build
```

Notes:

- The `--seed` step creates the departments and the administrator. It is safe to run again.
- On MySQL with binary logging enabled, creating the history triggers needs the `TRIGGER` privilege. If your host refuses, also set `log_bin_trust_function_creators=1`.

## 3. Schedule (required)

Add one cron entry:

```cron
* * * * * cd /path/to/task-organizer && php artisan schedule:run >> /dev/null 2>&1
```

It runs:

| Job | When |
|---|---|
| `tasks:check-deadlines` (auto-delay, reminders, health) | Every 15 minutes |
| `digest:send` (the briefing) | Weekdays at 08:00 |
| `active-tasks:expire` | Hourly |
| `queue:work --stop-when-empty` (sends queued emails) | Every minute |

The cron is still required. Without it, the app runs the deadline check after signed-in page loads, at most once every 5 minutes (`INLINE_DEADLINE_CHECKS=true`), but the briefing and emails need the scheduler.

## 4. Web server

Point the document root at `public/`.

Make `storage/` and `bootstrap/cache/` writable by the web server user.

Attachments are private:

- They are served only through `/attachments/{id}`, which checks permissions.
- Don't run `php artisan storage:link` for them.

## Hosting options

| Host | Notes |
|---|---|
| **VPS (DigitalOcean, Linode, AWS Lightsail/EC2)** | Nginx + PHP-FPM + MySQL. [Laravel Forge](https://forge.laravel.com) automates it. Optionally run `php artisan queue:work` under Supervisor and remove the queue line from `routes/console.php`. |
| **Laravel Cloud** | Git-push deploys with a managed database. Turn on the scheduler; set `ATTACHMENTS_DISK=s3` with its object storage. |
| **AWS** | EC2 or Lightsail as above, RDS for MySQL, and S3 for files (`ATTACHMENTS_DISK=s3` + `AWS_*` keys). Run the cron on the instance. |
| **Shared hosting / cPanel** | Upload the project with `public/` as the web root (or move `public/*` into `public_html` and fix the paths in `index.php`). Add the cron in cPanel. Everything runs without long-lived processes. |

## Upgrading

Pull the new code, then run each of these:

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan migrate --force
```

```bash
npm run build
```

```bash
php artisan optimize
```
