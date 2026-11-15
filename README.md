# The Real CRM

[![CI](https://github.com/plwebdesigns/the-real-crm/actions/workflows/ci.yml/badge.svg)](https://github.com/plwebdesigns/the-real-crm/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Filament 5](https://img.shields.io/badge/Filament-5-FDAE4B)
![Livewire 4](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire&logoColor=white)
![Tailwind CSS 4](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)

**Live demo:** [https://therealcrm.longoapps.com](https://therealcrm.longoapps.com) (redirects to `/login`). Demo data resets nightly at 3:00 AM Eastern, so click around and edit freely.

A Laravel and Filament CRM for a multi-office realty team. Agents work leads and sales in their office, location admins run that office, and a super admin sees the whole firm.

The panel lives at `/`. Sign in at `/login`.

## Screenshots

Agent dashboard: year-to-date sales, commission, pipeline, and assigned leads. The sidebar is Dashboard, Leaderboard, Leads, Sales, and Tasks.

![Agent dashboard for demo@example.com](docs/screenshots/agent-dashboard.png)

Leads, with All / Working / Lost / Closed tabs.

![Leads list](docs/screenshots/leads.png)

Sale form. Price and commission percent calculate gross commission, then the brokerage fee, then each agent's net. Seeded sales are one agent at 100%. Splits have to total 100%.

![Sale form with commission split](docs/screenshots/sale-commission.png)

Super admin analytics for every office, with a period and a location filter.

![Firm analytics](docs/screenshots/analytics.png)

Downtown admin's agent list. All six people are Downtown. Settings for this role is Agents, lead statuses, sale statuses, and lead sources. Locations is on the super admin sidebar.

![Downtown agents, scoped to one office](docs/screenshots/office-scope.png)

## Demo accounts

These accounts work on the [live demo](https://therealcrm.longoapps.com/login). All four use **`Password123`**.

| Role | Email | What you should see |
|---|---|---|
| Super admin (firm-wide) | `superadmin@example.com` | All offices. Analytics, Agents, Locations, and lookup settings. Can create location admins and other super admins. |
| Downtown admin | `downtownadmin@example.com` | Downtown only. Analytics and Agents for that office, plus lead/sale status and source settings. |
| Westside admin | `westsideadmin@example.com` | Same as Downtown, scoped to Westside. |
| Agent | `demo@example.com` | Personal dashboard (your sales, commission, and assigned leads). Can create and edit Downtown leads and sales. The sidebar has no Analytics, Agents, or Settings. |

Seeded sample data: **550 leads** and **132 sales** across Downtown and Westside. Each of the 11 office users (the demo agent, both location admins, and 8 filler agents) receives 50 leads and 12 sales.

## Extra seeded agents

`php artisan db:seed` also creates **8 filler agents** (4 Downtown, 4 Westside) with random names and emails. Their password is **`password`**. Emails change when you seed a fresh database.

## What the product does

**Dashboard (everyone)**
Year-to-date closed sales, volume, net commission, pending pipeline, lead counts (assigned / working / lost / closed %), and a "My leads" table. Stats link into the matching Leads or Sales tabs.

**Leads**
Contact plus buyer, seller, rental, or other type, status, source, assigned agents, and property preferences. Tabs: All / Working / Lost / Closed. **Create related lead** copies the person and flips buyer and seller (rental and other stay the same) so one client can be two opportunities, one sale each. Leads keep an activity log and uploaded documents.

**Sales**
One sale per lead. Price and commission percent calculate gross commission, then a **$250 fixed brokerage fee** (`BROKERAGE_FEE` and `BROKERAGE_FEE_TYPE`, fixed or percent), then agent splits that must total 100%. Closed sales require a close date. Sales can hold documents.

**Leaderboard**
Top agents by closed volume for the selected period. A super admin can include every office. Everyone else sees their own office.

**Tasks**
Call, email, and text follow-ups on a lead or sale. `tasks:send-due-reminders` is scheduled every 15 minutes. Reminders are queued (the default queue is `database`): an in-app notification for the assignee, and an email when they have opted in. The default mailer is `log`.

**Analytics (admins)**
Office- or firm-scoped sales and lead totals, a closed-volume chart, per-agent performance, and lead-source revenue. Super admins also get an office comparison and a location filter.

**Settings**
- Super admin: **Locations** (Downtown, Westside).
- Any admin: **Agents**, **Lead statuses**, **Sale statuses**, **Lead sources**.

Location admins manage people in their own office.

**Roles in short**
Agents stay in their office's records. Location admins see that office. The super admin has no office and sees all of them.

## Tech stack

Versions below are the ones locked in `composer.lock` and `package-lock.json`.

| | |
|---|---|
| PHP | 8.4.1+ (`composer.json` requires `^8.4.1`, the same floor as this lockfile). |
| Laravel | 13.29 |
| Filament | 5.7 |
| Livewire | 4.4 |
| Tailwind CSS | 4.3, built with Vite 8.2 |
| Database | MySQL or MariaDB, as in `.env.example`. SQLite works locally. PHPUnit uses in-memory SQLite. |
| Tests and style | PHPUnit 12.5, Laravel Pint 1.30 |

## Engineering notes

**Office scoping.** `User::canAccessLocation()` is the check. `Lead`, `Sale`, `Task`, and `User` expose a `visibleTo` scope, and Filament resources apply it in `app/Filament/Concerns/ScopesRecordsToLocation.php`, so lists never include another office. Policies enforce the same boundary on a single record: `app/Policies/Concerns/AllowsLocationMembers.php` for leads and sales, `app/Policies/UserPolicy.php` for agents, and `app/Policies/Concerns/AllowsSuperAdministrators.php` for locations. A super admin has a null `location_id` and skips the office filter. `tests/Feature/Filament/LocationIsolationTest.php` covers cross-office access.

**Commission.** `Sale::grossCommissionFor()`, `brokerageFeeFor()`, and `remainingCommissionFor()` in `app/Models/Sale.php` turn price and percent into the pool left after the brokerage fee. `SaleUser::netCommissionFor()` in `app/Models/SaleUser.php` writes each agent's net when the pivot is saved. The sale form in `app/Filament/Resources/Sales/Schemas/SaleForm.php` recalculates as you type and rejects splits that do not total 100%. `tests/Feature/Models/SaleTest.php` and `tests/Feature/Filament/SaleResourceTest.php`.

**Analytics.** `app/Filament/Pages/Analytics.php` is limited to admins. Widgets for firm totals, closed volume, offices, lead type, agent performance, and lead source live under `app/Filament/Widgets/`. `tests/Feature/Filament/AnalyticsTest.php`.

## Architecture

Roles are columns on [`User`](app/Models/User.php). `is_super_admin` has a null `location_id` and sees every office. A location admin is `is_admin` with an office. An agent has neither flag. Lead status, sale status, and lead source are firm-wide `name` / `slug` rows. Seeded slugs are in [`LookupSeeder`](database/seeders/LookupSeeder.php). A lead is working when its status is `contacted` or `qualified`, and closed when its sale's status is `closed`.

```mermaid
erDiagram
    Location ||--o{ Lead : office
    Location ||--o{ Sale : office
    Location |o--o{ User : office
    LeadStatus ||--o{ Lead : status
    LeadSource ||--o{ Lead : source
    Lead ||--o| Sale : "at most one"
    SaleStatus ||--o{ Sale : status
    User }o--o{ Lead : lead_user
    Sale ||--o{ SaleUser : split
    User ||--o{ SaleUser : agent
    User {
        boolean is_admin
        boolean is_super_admin
        int location_id "nullable"
    }
    Sale {
        decimal gross_commission
        decimal brokerage_fee
    }
    SaleUser {
        int commission_percent
        decimal net_commission
    }
```

**Office and roles.** List queries opt in. [`LeadResource`](app/Filament/Resources/Leads/LeadResource.php), [`SaleResource`](app/Filament/Resources/Sales/SaleResource.php), [`TaskResource`](app/Filament/Resources/Tasks/TaskResource.php), and [`UserResource`](app/Filament/Resources/Users/UserResource.php) use [`ScopesRecordsToLocation`](app/Filament/Concerns/ScopesRecordsToLocation.php), which calls `visibleTo()`. Analytics widgets call the same scope. [`User::canAccessLocation()`](app/Models/User.php) is the shared check: a super admin passes, and everyone else must share a non-null `location_id`. The panel is [`AppPanelProvider`](app/Providers/Filament/AppPanelProvider.php). [`Analytics::canAccess()`](app/Filament/Pages/Analytics.php) requires `is_admin`. [`LocationResource::canAccess()`](app/Filament/Resources/Locations/LocationResource.php) requires `is_super_admin`.

```mermaid
flowchart TD
    req["Signed-in request"] --> mw["Authenticate and EnsurePasswordIsSet"]
    mw --> resources["Lead, sale, task, and user resources"]
    resources --> scope["ScopesRecordsToLocation calls visibleTo"]
    scope --> super{"is_super_admin"}
    super -->|yes| allOffices["Every office"]
    super -->|no| office["Same office. Tasks use the lead's office"]
    office --> saleCheck{"Sale query for an agent"}
    saleCheck -->|yes| assigned["Also assignedTo this agent"]
    saleCheck -->|no| officeRows["Rows in that office"]
    allOffices --> record["View, update, or delete one record"]
    assigned --> record
    officeRows --> record
    record --> policy["Laravel policy"]
    policy --> leadTask["Lead and task: canAccessLocation"]
    policy --> salePol["Sale: same office, and admin or assigned"]
    policy --> userPol["User: super admin, or office admin in that office"]
    policy --> locPol["Location: is_super_admin"]
    policy --> lookupPol["Statuses and sources: is_admin"]
```

[`Lead::visibleTo()`](app/Models/Lead.php) and [`Task::visibleTo()`](app/Models/Task.php) return every lead and task in the office, so a location admin and an agent both see that pipeline. The dashboard stats and "My leads" table then narrow to the signed-in user's `leads()` and `sales()`. [`Sale::visibleTo()`](app/Models/Sale.php) shows an office admin every sale in the office, and an agent only sales with their `sale_user` row. [`SalePolicy`](app/Policies/SalePolicy.php) repeats that check on one record. [`LeadPolicy`](app/Policies/LeadPolicy.php) uses [`AllowsLocationMembers`](app/Policies/Concerns/AllowsLocationMembers.php). [`TaskPolicy`](app/Policies/TaskPolicy.php) follows the lead's office. [`UserPolicy`](app/Policies/UserPolicy.php) lets a super admin manage any user, and a location admin manage users in their office. [`LocationPolicy`](app/Policies/LocationPolicy.php) allows only a super admin. [`LeadStatusPolicy`](app/Policies/LeadStatusPolicy.php), [`SaleStatusPolicy`](app/Policies/SaleStatusPolicy.php), and [`LeadSourcePolicy`](app/Policies/LeadSourcePolicy.php) allow any admin. Downloads go through the same `view` ability: [`LeadDocumentController`](app/Http/Controllers/LeadDocumentController.php) and [`SaleDocumentController`](app/Http/Controllers/SaleDocumentController.php).

On save, [`CreateLead`](app/Filament/Resources/Leads/Pages/CreateLead.php) sets `location_id` from the signed-in agent or location admin, and [`EditLead`](app/Filament/Resources/Leads/Pages/EditLead.php) drops a submitted `location_id` so the office stays put. A [`Sale`](app/Models/Sale.php) copies `location_id` and `sale_type` from its lead. [`ConstrainsUserLocationFields`](app/Filament/Resources/Users/Concerns/ConstrainsUserLocationFields.php) keeps a location admin inside their office. Turning on `is_super_admin` also sets `is_admin` and clears `location_id`.

**Commission.** Saving a sale sets `gross_commission` to price times the commission percent, rounded to cents, then `brokerage_fee` from `config('app.brokerage_fee')`. [`config/app.php`](config/app.php) defaults that to 250 and `fixed`. [`BrokerageFeeType`](app/Enums/BrokerageFeeType.php) also allows `percent` of the gross. The fee is clamped to the range from 0 through the gross. [`SaleUser::netCommissionFor()`](app/Models/SaleUser.php) stores each agent's percent of gross minus fee, and a later change to price, percent, or fee re-saves those rows. [`SaleForm`](app/Filament/Resources/Sales/Schemas/SaleForm.php) requires at least one agent, integer splits from 1 to 100, and a total of 100. The agent list is `User::atLocation()`: the viewer's office, or the lead's office for a super admin. The sale's own commission percent must be from 1.0 to 6.0.

**Lead to sale.** `sales.lead_id` is unique in [`create_sales_table`](database/migrations/2026_08_31_155725_create_sales_table.php), and [`Lead::sale()`](app/Models/Lead.php) is `hasOne`. The sale form only offers leads that have no sale yet. **Create related lead** inserts another lead: [`CreateLead`](app/Filament/Resources/Leads/Pages/CreateLead.php) copies name, email, phone, source, office, and agents from the `related` query parameter, and [`SaleType::relatedType()`](app/Enums/SaleType.php) swaps buyer and seller. Rental and other stay as they are. Each lead still has at most one sale.

**Tests.** Policies: [`SalePolicyTest`](tests/Feature/SalePolicyTest.php), [`LeadPolicyTest`](tests/Feature/LeadPolicyTest.php), [`UserPolicyTest`](tests/Feature/UserPolicyTest.php), [`LocationPolicyTest`](tests/Feature/LocationPolicyTest.php), and [`LeadStatusPolicyTest`](tests/Feature/LeadStatusPolicyTest.php). Cross-office panel access: [`LocationIsolationTest`](tests/Feature/Filament/LocationIsolationTest.php). [`SaleTest`](tests/Feature/Models/SaleTest.php) covers `visibleTo` for an agent, an office admin, and a super admin, plus gross commission, the $250 fee, 50/50 nets, a fee clamped to the gross, and a price edit that rewrites nets. [`SaleResourceTest`](tests/Feature/Filament/SaleResourceTest.php) rejects a second sale on a lead, and agent splits unless they sum to 100. Related leads: [`LeadResourceTest`](tests/Feature/Filament/LeadResourceTest.php) and [`LeadTest`](tests/Feature/Models/LeadTest.php).

## Local setup

You need PHP 8.4.1 or newer (`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `zip`, plus `pdo_sqlite` or `pdo_mysql`), Composer 2, and Node.js 20 or newer.

These steps were run on PHP 8.4: SQLite for the app in the screenshots, and MariaDB for `migrate --seed`.

```bash
git clone https://github.com/plwebdesigns/the-real-crm.git
cd the-real-crm
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

### SQLite

```bash
touch database/database.sqlite
```

Set these in `.env`. SQLite reads `DB_DATABASE` and ignores `DB_HOST`. `COMPANY_NAME` is the name in the sidebar.

```dotenv
APP_NAME="The Real CRM"
COMPANY_NAME="The Real CRM"
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

```bash
php artisan migrate --seed
php artisan serve
```

Open [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login).

### MySQL or MariaDB

`.env.example` expects a database named `the_real_crm`. On Laravel Herd, user `root` with an empty password is enough, so you can leave the example `DB_*` values and run `php artisan migrate --seed`.

Ubuntu's MariaDB authenticates `root` through the system socket, so that empty password is rejected. Create a user and set `DB_USERNAME` and `DB_PASSWORD` to match:

```sql
CREATE DATABASE the_real_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm'@'127.0.0.1' IDENTIFIED BY 'a-password';
GRANT ALL PRIVILEGES ON the_real_crm.* TO 'crm'@'127.0.0.1';
```

```dotenv
APP_NAME="The Real CRM"
COMPANY_NAME="The Real CRM"
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=the_real_crm
DB_USERNAME=crm
DB_PASSWORD=a-password
```

```bash
php artisan migrate --seed
php artisan serve
```

### Herd

Herd is optional. Park the project, set `APP_URL` to the `*.test` host Herd assigns, and open that host instead of `php artisan serve`.

### Reset demo data

```bash
php artisan migrate:fresh --seed
```

## Tests

```bash
php artisan test
```

`phpunit.xml` points PHPUnit at an in-memory SQLite database, so the suite does not use the database from `.env`. Format PHP with `vendor/bin/pint`.
