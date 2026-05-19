-- NexusSync - 003_seed.sql
-- Demo data: 5 users (admin, manager, 3 employees), 1 cycle, thrust areas
-- All passwords: admin123 / manager123 / employee123

BEGIN;

-- Admin user (password: admin123)
INSERT INTO users (employee_id, name, email, password_hash, role, department)
VALUES ('ADM001', 'Admin User', 'admin@atom.local',
        '$2y$12$acoLy6e0FPAPoFy9qxCpHeHnRQmLVyhG3QbbV3H/esRJAgAC6AZn2',
        'admin', 'Human Resources')
ON CONFLICT (email) DO NOTHING;

-- Manager user (password: manager123)
INSERT INTO users (employee_id, name, email, password_hash, role, department, manager_id)
VALUES ('MGR001', 'Manager User', 'mgr@atom.local',
        '$2y$12$taHGMPQbwhlzw7H9tzZktuiXjcT347J0xTmV2dGnFbHN7rsDEd0Ui',
        'manager', 'Sales', 1)
ON CONFLICT (email) DO NOTHING;

-- Employee user (password: employee123)
INSERT INTO users (employee_id, name, email, password_hash, role, department, manager_id)
VALUES ('EMP001', 'Employee User', 'emp@atom.local',
        '$2y$12$ggOdeVODOiCBqx7j/Uo9VOBsgFp1AL6m1q7QPAdYTBRAu9v/JPrAS',
        'employee', 'Sales', 2)
ON CONFLICT (email) DO NOTHING;

-- Additional employees (password: password123)
INSERT INTO users (employee_id, name, email, password_hash, role, department, manager_id)
VALUES ('EMP002', 'Priya Sharma', 'priya@atom.local',
        '$2y$12$KHQ.nBQe1gjFsGL72CqIHeiaUgG7.Kb41RH/2WMN0pykOIcwdswpS',
        'employee', 'Sales', 2)
ON CONFLICT (email) DO NOTHING;

INSERT INTO users (employee_id, name, email, password_hash, role, department, manager_id)
VALUES ('EMP003', 'Rahul Verma', 'rahul@atom.local',
        '$2y$12$KHQ.nBQe1gjFsGL72CqIHeiaUgG7.Kb41RH/2WMN0pykOIcwdswpS',
        'employee', 'Engineering', 2)
ON CONFLICT (email) DO NOTHING;

-- Active appraisal cycle (FY 2026-27)
INSERT INTO appraisal_cycles (cycle_name, goal_setting_opens, goal_setting_closes,
    q1_opens, q1_closes, q2_opens, q2_closes, q3_opens, q3_closes, q4_opens, q4_closes, is_active)
VALUES ('FY 2026-27', '2026-05-01', '2026-06-30',
        '2026-07-01', '2026-07-31', '2026-10-01', '2026-10-31',
        '2027-01-01', '2027-01-31', '2027-03-01', '2027-04-30', TRUE)
ON CONFLICT DO NOTHING;

-- Thrust areas
INSERT INTO thrust_areas (name, department) VALUES
    ('Revenue Growth', NULL),
    ('Customer Satisfaction', NULL),
    ('Operational Efficiency', NULL),
    ('Safety & Compliance', NULL),
    ('People Development', NULL),
    ('Innovation & Technology', NULL),
    ('Cost Optimization', NULL),
    ('Quality Improvement', NULL)
ON CONFLICT DO NOTHING;

-- Default escalation rules
INSERT INTO escalation_rules (rule_name, trigger_type, delay_days, notify_target) VALUES
    ('Goal submission reminder', 'goal_not_submitted', 7, 'employee'),
    ('Goal approval reminder', 'not_approved', 5, 'manager'),
    ('Checkin completion reminder', 'checkin_pending', 7, 'employee'),
    ('Checkin escalation to HR', 'checkin_pending', 14, 'hr')
ON CONFLICT DO NOTHING;

COMMIT;
