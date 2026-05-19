# NexusSync - Goal Setting & Tracking Portal

A functional, audit-ready web portal for employee goal setting, manager approval, and quarterly achievement tracking.

## Tech Stack

- **Backend**: PHP 8.2 (no framework, no Composer)
- **Database**: PostgreSQL 15
- **Frontend**: Vanilla HTML/CSS/JS + Chart.js + Bootstrap CSS (select pages)
- **Hosting**: Railway (PHP service + PostgreSQL addon)

## Quick Start (Local Development)

### Prerequisites
- PHP 8.2+ with `pdo_pgsql` extension
- PostgreSQL 15+

### Setup
```bash
# 1. Create database
createdb atomquest

# 2. Run migrations
DATABASE_URL="postgresql://postgres:postgres@localhost:5432/atomquest" php sql/migrate.php

# 3. Start PHP dev server
cd public
php -S localhost:8080
```

### Demo Credentials

| Role | Email | Password |
|---|---|---|
| Admin | admin@atom.local | admin123 |
| Manager | mgr@atom.local | manager123 |
| Employee | emp@atom.local | employee123 |

## Features

### Phase 1 - Goal Creation & Approval
- Employee goal sheet creation with thrust area, UoM, targets, weightage
- Live weightage validation (must equal 100%, min 10% per goal, max 8 goals)
- Manager inline editing and approval workflow
- Shared/departmental goals with read-only fields + synced achievements
- Goal sheet locking on approval

### Phase 2 - Achievement Tracking
- Quarterly achievement entry with auto-computed scores
- 6 UoM types: Numeric Min/Max, % Min/Max, Timeline, Zero-based
- Late entry flagging
- Manager check-in with structured comments

### Phase 3 - Admin & Reporting
- Cycle management, user management, org hierarchy
- Goal unlock with mandatory reason + re-approval flow
- CSV export of achievement reports
- Paginated audit trail with field-level change tracking

### Bonus Features
- Escalation rules with manual trigger and de-duplicated notifications
- In-app notification system with bell icon
- Chart.js analytics (score distribution)
- Demo role switcher for judges

## Architecture

```
Browser → Apache + PHP (Railway) → PostgreSQL (Railway)
```

- Server-rendered PHP pages with PRG pattern
- PostgreSQL-backed sessions (survives Railway redeploys)
- CSRF protection on all forms
- Prepared statements everywhere (SQL injection prevention)
- Optimistic locking for concurrent edit detection

## Project Structure

```
atomhack/
├── public/          ← Document root (Apache serves from here)
│   ├── index.php    ← Login
│   ├── employee/    ← Employee pages
│   ├── manager/     ← Manager pages
│   ├── admin/       ← Admin pages
│   └── api/         ← AJAX endpoints
├── includes/        ← PHP business logic (not web-accessible)
├── sql/             ← Migrations (numbered, idempotent)
├── docs/            ← Architecture, data dictionary
└── Dockerfile       ← Railway deployment
```
