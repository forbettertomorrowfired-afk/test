# AtomQuest Data Dictionary

## Tables Overview

| Table | Purpose | Rows (est.) |
|---|---|---|
| `users` | Employee, manager, admin accounts | ~100 |
| `appraisal_cycles` | FY cycle definitions with date windows | ~5 |
| `thrust_areas` | Org/dept-level strategic thrust areas | ~20 |
| `goal_sheets` | One per user per cycle; tracks status | ~100 |
| `goals` | Individual goals within a sheet | ~500 |
| `achievements` | Quarterly actuals per goal | ~2000 |
| `checkin_comments` | Manager check-in discussion entries | ~200 |
| `audit_log` | Append-only field-level change log | ~5000+ |
| `shared_goal_templates` | Reusable shared goal definitions | ~20 |
| `escalation_rules` | Configurable deadline alerts | ~5 |
| `escalation_log` | Triggered escalation records | ~50 |
| `notifications` | In-app notification queue | ~500 |
| `php_sessions` | PostgreSQL-backed PHP sessions | ~20 |
| `_migrations` | Applied migration tracker | ~4 |

## Column Details

### users
| Column | Type | Constraints | Notes |
|---|---|---|---|
| id | SERIAL | PK | Auto-increment |
| employee_id | VARCHAR(20) | UNIQUE, NOT NULL | e.g. EMP001 |
| name | VARCHAR(100) | NOT NULL | Full name |
| email | VARCHAR(150) | UNIQUE, NOT NULL | Login credential |
| password_hash | VARCHAR(255) | NOT NULL | bcrypt hash |
| role | VARCHAR(20) | CHECK | employee, manager, admin |
| department | VARCHAR(100) | DEFAULT '' | Organizational unit |
| manager_id | INT | FK → users(id) | Self-referential hierarchy |
| is_active | BOOLEAN | DEFAULT TRUE | Soft delete |
| created_at | TIMESTAMPTZ | DEFAULT NOW() | |

### appraisal_cycles
| Column | Type | Notes |
|---|---|---|
| id | SERIAL | PK |
| cycle_name | VARCHAR(50) | e.g. "FY 2026-27" |
| goal_setting_opens/closes | DATE | Goal entry window |
| q1..q4_opens/closes | DATE | Quarter windows |
| is_active | BOOLEAN | Only one can be TRUE (trigger-enforced) |

### goal_sheets
| Column | Type | Notes |
|---|---|---|
| id | SERIAL | PK |
| user_id | INT | FK → users |
| cycle_id | INT | FK → appraisal_cycles |
| status | VARCHAR(20) | draft → submitted → returned/approved → locked |
| version | INT | Optimistic locking counter |
| submitted_at | TIMESTAMPTZ | When employee submitted |
| approved_at | TIMESTAMPTZ | When manager approved |
| approved_by | INT | FK → users (the approving manager) |
| return_comment | TEXT | Reason for returning to draft |

### goals
| Column | Type | Notes |
|---|---|---|
| id | SERIAL | PK |
| goal_sheet_id | INT | FK → goal_sheets |
| thrust_area_id | INT | FK → thrust_areas |
| title | VARCHAR(255) | Goal statement |
| description | TEXT | Detailed description |
| uom_type | VARCHAR(20) | numeric_min/max, percent_min/max, timeline, zero |
| target_value | DECIMAL(12,2) | For numeric/percent types |
| target_date | DATE | For timeline type |
| weightage | INT | 10-100%, must sum to 100% per sheet |
| is_shared | BOOLEAN | True = pushed from shared template |
| shared_source_id | INT | FK → goals (primary owner's goal) |
| is_deleted | BOOLEAN | Soft delete |

### achievements
| Column | Type | Notes |
|---|---|---|
| id | SERIAL | PK |
| goal_id | INT | FK → goals |
| quarter | VARCHAR(5) | Q1, Q2, Q3, Q4 |
| actual_value | DECIMAL(12,2) | Actual achieved value |
| completion_date | DATE | For timeline goals |
| status | VARCHAR(20) | not_started, on_track, completed |
| computed_score | DECIMAL(5,2) | Auto-calculated, capped at 150% |
| is_late_entry | BOOLEAN | True if entered after quarter close |

### audit_log
| Column | Type | Notes |
|---|---|---|
| id | BIGSERIAL | PK |
| table_name | VARCHAR(50) | Which table was changed |
| record_id | INT | Row ID in that table |
| action | VARCHAR(20) | INSERT, UPDATE, DELETE, UNLOCK, SYNC |
| field_name | VARCHAR(100) | Which column changed |
| old_value / new_value | TEXT | Before/after values |
| changed_by | INT | FK → users |
| ip_address | INET | Client IP |
| reason | TEXT | Optional justification |

## Status Transitions

```
goal_sheets.status:
  draft → submitted → approved → locked
                    ↘ returned → draft (re-submit)
                    (admin unlock: locked → draft)
```

## Score Formulas

| UoM Type | Formula | Cap |
|---|---|---|
| numeric_min | (actual / target) × 100 | 150% |
| numeric_max | (target / actual) × 100 | 150% |
| percent_min | (actual / target) × 100 | 150% |
| percent_max | (target / actual) × 100 | 150% |
| timeline | on-time → 100%, late → 0% | 100% |
| zero | actual=0 → 100%, else → 0% | 100% |
