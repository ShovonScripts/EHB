# Deployment Guide

## Environment Variables

Create a production `.env` file with the following minimum settings:

```env
APP_NAME="Emrul Hasan Bappi"
APP_ENV=production
APP_KEY=base64:<generated-key>
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ehb_journalist
DB_USERNAME=<db-user>
DB_PASSWORD=<db-password>

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=<mail-host>
MAIL_PORT=587
MAIL_USERNAME=<mail-username>
MAIL_PASSWORD=<mail-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="hello@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### HTTPS / HSTS

- Terminate TLS at the load balancer or web server.
- Set `APP_URL` to `https://`.
- Ensure the web server sends `Strict-Transport-Security` header.

## Deploy Command Sequence

```bash
# 1. Install dependencies (no dev)
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Run migrations
php artisan migrate --force --no-interaction

# 3. Cache config, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Create storage symlink
php artisan storage:link

# 5. Build frontend assets
npm install
npm run build
```

## Media Files and the Install Path

Uploads live in `storage/app/public` and are served through the public disk. Two
things decide whether an image that is visible in the admin is visible on the
site:

- **`public/storage`** — run `php artisan storage:link`. At a domain root the
  site works without it: the `public` disk sets `serve => true`, so Laravel
  registers a `/storage/{path}` fallback route that reads the file out of
  `storage/app/public` itself. That route only answers when the site is served
  from a domain root — see below.
- **The install path** — if the site is served from a subdirectory
  (`http://localhost/ehb/public`) rather than its own domain or vhost:

  1. Add `MEDIA_URL=/ehb/public/storage` to `.env` and run
     `php artisan config:clear`. Without it, page images are emitted as
     `/storage/…`, which the browser resolves against the *domain* root, so
     every image 404s while the admin panel — whose assets are built from
     `APP_URL` — looks perfectly fine.
  2. Nothing else. The application registers the fallback route in the form a
     request in this layout actually matches (`bootstrap/app.php`), so uploads
     are served out of `storage/app/public` whether or not the web server can
     follow `public/storage` — a Windows junction, a host without symlinks, or
     a stale copy. `php artisan storage:link` remains optional and, if Apache
     cannot follow the symlink, an Alias works too:

     ```apache
     Alias /ehb/public/storage "C:/xampp/htdocs/EHB_new/storage/app/public"
     <Directory "C:/xampp/htdocs/EHB_new/storage/app/public">
         Require all granted
     </Directory>
     ```

After any change to `APP_URL`, `MEDIA_URL` or the storage configuration, run
`php artisan config:clear && php artisan config:cache`, `php artisan cache:clear`
(pages cached with the old URL last ~2 minutes), and `route:clear` /
`route:cache` when routes are cached. `php artisan media:doctor` inspects the
whole chain — disk configuration, the symlink, Media rows with no file on this
server, the registered `/storage/{path}` route, and a real HTTP fetch of the
URLs the site emits — and exits non-zero with a list of what it found.

## Post-Launch Smoke-Test Checklist

- [ ] `php artisan media:doctor` exits 0 (every Media row has its file on this server, and `/storage/…` returns 200).

- [ ] `https://your-domain.com/sitemap.xml` loads and excludes Draft items.
- [ ] `https://your-domain.com/robots.txt` is present and correct.
- [ ] Admin login at `/admin` works with the owner account.
- [ ] Contact form delivers and appears in the admin Messages screen (`/admin/contacts`).
- [ ] All 6 section listings return 200:
  - `/articles`
  - `/investigations`
  - `/interviews`
  - `/opinions`
  - `/multimedia`
  - `/work`
- [ ] All 6 section detail pages return 200 for a published item.
- [ ] A Draft item returns 404 at its public URL.
- [ ] Security headers are present (`Content-Security-Policy`, `X-Frame-Options`, etc.).

## Open Infrastructure Decisions

The following remain undecided and are not provisioned by this codebase:

- **Hosting target** — no preference encoded; the app runs on any PHP 8.2+ host with MySQL 8+ and a supported web server.
- **Backup storage destination** — `backup:database` writes to `storage/app/backups` by default; configure `--path` to point at durable object storage or a mounted backup volume in production.
- **Mail provider** — `MAIL_*` variables above are placeholders; choose SMTP or a service such as Postmark, Mailgun, or SES and update `.env`.

## Backup & Restore

```bash
# Backup
php artisan backup:database --path=/path/to/backups

# Restore
php artisan restore:database /path/to/backups/database-YYYY-MM-DD-HHMMSS-XXXXXX.sql --force
```

For MySQL environments with `mysqldump` available, the backup command uses it automatically. Otherwise it falls back to a PHP-based SQL export.
