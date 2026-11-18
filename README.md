# AutoTrackPlus

AutoTrackPlus is a vehicle maintenance tracking application built with PHP and MySQL. It helps employees keep track of vehicles, service dates, insurance expirations, and repair history. Administrators can manage employees and view vehicles across the system.

**Live app:** [autotrackplus-production.up.railway.app](https://autotrackplus-production.up.railway.app/)

## Features

### Employees

- Add and manage vehicles
- Track service dates, insurance expirations, and mileage
- Add and update repair records
- View notifications and activity history

### Administrators

- Create and invite employees
- Manage employee access
- View vehicles across the system

### Account security

- Sign in with an employee code and password
- Set or reset a password using an email link
- Passwords are stored as Argon2id hashes
- Password setup links expire after 30 minutes and can be used once

## Built with

- **PHP 8.2** and **Apache**
- **MySQL**
- **JavaScript** and **CSS**
- **Docker** and **Docker Compose**
- **Railway** for hosting
- **Brevo** for production email
- **Mailpit** for local email testing

## Run locally

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Git

### 1. Clone the repository

```bash
git clone https://github.com/imrakeshgulla/autotrackplus.git
cd autotrackplus
```

### 2. Start the application

```bash
docker compose up -d --build
docker compose exec app php bin/migrate.php
```

### 3. Open the app

Visit [localhost:8080/autotrackplus/public/login.php](http://localhost:8080/autotrackplus/public/login.php).

The first startup may take a few minutes while Docker downloads the required images.

## Set up the local admin account

For local testing, run:

```bash
docker compose exec app php bin/issue-password-setup.php \
  --code=999999 --email=admin@example.com --confirm-identity
```

Then:

1. Open [Mailpit](http://localhost:8025/).
2. Open the password setup email.
3. Follow the link and create a password.
4. Return to the login page and sign in using employee code `999999` and your new password.

Mailpit captures emails locally; it does not send them to a real inbox. The seeded admin details above are for **local testing only**.

> The `--confirm-identity` flag confirms that the operator has checked the person's identity and email. For real accounts, verify both before issuing an invitation.

## Employee invitations

An administrator can create and invite employees from the admin panel. The employee receives a link to choose their own password.

Existing employees can also receive a password invitation after an administrator confirms their identity and email address. Public self-registration is not enabled.

## Testing

Run the mailer test:

```bash
docker compose exec app php tests/mailer-test.php
```

Run the authentication integration tests:

```bash
docker compose exec app php tests/auth-integration.php
```

The integration tests use a separate temporary test database and Mailpit. Run them in the **local Docker environment**, not against production.

## Deployment

The live application runs on Railway using the project’s Docker image and a separate MySQL database. Production email is sent through Brevo’s HTTPS API.

The production environment requires:

- `APP_ENV`
- `APP_URL`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`
- `BREVO_API_KEY`
- `MAIL_FROM`

`MAIL_FROM` must be a verified Brevo sender, and transactional email must be enabled on the Brevo account. Keep credentials in Railway’s service variables—not in the repository.

For a **new, empty production database**, initialize the schema and apply migrations from an authorized app service shell:

```bash
php bin/init-database.php
php bin/migrate.php
```

The initializer refuses a database that already contains tables and does not create a default admin. For an existing database, back up the data and follow a reviewed migration process; do not run the initializer.

Production sessions are stored in MySQL. Before deploying, verify HTTPS, database access, email delivery, account recovery, and backup restoration.

## Backups

The local and Railway databases are separate. Back up the database you intend to protect, and store backups securely outside the Git repository.

The Docker volume contains local database data. Avoid deleting it if you want to keep that data:

```bash
# Stops the containers without deleting the database volume
docker compose down
```

## Project structure

```text
actions/          Application actions and request handlers
bin/              Setup and migration commands
config/           Database, security, session, and email configuration
docker/           Apache and container startup configuration
public/           Login, dashboards, and frontend assets
sql/              Database schema and migrations
tests/            Application tests
Dockerfile        Application container configuration
compose.yaml      Local development services
```

## Author

**Rakesh Gulla**  
[GitHub: @imrakeshgulla](https://github.com/imrakeshgulla)
