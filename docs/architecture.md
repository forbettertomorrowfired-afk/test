# AtomQuest Architecture

## System Overview

```
┌──────────────────────────────────────────────────┐
│                  Browser (Client)                 │
│    HTML + CSS + Vanilla JS + Chart.js             │
└──────────────────────┬───────────────────────────┘
                       │ HTTP/HTTPS
┌──────────────────────▼───────────────────────────┐
│            Apache 2.4 + PHP 8.2 (Railway)        │
│                                                   │
│  ┌─────────────────────────────────────────────┐ │
│  │  public/                 (Document Root)    │ │
│  │    index.php             ← Login/Auth       │ │
│  │    employee/*            ← Employee pages   │ │
│  │    manager/*             ← Manager pages    │ │
│  │    admin/*               ← Admin pages      │ │
│  │    api/*                 ← AJAX endpoints   │ │
│  └─────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────┐ │
│  │  includes/              (Not web-accessible)│ │
│  │    auth.php, csrf.php, security.php         │ │
│  │    db.php (PDO + PgSessionHandler)          │ │
│  │    functions.php (validation, scoring)      │ │
│  │    audit.php                                │ │
│  │    layout/header.php, footer.php            │ │
│  └─────────────────────────────────────────────┘ │
└──────────────────────┬───────────────────────────┘
                       │ PDO (prepared statements)
┌──────────────────────▼───────────────────────────┐
│              PostgreSQL 15+ (Railway)             │
│                                                   │
│  users, appraisal_cycles, goal_sheets, goals,    │
│  achievements, checkin_comments, audit_log,       │
│  thrust_areas, shared_goal_templates,            │
│  escalation_rules/log, notifications,            │
│  php_sessions, _migrations                       │
└──────────────────────────────────────────────────┘
```

## Security Layers

1. **CSRF** — Token in session, validated on every POST
2. **SQL Injection** — 100% prepared statements (PDO bound params)
3. **XSS** — All output via `h()` (htmlspecialchars)
4. **Session Fixation** — `session_regenerate_id(true)` on login
5. **Secure Headers** — CSP, X-Frame-Options, nosniff, SameSite
6. **Audit Trail** — Append-only audit_log with DB trigger
7. **Auth Guards** — `require_role()` on every page

## Key Design Decisions

| Decision | Rationale |
|---|---|
| No framework/ORM | Zero dependencies, fast boot, hackathon simplicity |
| PostgreSQL sessions | Survives Railway's ephemeral container redeploys |
| Optimistic locking | `version` column prevents lost updates |
| Numbered migrations | Simple, reliable, no Composer needed |
| Server-side rendering | No SPA build step, works immediately |
| Chart.js (local) | No CDN dependency, offline-capable |
