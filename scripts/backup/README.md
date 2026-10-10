# Database backups

## What runs

1. **On ls5 (local):** the `anjuman` user's crontab runs `php artisan schedule:run`, which runs
   `php artisan db:backup` every 30 minutes, 08:00–02:00 Asia/Colombo. The newest 3 dumps are kept in
   `storage/app/backups/` (settings: `config/backup.php`, `DB_BACKUP_*` in `.env`).
2. **Off-server (R2):** root cron `/etc/cron.d/colombojamaat-api-offsite` (:05 and :35) runs
   `/usr/local/sbin/colombojamaat-api-offsite.sh`. It encrypts each new dump with the **public** key
   (`backup-public.pem`, copied to `/usr/local/lib/colombojamaat-api-backup/` with `encrypt.mjs`) and uploads it to
   R2 bucket `colombojamaat-api-backups` as `db/<name>.sql.gz.enc`. Log: `/var/log/colombojamaat-api-offsite.log`.

Protection:
- ls5 can write backups but cannot read them. Only the **private** key opens them.
- `db/` objects are **locked for 30 days** (R2 bucket lock): nobody, including someone who takes over ls5, can delete
  or overwrite them. They are removed automatically after **40 days**.
- The R2 key on ls5 can only reach this one bucket.

## The private key (keep it safe, keep two copies)

The private key is `.backup-keys/colombojamaat-api-backup-private.pem` in this project folder on the computer that set
this up (git-ignored, never on ls5). **Without it no off-server backup can be read.**

1. Store a copy in your password manager (secure note or attachment) now.
2. Never put it on ls5 or in git.

## Restoring

1. Download the object you want from the R2 dashboard (bucket `colombojamaat-api-backups`, folder `db/`).
2. Decrypt it (writes the `.sql.gz` next to it):

   ```bash
   node scripts/backup/restore.mjs ~/Downloads/colombojamaat-api_2026-10-10_16-00.sql.gz.enc
   ```

3. Load it into a scratch database first:

   ```bash
   gunzip -c ~/Downloads/colombojamaat-api_2026-10-10_16-00.sql.gz | mariadb colombojamaat_restore
   ```

   The dump starts with a MariaDB 11 `sandbox mode` line. MySQL and older MariaDB clients reject it: delete the first
   line before importing there.

Delete decrypted files when done: they hold real member and payment data.

To restore from a local copy on ls5 instead, use `storage/app/backups/*.sql.gz` directly (not encrypted).
