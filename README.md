# The Real CRM

A Filament app for a multi-office realty team: agents work leads and sales, location admins run their office, and a super admin sees the whole firm.

Open [http://the-real-crm.test/login](http://the-real-crm.test/login) (Herd parks `~/Herd`, so that URL is the live site). Sign in with email + password. The panel lives at `/`, so login is `/login`.

## Named demo accounts

All four use **`Password123`**.

| Role | Email | What you should see |
|---|---|---|
| Super admin (firm-wide) | `superadmin@example.com` | All offices. Analytics, Agents, Locations, and lookup settings. Can create location admins and other super admins. |
| Downtown admin | `downtownadmin@example.com` | Downtown only. Analytics and Agents for that office, plus lead/sale status and source settings. |
| Westside admin | `westsideadmin@example.com` | Same as Downtown, scoped to Westside. |
| Agent | `demo@example.com` | Personal dashboard (your sales, commission, and assigned leads). Can create/edit Downtown leads and sales. No Analytics, Agents, or Settings. |

Seeded sample data: **110 leads** and **44 sales** across Downtown and Westside.

## Extra seeded agents

`php artisan db:seed` also creates **8 filler agents** (4 Downtown, 4 Westside) with random names and emails. Their password is **`password`**, not `Password123`. Emails change if you re-seed.

## What the product does

**Dashboard (everyone)**
Year-to-date closed sales, volume, net commission, pending pipeline, lead counts (assigned / working / lost / closed %), and a “My leads” table. Stats link into the matching Leads or Sales tabs.

**Leads**
Contact + buyer/seller/rental/other type, status, source, assigned agents, and property prefs. Tabs: All / Working / Lost / Closed. **Create related lead** copies the person and flips buyer ↔ seller so one client can be two opportunities (one sale each).

**Sales**
One sale per lead. Price and commission % auto-calc gross commission, then a **$250 fixed brokerage fee** (configurable), then agent splits that must total 100%. Closed sales require a close date.

**Analytics (admins)**
Office- or firm-scoped sales/leads totals, per-agent performance, and lead-source revenue.

**Settings**
- Super admin: **Locations** (Downtown, Westside).
- Any admin: **Agents**, **Lead statuses**, **Sale statuses**, **Lead sources**.
Location admins can only manage people in their own office.

**Roles in short**
Agents stay in their office’s records. Location admins see that office’s everything. Super admin has no office and sees all of it.

To reset demo data:

```bash
php artisan migrate:fresh --seed
```
