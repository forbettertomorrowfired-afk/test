-- NexusSync Goal Setting & Tracking Portal
-- 001_schema.sql - Core tables, indexes, constraints

BEGIN;

-- =============================================================
-- USERS
-- =============================================================
CREATE TABLE IF NOT EXISTS users (
    id              SERIAL PRIMARY KEY,
    employee_id     VARCHAR(20) UNIQUE NOT NULL,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20) NOT NULL CHECK (role IN ('employee', 'manager', 'admin')),
    department      VARCHAR(100) NOT NULL DEFAULT '',
    manager_id      INT REFERENCES users(id) ON DELETE SET NULL,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_users_manager_id ON users(manager_id);
CREATE INDEX idx_users_department ON users(department);
CREATE INDEX idx_users_role ON users(role);

-- =============================================================
-- APPRAISAL CYCLES
-- =============================================================
CREATE TABLE IF NOT EXISTS appraisal_cycles (
    id                      SERIAL PRIMARY KEY,
    cycle_name              VARCHAR(50) NOT NULL,
    goal_setting_opens      DATE NOT NULL,
    goal_setting_closes     DATE NOT NULL,
    q1_opens                DATE NOT NULL,
    q1_closes               DATE NOT NULL,
    q2_opens                DATE NOT NULL,
    q2_closes               DATE NOT NULL,
    q3_opens                DATE NOT NULL,
    q3_closes               DATE NOT NULL,
    q4_opens                DATE NOT NULL,
    q4_closes               DATE NOT NULL,
    is_active               BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- =============================================================
-- THRUST AREAS
-- =============================================================
CREATE TABLE IF NOT EXISTS thrust_areas (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    department  VARCHAR(100),          -- NULL = org-wide
    is_active   BOOLEAN NOT NULL DEFAULT TRUE
);

-- =============================================================
-- GOAL SHEETS
-- =============================================================
CREATE TABLE IF NOT EXISTS goal_sheets (
    id              SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    cycle_id        INT NOT NULL REFERENCES appraisal_cycles(id) ON DELETE CASCADE,
    status          VARCHAR(20) NOT NULL DEFAULT 'draft'
                        CHECK (status IN ('draft', 'submitted', 'returned', 'approved', 'locked')),
    version         INT NOT NULL DEFAULT 1,
    submitted_at    TIMESTAMPTZ,
    approved_at     TIMESTAMPTZ,
    approved_by     INT REFERENCES users(id) ON DELETE SET NULL,
    return_comment  TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(user_id, cycle_id)
);

CREATE INDEX idx_goal_sheets_user_id ON goal_sheets(user_id);
CREATE INDEX idx_goal_sheets_cycle_id ON goal_sheets(cycle_id);
CREATE INDEX idx_goal_sheets_status ON goal_sheets(status);
CREATE INDEX idx_goal_sheets_approved_by ON goal_sheets(approved_by);

-- =============================================================
-- GOALS
-- =============================================================
CREATE TABLE IF NOT EXISTS goals (
    id                  SERIAL PRIMARY KEY,
    goal_sheet_id       INT NOT NULL REFERENCES goal_sheets(id) ON DELETE CASCADE,
    thrust_area_id      INT NOT NULL REFERENCES thrust_areas(id),
    title               VARCHAR(255) NOT NULL,
    description         TEXT NOT NULL DEFAULT '',
    uom_type            VARCHAR(20) NOT NULL
                            CHECK (uom_type IN ('numeric_min','numeric_max','percent_min','percent_max','timeline','zero')),
    target_value        DECIMAL(12,2),
    target_date         DATE,
    weightage           INT NOT NULL CHECK (weightage >= 10 AND weightage <= 100),
    sort_order          INT NOT NULL DEFAULT 0,
    is_shared           BOOLEAN NOT NULL DEFAULT FALSE,
    shared_source_id    INT REFERENCES goals(id) ON DELETE SET NULL,
    is_deleted          BOOLEAN NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_goals_goal_sheet_id ON goals(goal_sheet_id);
CREATE INDEX idx_goals_thrust_area_id ON goals(thrust_area_id);
CREATE INDEX idx_goals_shared_source_id ON goals(shared_source_id);
CREATE INDEX idx_goals_is_deleted ON goals(is_deleted);

-- =============================================================
-- ACHIEVEMENTS
-- =============================================================
CREATE TABLE IF NOT EXISTS achievements (
    id                  SERIAL PRIMARY KEY,
    goal_id             INT NOT NULL REFERENCES goals(id) ON DELETE CASCADE,
    quarter             VARCHAR(5) NOT NULL CHECK (quarter IN ('Q1','Q2','Q3','Q4')),
    actual_value        DECIMAL(12,2),
    completion_date     DATE,
    status              VARCHAR(20) NOT NULL DEFAULT 'not_started'
                            CHECK (status IN ('not_started','on_track','completed')),
    computed_score      DECIMAL(5,2),
    is_late_entry       BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_by          INT REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(goal_id, quarter)
);

CREATE INDEX idx_achievements_goal_id ON achievements(goal_id);
CREATE INDEX idx_achievements_quarter ON achievements(quarter);
CREATE INDEX idx_achievements_updated_by ON achievements(updated_by);

-- =============================================================
-- CHECKIN COMMENTS
-- =============================================================
CREATE TABLE IF NOT EXISTS checkin_comments (
    id              SERIAL PRIMARY KEY,
    goal_sheet_id   INT NOT NULL REFERENCES goal_sheets(id) ON DELETE CASCADE,
    quarter         VARCHAR(5) NOT NULL CHECK (quarter IN ('Q1','Q2','Q3','Q4')),
    manager_id      INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    comment         TEXT NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_checkin_goal_sheet ON checkin_comments(goal_sheet_id);

-- =============================================================
-- AUDIT LOG
-- =============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id              BIGSERIAL PRIMARY KEY,
    table_name      VARCHAR(50) NOT NULL,
    record_id       INT NOT NULL,
    action          VARCHAR(20) NOT NULL,
    field_name      VARCHAR(100),
    old_value       TEXT,
    new_value       TEXT,
    changed_by      INT REFERENCES users(id) ON DELETE SET NULL,
    changed_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ip_address      INET,
    reason          TEXT
);

CREATE INDEX idx_audit_log_table_record ON audit_log(table_name, record_id);
CREATE INDEX idx_audit_log_changed_by ON audit_log(changed_by);
CREATE INDEX idx_audit_log_changed_at ON audit_log(changed_at);

-- =============================================================
-- SHARED GOAL TEMPLATES
-- =============================================================
CREATE TABLE IF NOT EXISTS shared_goal_templates (
    id                  SERIAL PRIMARY KEY,
    title               VARCHAR(255) NOT NULL,
    description         TEXT NOT NULL DEFAULT '',
    thrust_area_id      INT NOT NULL REFERENCES thrust_areas(id),
    uom_type            VARCHAR(20) NOT NULL
                            CHECK (uom_type IN ('numeric_min','numeric_max','percent_min','percent_max','timeline','zero')),
    target_value        DECIMAL(12,2),
    target_date         DATE,
    department          VARCHAR(100),
    created_by          INT NOT NULL REFERENCES users(id),
    primary_owner_id    INT REFERENCES users(id),
    cycle_id            INT NOT NULL REFERENCES appraisal_cycles(id),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- =============================================================
-- ESCALATION RULES (Bonus)
-- =============================================================
CREATE TABLE IF NOT EXISTS escalation_rules (
    id              SERIAL PRIMARY KEY,
    rule_name       VARCHAR(100) NOT NULL,
    trigger_type    VARCHAR(50) NOT NULL
                        CHECK (trigger_type IN ('goal_not_submitted','not_approved','checkin_pending')),
    delay_days      INT NOT NULL DEFAULT 7,
    notify_target   VARCHAR(20) NOT NULL
                        CHECK (notify_target IN ('employee','manager','hr')),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE
);

-- =============================================================
-- ESCALATION LOG (Bonus)
-- =============================================================
CREATE TABLE IF NOT EXISTS escalation_log (
    id              BIGSERIAL PRIMARY KEY,
    rule_id         INT NOT NULL REFERENCES escalation_rules(id),
    user_id         INT NOT NULL REFERENCES users(id),
    cycle_id        INT REFERENCES appraisal_cycles(id),
    quarter         VARCHAR(5),
    triggered_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    resolved_at     TIMESTAMPTZ,
    resolution_note TEXT
);

CREATE INDEX idx_escalation_log_user ON escalation_log(user_id);

-- =============================================================
-- NOTIFICATIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          BIGSERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type        VARCHAR(50) NOT NULL,
    message     TEXT NOT NULL,
    link        VARCHAR(255),
    is_read     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_is_read ON notifications(user_id, is_read);

-- =============================================================
-- MIGRATIONS TRACKER
-- =============================================================
CREATE TABLE IF NOT EXISTS _migrations (
    id          SERIAL PRIMARY KEY,
    filename    VARCHAR(255) UNIQUE NOT NULL,
    applied_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMIT;
