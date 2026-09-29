# Infrastructure Decisions

## Context

- Solo developer operation based in Bangladesh.
- Personal/professional journalist portfolio site (Laravel 12 + Filament 5 + MySQL).
- Low-to-moderate traffic, media-heavy (uploaded images via `storage:link`).
- Budget-conscious but willing to pay for reliability and low latency to South Asia.
- All three decisions below are independent; choices do not block each other.

---

## 1. Hosting

### Option A — Laravel Forge + DigitalOcean/AWS Lightsail VPS
- **Rough monthly cost:** ~USD 15–25 (Forge Starter) + ~USD 6–12 (2 GB VPS) = ~USD 21–37/month.
- **Deploy complexity:** Low. Forge provisions the server, runs the deploy command sequence from `docs/DEPLOYMENT.md` on git push, and manages nginx/MySQL/SSL.
- **Media/`storage:link` support:** Full. You own the filesystem, so `storage/app/public` works natively.
- **Bangladesh/South Asia latency:** DO has no local region, but Singapore/Sydney peering is decent (~120–180 ms). Lightsail is similar.
- **Payment methods:** International card (Visa/Mastercard) widely accepted by DO; Forge accepts the same.

### Option B — Railway
- **Rough monthly cost:** ~USD 5–20 (Hobby plan) for PHP + MySQL services; scales with usage.
- **Deploy complexity:** Low-medium. Git-based deploy, but the deploy sequence must be encoded in `railway.json` or a start command (`sh -c 'php artisan migrate --force && php artisan config:cache && php artisan serve --host 0.0.0.0 --port $PORT'`). Filament works fine.
- **Media/`storage:link` support:** Partial. Railway disks are ephemeral by default; you must attach a persistent volume (~USD 1/GB-month) and symlink to it.
- **Bangladesh/South Asia latency:** Railway’s nearest region is typically Singapore or Japan (~100–150 ms).
- **Payment methods:** International card only.

### Option C — cPanel shared/VPS (e.g., Hostinger, Namecheap, BDIX-compatible local hosts)
- **Rough monthly cost:** ~USD 3–15 (shared) or ~USD 10–25 (VPS).
- **Deploy complexity:** Medium-high. You run the deploy sequence from SSH or a CI hook; no native Forge-style git-push deploy unless you script it.
- **Media/`storage:link` support:** Full on VPS; shared hosts may restrict symlinks, so verify `allow_symlink` or use public paths.
- **Bangladesh/South Asia latency:** Local BD hosts (e.g., ExonHost, BDIX Network) can offer sub-50 ms for local visitors, but international reach may be slower. Some hosts support BDIX peering.
- **Payment methods:** Local payment methods (bKash, Nagad, Rocket) often accepted by BD hosts; international hosts use cards.

### Recommendation: Option A (Forge + DigitalOcean)
Forge is purpose-built for Laravel, the deploy command sequence in `docs/DEPLOYMENT.md` maps 1:1, and Filament’s `storage:link` works without storage-driver changes. If latency to South Asia becomes a hard requirement, swap the DO droplet for an AWS Lightsail instance in the Singapore region without changing any app code.

**What changes in `.env` / `docs/DEPLOYMENT.md`:**
- Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.com`.
- Update `DB_CONNECTION=mysql` and the `DB_*` keys to the Forge-provisioned MySQL instance.
- Add `SESSION_SECURE_COOKIE=true` once HTTPS is live.
- `docs/DEPLOYMENT.md` already covers the command sequence; only the `composer install` target and `.env` provisioning differ.

---

## 2. Backup Storage Destination

### Option A — S3-compatible object storage (Cloudflare R2, Backblaze B2, or AWS S3)
- **Rough monthly cost:** R2 is free egress + ~USD 0.015/GB-month; B2 is ~USD 0.005/GB-month + egress fees; S3 is ~USD 0.023/GB-month + egress.
- **Retention:** Easy. Store multiple dated files (`database-YYYY-MM-DD-HHMMSS-XXXXXX.sql`); lifecycle rules auto-delete after N days.
- **Integration:** Update `backup:database` to stream SQL to the provider SDK instead of local disk, or add a post-backup `aws s3 cp` step.
- **Pros:** Off-site, durable, survives server loss.

### Option B — Second server / mounted NFS volume
- **Rough monthly cost:** ~USD 5–10 (small backup VPS or attached volume).
- **Retention:** Manual rotation script (`find storage/app/backups -mtime +30 -delete`).
- **Integration:** Change `backup:database --path=/mnt/backups`.
- **Pros:** No egress fees; full control.

### Option C — Managed backup add-on tied to the chosen host
- **Rough monthly cost:** ~USD 2–10 (varies by host; Forge has server snapshots, DO has Managed Backups).
- **Retention:** Host-managed retention policies.
- **Integration:** Usually transparent; the backup file is still written locally first, then the host snapshots the volume.
- **Pros:** Zero scripting; survives full server failure if snapshots are enabled.

### Recommendation: Option A (Cloudflare R2 or Backblaze B2)
Lowest marginal cost, true off-site redundancy, and the egress-free model (R2) means you can restore to a new server without bandwidth anxiety. The smallest change is to extend `backup:database` with a post-backup `putObject` call, or add a separate `backup:database-and-upload` command.

**What changes in `.env` / `docs/DEPLOYMENT.md`:**
- Add provider credentials: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT` (for B2/R2).
- Update `filesystems.php` `s3` disk to point at the chosen provider.
- Update `docs/DEPLOYMENT.md` backup section with the new destination path or command.

---

## 3. Mail Provider

### Option A — Transactional email API (Resend, Postmark, or Mailgun)
- **Rough monthly cost:** Free tier up to ~100 emails/day (Resend) or ~100/month (Postmark); paid tiers start ~USD 3–15/month for a few thousand emails.
- **Setup complexity:** Low. Change `MAIL_MAILER=resend` or `postmark`, add the API key, and Laravel’s existing `Mail::to(...)->send(...)` in `ContactController` works without code changes.
- **Deliverability:** High. APIs handle SPF/DKIM/DMARC setup via DNS.
- **Bangladesh relevance:** Works globally; some Bangladeshi mail clients (e.g., webmail) occasionally flag bulk SMTP, but APIs mitigate this.

### Option B — SMTP via hosting provider
- **Rough monthly cost:** Included with most hosting plans.
- **Setup complexity:** Low. Set `MAIL_MAILER=smtp`, `MAIL_HOST=mail.your-domain.com`, `MAIL_PORT=465/587`.
- **Deliverability:** Variable. Shared hosting IPs often land in spam; VPS IPs are better but still require reverse DNS and SPF records.
- **Bangladesh relevance:** Works fine for low volume if DNS is configured.

### Option C — Local MDA + external relay (e.g., Postfix on VPS relayed through Amazon SES)
- **Rough monthly cost:** Free (SES free tier) or ~USD 1–10/month for low volume.
- **Setup complexity:** High. Requires VPS-level mail server configuration, SPF/DKIM, bounce handling, and rate-limit tuning.
- **Deliverability:** Best if configured correctly, but high maintenance burden for a solo developer.

### Recommendation: Option A (Resend)
Resend offers a generous free tier, modern API, and Laravel has first-party support. If volume grows past the free tier, the next tier is still cheap. This requires the least ongoing maintenance.

**What changes in `.env` / `docs/DEPLOYMENT.md`:**
- Set `MAIL_MAILER=resend`, `MAIL_FROM_ADDRESS=hello@your-domain.com`, `RESEND_KEY=re_xxx`.
- Update `docs/DEPLOYMENT.md` environment checklist to remove placeholder SMTP variables and add the Resend API key.
- Add `RESEND_KEY` to `.env.example`.

---

## Summary

| Decision | Recommendation | Key `.env` changes | Key doc changes |
|----------|---------------|-------------------|-----------------|
| Hosting | Forge + DO/Lightsail | `APP_ENV`, `DB_CONNECTION`, DB keys, `SESSION_SECURE_COOKIE` | None (deploy sequence already matches) |
| Backup storage | Cloudflare R2 or Backblaze B2 | `AWS_*` keys (bucket, endpoint, region) | Add off-site upload step to backup section |
| Mail provider | Resend API | `MAIL_MAILER=resend`, `RESEND_KEY` | Replace SMTP placeholders with Resend checklist items |

These three decisions are the only remaining items before launch. No code changes are required beyond the `.env` and documentation updates listed above.
