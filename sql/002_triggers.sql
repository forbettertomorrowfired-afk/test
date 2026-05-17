-- AtomQuest — 002_triggers.sql
-- Audit triggers + single-active-cycle enforcement

BEGIN;

-- =============================================================
-- Prevent DELETE/UPDATE on audit_log (append-only)
-- =============================================================
CREATE OR REPLACE FUNCTION fn_audit_log_immutable()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'audit_log is append-only. UPDATE and DELETE are forbidden.';
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_log_immutable ON audit_log;
CREATE TRIGGER trg_audit_log_immutable
    BEFORE UPDATE OR DELETE ON audit_log
    FOR EACH ROW EXECUTE FUNCTION fn_audit_log_immutable();

-- =============================================================
-- Enforce single active appraisal cycle
-- =============================================================
CREATE OR REPLACE FUNCTION fn_single_active_cycle()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.is_active = TRUE THEN
        UPDATE appraisal_cycles SET is_active = FALSE WHERE id != NEW.id AND is_active = TRUE;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_single_active_cycle ON appraisal_cycles;
CREATE TRIGGER trg_single_active_cycle
    BEFORE INSERT OR UPDATE ON appraisal_cycles
    FOR EACH ROW EXECUTE FUNCTION fn_single_active_cycle();

-- =============================================================
-- Auto-update updated_at on goal_sheets
-- =============================================================
CREATE OR REPLACE FUNCTION fn_update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_goal_sheets_updated ON goal_sheets;
CREATE TRIGGER trg_goal_sheets_updated
    BEFORE UPDATE ON goal_sheets
    FOR EACH ROW EXECUTE FUNCTION fn_update_timestamp();

DROP TRIGGER IF EXISTS trg_goals_updated ON goals;
CREATE TRIGGER trg_goals_updated
    BEFORE UPDATE ON goals
    FOR EACH ROW EXECUTE FUNCTION fn_update_timestamp();

-- =============================================================
-- Enforce max 8 active (non-deleted) goals per goal sheet
-- =============================================================
CREATE OR REPLACE FUNCTION fn_max_goals_per_sheet()
RETURNS TRIGGER AS $$
DECLARE
    goal_count INT;
BEGIN
    IF NEW.is_deleted = FALSE THEN
        SELECT COUNT(*) INTO goal_count
        FROM goals
        WHERE goal_sheet_id = NEW.goal_sheet_id
          AND is_deleted = FALSE
          AND id != COALESCE(NEW.id, 0);

        IF goal_count >= 8 THEN
            RAISE EXCEPTION 'Maximum 8 goals per goal sheet. Currently have %.', goal_count;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_max_goals ON goals;
CREATE TRIGGER trg_max_goals
    BEFORE INSERT OR UPDATE ON goals
    FOR EACH ROW EXECUTE FUNCTION fn_max_goals_per_sheet();

COMMIT;
