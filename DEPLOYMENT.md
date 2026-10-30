# Vercel deployment

CoolMeter can run on Vercel using the pinned community `vercel-php@0.9.0` runtime (PHP 8.5). It remains a Laravel application. The online version requires hosted PostgreSQL and sign-in; the original SQLite home mode remains available locally.

## Project

Vercel project: `coolmeter` in `joreenns-projects`.

Project ID: `prj_cP8QfgaXkEHoB0fkLSNkrQ7p1tWJ`.

Dashboard: https://vercel.com/joreenns-projects/coolmeter

Production address: https://coolmeter.vercel.app

Verified production deployment: `dpl_DzRfxgPJUhYxhSxvynQ8E5asB5fx` (October 29, 2026). Login returned HTTP 200, unauthenticated journal API access returned HTTP 401, and the PWA manifest returned HTTP 200. The full local suite passed 25 tests (108 assertions), followed by the authentication tests after the final routing update (54 assertions).

The existing Vercel sign-in protection remains in effect in addition to the app's owner sign-in. This requires the deployment owner's Vercel account to access protected URLs. No paid upgrade or custom domain was purchased.

Creating the project does not mean a working app has been deployed. Check the deployment's final status and test its URL after the database is connected.

## Hosted database

Connect a dedicated PostgreSQL database using Vercel Storage, for example Neon's Free plan. Select the Production environment. The integration supplies `POSTGRES_URL` or `DATABASE_URL`; Laravel also accepts `DB_URL`. Do not paste credentials into source or commit `.env`.

The runtime supports PDO PostgreSQL even if your local XAMPP does not. SQLite files, caches, logs, and local session files are excluded from uploads. An in-memory or `/tmp` database is not used as a substitute for permanent storage.

## Environment

The project has nonsecret production environment variables for production mode, debug off, PostgreSQL with SSL, database-backed sessions/cache, secure session cookies, and mandatory sign-in. These are also documented in `vercel.json`. When deploying directly through the API, configure them as project environment variables: do not rely on the CLI-only legacy `env` handling in that file.

Set these separately using encrypted Vercel environment variables:

- `APP_KEY`: a new random Laravel encryption key for the online app. Do not reuse the local application's key.
- `POSTGRES_URL` (or `DB_URL` / `DATABASE_URL`): the connection string from the database integration.
- `COOLMETER_SETUP_TOKEN_HASH`: SHA256 of a cryptographically random setup token. Only this hash is stored, not the raw token.
- `APP_URL`: the final HTTPS app address.

The Composer `vercel` hook runs `scripts/vercel-build.php`, which validates that a hosted PostgreSQL URL and app key exist, then runs `php artisan migrate --force`. It refuses a deployment lacking permanent database configuration. No database administration or migration endpoint is exposed over HTTP.

The PHP entry point puts disposable framework caches and compiled views in `/tmp/coolmeter`. No app records are stored there. Static files are explicitly allowlisted; `.env`, SQLite databases, and PHP source files are never served as static assets. The deployment source should be private.

## First sign-in

The deployment operator generates a random setup token in memory and configures its hash as above. Open `https://YOUR-APP/setup/TOKEN` privately and choose your email/password yourself (at least 12 characters). It creates the single owner and signs you in. The setup invite becomes unusable as soon as an owner exists. There is no public registration endpoint.

Subsequent visits use `/login`. Passwords are hashed by Laravel; login attempts are throttled using the persistent database cache. Session cookies are encrypted/signed by Laravel and marked secure online. The journal's read, export, calculation, and mutation endpoints all require sign-in online. Keep your password in a password manager; email-based password recovery is not configured in this version.

## Verification

Run `php artisan test` locally: the tests use in-memory SQLite and include authentication, setup-invite validation, rate limiting, historical settings, and estimation workflows. PostgreSQL migrations and the actual PHP runtime must additionally be verified during a real deployment.

After deployment, check:

1. The public root redirects to `/login` and `/api/state` rejects anonymous access.
2. Static CSS, JS, manifest, and icons load over HTTPS.
3. After owner setup, a saved session survives page reload and is available on the phone.
4. Restart/redeploy does not erase the journal; the hosted database owns it.

The local SQLite journal is not automatically uploaded. Importing old records is a separate operation; keep a backup if you have started logging locally.

## Costs and hosting notes

No paid plan or custom domain is selected by the application. Review the provider's current free-tier limits before accepting any billing agreement. This project uses the community PHP runtime, not an officially maintained Vercel Laravel runtime.

References: https://github.com/vercel-community/php · https://vercel.com/docs/functions/runtimes · https://vercel.com/docs/marketplace-storage
