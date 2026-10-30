# CoolMeter

**Your cooling, accounted for.** A shared home air conditioner energy journal built with Laravel for PC and Android browsers.

[Open the live app](https://coolmeter.vercel.app) · [Deployment guide](DEPLOYMENT.md)

CoolMeter estimates the electricity used by an **LG LA100ENT 1 HP inverter window AC** and its cost using a dated Davao Light residential tariff. Track a live session or enter start/end times, record room conditions and when Eco was enabled, and review your cooling habits over time.

## Features

- Live Start/Stop tracking that survives closing the browser.
- Manual entry with dates for overnight sessions and correction of forgotten timers.
- Room warmth, target temperature, fan speed, and session notes.
- An Eco-time popup during manual entry and an Eco button during live sessions.
- Estimated session kWh and peso cost, with an illustrative scenario range.
- Daily/monthly totals and a seven-day chart, split at Philippine midnight.
- Session details, editing, deletion, month filtering, and CSV export.
- Editable tariff and power assumptions, saved separately with each session.
- Separate household sign-ins with owner-created, email-bound invitation links that expire after 48 hours.
- One shared AC timer and session history, with owner-only invitation and access management.
- Larger text, clearer contrast, phone layouts that fit the screen, and saved light/dark theme preferences.
- A blue/slate dark theme and an AC illustration that follows the selected temperature, including the running session's target after reload.
- Initial journal data included in the page, cached public assets, and connection recovery for read requests.
- Responsive layout and a PWA manifest for supported Android browsers.

## Stack

| Component | Technology |
| --- | --- |
| Backend | Laravel 12 / PHP |
| Frontend | Blade, CSS, vanilla JavaScript |
| Local database | SQLite |
| Hosted database | PostgreSQL / Neon |
| Hosting | Vercel with a community PHP runtime |
| Time zone | Asia/Manila |

The UI uses local assets. No Node build is needed to run the application.

## Run locally

Requirements: **PHP 8.2+**, Composer, and the Laravel PHP extensions including PDO SQLite, mbstring, OpenSSL, DOM, and fileinfo. ZIP support is useful for installing Composer packages.

```sh
git clone https://github.com/joreenn/CoolMeter.git
cd CoolMeter
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan serve
```

Open [localhost:8000](http://localhost:8000). The example environment enables local home mode without sign-in; keep that mode on a trusted local network.

### Windows and Android over home Wi-Fi

After installation, double-click **Start-CoolMeter.cmd**. It starts the server in the background and prints both the PC and local network addresses. Open the printed network address on an Android connected to the same Wi-Fi. Keep the PC running. Use **Stop-CoolMeter.ps1** to stop this background server.

Alternatively:

```sh
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

The launcher does not change Windows Firewall rules. If needed, allow PHP on your private home network. Do not expose the unauthenticated local mode to the internet.

## Online access

The hosted app is available at **[coolmeter.vercel.app](https://coolmeter.vercel.app)** and uses a persistent PostgreSQL database, so your PC can be off. Public registration is disabled. The live app requires only your CoolMeter sign-in; preview deployments also require Vercel access.

In **Settings → One AC, together**, the owner enters a family member's email and creates an invitation link. Share that link directly with that person. They choose their own password and can view, start, stop, edit, and export the same AC sessions. A member can also record Eco mode and change estimation settings. The owner can cancel pending invitations or remove a member's access; cooling history is kept.

Invitations are single use and expire after 48 hours. Creating another invitation for the same email cancels the earlier link. While the app is open, running sessions refresh about every 15 seconds; an idle journal refreshes about every 30 seconds. Returning to the app refreshes it too. Sessions remain on the server when either person closes the app.

Use the **Light theme / Dark theme** button at the top of the app. The choice is remembered on that browser; the first visit follows the device theme.

For your own deployment, configure a hosted database, a separate application key, and an owner setup-token hash. See [DEPLOYMENT.md](DEPLOYMENT.md). Never commit real credentials or a setup invitation. Pushing source to this repository does not by itself redeploy the existing Vercel app; connect a Git deployment workflow or deploy explicitly.

On Android, use **Install app** or **Add to Home screen** where supported. Full PWA support requires HTTPS or localhost. Offline edits are not queued; the server must be reachable to save records.

## How the estimate works

The supplied AC label confirms **1,030 W normal electrical input** and **1,250 W maximum input**. Horsepower and cooling capacity are not used as electrical wattage.

```text
Energy (kWh) = sum of (electrical input in watts × segment hours) / 1,000
Cost (PHP)   = estimated kWh × saved electricity rate
```

1. **Initial cooling:** assumes 1,030 W for an adjustable cooldown period. Defaults are 10 minutes for a comfortable room, 20 for warm, and 30 for very hot.
2. **Settled operation:** assumes 670 W after cooldown. This is an uncalibrated modelling assumption, not an LG specification.
3. **Eco timing:** splits the session at the recorded switch time. Normal and Eco settled input both default to 670 W because this unit's Eco savings have not been measured. Adjust them using measured average power when available.
4. **Scenario range:** defaults to ±35%, with the upper scenario capped at 1,250 W × duration. This is not a statistical confidence interval or a guaranteed bound; actual consumption can fall outside it.

Temperature and fan speed are recorded as context without assigning unverified savings percentages. Each session preserves the tariff and assumptions used when it was created. A meter measuring the AC's own consumption is needed for actual kWh.

### Davao Light tariff

The initial seed rate is **₱13.33/kWh** for **October 11–November 10, 2026**, based on [News Fort's report of Davao Light's advisory](https://www.newsfortph.com/local-news/davao-light-residential-rate-rises-by-0-09-per-kwh-for-october-billing). It is labelled as reported and is not an automatically updated current rate.

Update the rate and billing dates in Settings from your bill or the [official Davao Light rates page](https://www.davaolight.com/customer-services/faqs-rates). Sessions outside the saved period are flagged. Cost estimates concern the AC; fixed charges and household discounts can affect your actual bill.

## Checks

```sh
php artisan test
node --check public/app.js
php artisan view:cache
```

The tests use a separate in-memory SQLite database and cover calculations, Eco phase integration, overnight/month boundaries, validation, overlap prevention, historical assumptions, timers, sign-in, setup invites, and the Neon connection adapter. Node is only needed for the optional JavaScript syntax check above.

## Project map

| Path | Purpose |
| --- | --- |
| `app/Services/EnergyEstimator.php` | Energy and cost model |
| `app/Http/Controllers/` | Session, settings, and authentication endpoints |
| `resources/views/` | Dashboard and sign-in pages |
| `public/app.js` / `public/app.css` | Browser interactions and layout |
| `config/cooling.php` | AC specifications and initial model/tariff defaults |
| `database/migrations/` | Database schema |
| `api/index.php` / `vercel.json` | Serverless adapter and deployment routing |
| `tests/` | Calculation and workflow verification |

## Data and backups

Local journal data lives in `database/database.sqlite`. Stop the local server before copying that file for a full backup. CSV export provides a readable session summary. The hosted app stores its records in PostgreSQL and has a separate journal from a local installation.

Secrets, local databases, dependencies, session files, logs, and framework caches are excluded from version control. Install dependencies with Composer after cloning.
