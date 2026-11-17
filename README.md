# AutoTrackPlus

A PHP 8.2, Apache, and MySQL vehicle maintenance app with employee dashboards, vehicle and repair tracking, reminders, activity history, and an admin panel.

## Run locally with Docker

```sh
docker compose up -d --build
docker compose exec app php bin/migrate.php
```

Open http://localhost:8080/autotrackplus/public/login.php. The existing application path and MySQL `mysql_data` volume are retained. The app and local mail viewer bind to loopback only; MySQL has no published host port.

The migration adds password hashes, session versions, setup authorization, hashed expiring tokens, and rate-limit storage. It preserves existing user IDs, employee codes, roles, account status, vehicles, repairs, notifications, and history. The runner is repeat-safe and can resume partially completed DDL. `sql/schema.sql` also supports fresh installations. Editing that file alone does not upgrade an existing volume.

Do not delete the database volume or run `docker compose down -v`. Take a database backup before upgrading an existing installation. A backup from this implementation session is stored at `/private/tmp/autotrackplus-before-passwords.sql` on the host; copy it to secure durable storage if needed.

## Set up the existing admin

Code-only login and old code-only sessions no longer work. There is no default password. The seeded admin retains employee code `999999` and its existing data.

For local testing, issue an invitation to the seeded address (Mailpit captures it; nothing is delivered externally):

```sh
docker compose exec app php bin/issue-password-setup.php \
  --code=999999 --email=admin@example.com --confirm-identity
```

Open http://localhost:8025, open the setup email, and follow its link. Choose and confirm a unique password of 15–128 characters. Return to the login page and sign in with `999999` and that password. The link expires after 30 minutes and works once; rerun the command if needed. No password is printed, emailed, or stored in plaintext.

The `--confirm-identity` flag is an operator assertion: confirm the employee identity and destination email against trusted records before using it. `--email` deliberately updates that account's email if different. Use a real confirmed address when preparing real accounts; the seeded placeholder is only appropriate for local testing. This command never activates a disabled account.

## Set up employees and reset passwords

Sign in as admin to create and invite employees, or send invitations to existing accounts. Confirm the employee's identity and the listed email before checking the confirmation box. Admins do not choose or see employees' passwords. Public self-registration is replaced with an instruction to contact the administrator, so outsiders cannot claim employee codes. Existing accounts are not automatically authorized for recovery merely because the legacy `is_verified` flag is set.

An operator can also issue an invitation for an existing employee using their code and confirmed email with the command above. Once authorized, active users can select **Set or reset password** on the login page to request another email. Responses do not reveal account existence. Resending invalidates earlier links. Password reset revokes all previous sessions; old passwords stop working. Disabling an account revokes sessions immediately. Password setup and reset never enable a disabled account.

Sessions expire after 30 minutes of inactivity or 12 hours total. Login, setup requests, and token submissions are rate limited in MySQL. CSRF tokens protect login, setup, admin actions, and employee changes. Passwords use Argon2id with PHP-managed salts and rehashing. The strength check enforces length and a small local list of predictable passwords; it is not a comprehensive breached-password lookup.

## Local email and configuration

Mailpit accepts SMTP at `mailpit:1025` inside Docker, and shows captured messages at http://localhost:8025. Its inbox is a local test facility, without authentication, and must remain private. Messages are temporary and may disappear when that container is recreated; request a new invitation if needed.

`config/config.php` accepts `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and an absolute `APP_URL`. Compose sets `APP_URL=http://localhost:8080/autotrackplus`. Links use this trusted configured URL, never the incoming Host header. Use the hostname `localhost` consistently in the browser so session cookies match.

Local SMTP uses `SMTP_HOST`, `SMTP_PORT`, and `MAIL_FROM`. Authenticated SMTP additionally uses `SMTP_TLS=1`, `SMTP_USER`, and `SMTP_PASSWORD` (credentials require STARTTLS). Production uses Brevo’s HTTPS API with `BREVO_API_KEY` and a Brevo-verified `MAIL_FROM`; it does not use SMTP. Production also needs HTTPS, a dedicated MySQL application account with a strong password, and secrets supplied outside version control. HTTPS `APP_URL` enables Secure session cookies. Keep the local HTTP setting for local Docker testing. No hosting or deployment is configured by this change.

## Verification

```sh
docker compose exec app php tests/mailer-test.php
```

The mailer test uses a mocked Brevo request and a temporary local SMTP server. It sends no email externally and does not connect to a database. Brevo accepting a request does not guarantee final delivery; check delivery events in Brevo after configuring production. Review Brevo transactional click-tracking and log settings before sending password links: those links contain single-use tokens and may be rewritten or recorded by the provider.

The broader integration suite uses an isolated test database:

```sh
docker compose exec app php tests/auth-integration.php
```

The CLI suite creates a separate randomly named MySQL test database, upgrades legacy fixtures, and tests HTTP flows on an internal temporary PHP server. It checks data preservation, repeat-safe migration, admin/employee login, email setup, token expiration and replay, invitation replacement, CSRF, role restrictions, session revocation, disabled accounts, rate limits, logout, and existing vehicle editing. It drops only its own test database and removes only its own test emails. Existing application data and volume are untouched. Tests require local MySQL administrative privileges and Mailpit; do not run against a production service.

To check a local migration manually, run `php bin/verify-migration-data.php capture` in the app container before the migration and `php bin/verify-migration-data.php compare` immediately afterward, before using the app or recreating its container (the baseline is temporary). This compares hashes of every original business column; it does not store raw records. The session's real upgrade preserved 2 users, 1 vehicle, 1 repair, and 9 activity entries.

PHP files can be linted inside the app container. Apache denies HTTP access to configuration, schema, CLI tools, tests, Git metadata, and Docker configuration. Employee code remains a six-digit identifier, including leading zeros; possession of the code never grants access.


## Railway production setup

The Docker image includes the application and listens on Railway's `PORT` (default 80 locally). The app remains at `/autotrackplus`. Railway terminates HTTPS at its proxy; set `APP_URL` to the exact external HTTPS URL including that path. The Docker image and `.dockerignore` contain no credentials. Set secrets in Railway service variables, not in source files. See `.env.example` for the required variable names.

1. Provision a Railway MySQL service with a persistent volume and backups. Use its private-network host and port from the MySQL service's `MYSQLHOST` and `MYSQLPORT` reference variables; set `DB_NAME`, `DB_USER`, and `DB_PASSWORD` from the corresponding database settings. Prefer a dedicated application account; migrations and initialization need DDL privileges. Railway's MySQL template may expose root credentials: provision a less-privileged account for normal app use. Keep MySQL private. If using a provider requiring TLS, set `DB_SSL_CA` to the absolute path of its CA certificate in the container and verify the provider's hostname/certificate. Private networking alone does not establish database TLS.
2. Configure `APP_ENV=production`, `APP_URL=https://your-domain/autotrackplus`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. Set `BREVO_API_KEY` to a Brevo API key (not an SMTP key) and `MAIL_FROM` to a sender address verified in Brevo. Brevo transactional sending must be enabled on the account. Store the key as a Railway secret; never put it in the repository or logs. Production sends via `https://api.brevo.com/v3/smtp/email` over HTTPS; no `SMTP_*` variables are needed there. Mailpit remains the local development transport. Production configuration fails closed if required values are absent.
3. For a **new, empty production database only**, use an authorized Railway service shell to run `php bin/init-database.php` and then `php bin/migrate.php`. Initialization refuses a nonempty database and creates no default admin. For an existing database, back up and restore it through a reviewed migration procedure and run only `php bin/migrate.php`; do not run initialization. The migration is explicit, not automatic at container startup.
4. In the service shell, after confirming the person's identity and email, run `php bin/create-first-admin.php --code=123456 --email=confirmed@example.com --first-name=First --last-name=Last --confirm-identity` with the real values. It refuses if an admin already exists and emails a 30-minute single-use password link. If delivery fails after account creation, correct the Brevo configuration and retry with `php bin/issue-password-setup.php --code=... --email=... --confirm-identity`. For imported accounts, use that latter command after validating the destination against trusted records. Never use the local seeded admin email in production.
5. Verify login, reset email, logout, restricted paths, HTTPS cookies, and a backup restore before going live. The production password denylist is small; add a comprehensive compromised-password check and assess MFA for administrators. Configure monitoring and appropriate rate limits at the edge; the app's IP throttling uses `REMOTE_ADDR`, which may identify a proxy. Only trust forwarded client IPs when the proxy boundary has been explicitly configured and tested.

With `APP_ENV=production`, PHP sessions use the `app_sessions` MySQL table created by migration 002. This supports multiple app instances and survives container restarts. Session contents include login and password-setup state, so protect database access and backups; expiration and revocation remain enforced by the app. MySQL must support advisory locks on the same connection. Local Compose retains file sessions by default; set `SESSION_DRIVER=mysql` after applying migration 002 to exercise the shared handler locally. Session garbage collection removes expired entries probabilistically; monitor table growth.

The Apache configuration denies directory listing and blocks access to configuration, SQL, CLI tools, tests, and Git metadata. The source tree uses the historical `/autotrackplus` layout; verify these restrictions in the deployed environment. Do not expose the local Compose MySQL root configuration, local Mailpit, or backup files publicly.
