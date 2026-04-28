# FitForge

Symfony 7.4 REST API for fitness, nutrition, and meal planning.

## Requirements

- PHP >= 8.2 (with `ext-ctype`, `ext-iconv`)
- Composer
- Docker / Docker Compose
- Symfony CLI (recommended for the local web server)

## Dev Setup

1. **Clone and install dependencies**

   ```bash
   git clone <repo-url> FitForge
   cd FitForge
   composer install
   ```

2. **Start the database**

   ```bash
   docker compose up -d
   ```

   This boots a PostgreSQL 16 container exposed on `localhost:5432`
   (db: `app`, user: `app`, password: `!ChangeMe!`).

3. **Generate JWT keys** (required for `lexik/jwt-authentication-bundle`)

   ```bash
   php bin/console lexik:jwt:generate-keypair
   ```

   The passphrase is read from `JWT_PASSPHRASE` in `.env`.

4. **Run migrations and load fixtures**

   ```bash
   php bin/console doctrine:migrations:migrate -n
   php bin/console doctrine:fixtures:load -n
   ```

5. **Start the dev server**

   ```bash
   symfony server:start -d
   ```

   The API is then reachable at `https://127.0.0.1:8000`.

   Without the Symfony CLI:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

## Useful Commands

```bash
# Clear cache
php bin/console cache:clear

# Create a new migration from entity changes
php bin/console make:migration

# Reset the database
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n

# Stop containers
docker compose down
```

## API Testing

Bruno collections live in `bruno/FitForge/`. Open them with [Bruno](https://www.usebruno.com/) to exercise the endpoints.

## Environment

Copy `.env` to `.env.local` to override values locally without committing them. Real secrets must never be committed — see `config/secrets/` or environment variables for production.
