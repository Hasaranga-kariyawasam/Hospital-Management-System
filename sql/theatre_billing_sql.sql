-- ============================================================
-- Theatre Billing — Extra columns + New table + Dummy data
-- Run in phpMyAdmin after importing hospital_db.sql
-- ============================================================

-- ── 1. Add theatre_charge column to theatre_operations ──────
ALTER TABLE theatre_operations
    ADD COLUMN IF NOT EXISTS theatre_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Fee charged for this operation';

-- ── 2. New table: theatre_billing ───────────────────────────
--    Links theatre_operations → billing_invoices
--    Tracks per-operation billing status independently

CREATE TABLE IF NOT EXISTS theatre_billing (
    theatre_billing_id  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    operation_id        INT UNSIGNED   NOT NULL,
    patient_id          INT UNSIGNED   NOT NULL,
    invoice_id          INT UNSIGNED   NULL COMMENT 'FK billing_invoices — set when invoice created',
    operation_charge    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    anaesthesia_charge  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    consumables_charge  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    total_charge        DECIMAL(10,2)  GENERATED ALWAYS AS
                            (operation_charge + anaesthesia_charge + consumables_charge) STORED,
    billing_status      ENUM('pending','invoiced','paid','waived')
                            NOT NULL DEFAULT 'pending',
    notes               TEXT           NULL,
    created_by          INT UNSIGNED   NULL COMMENT 'reception/admin user_id',
    created_at          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (theatre_billing_id),
    UNIQUE  KEY uq_operation (operation_id),
    KEY     idx_patient      (patient_id),
    KEY     idx_status       (billing_status),

    FOREIGN KEY fk_tb_op  (operation_id) REFERENCES theatre_operations(operation_id) ON DELETE CASCADE,
    FOREIGN KEY fk_tb_pat (patient_id)   REFERENCES patients(patient_id),
    FOREIGN KEY fk_tb_inv (invoice_id)   REFERENCES billing_invoices(invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. Dummy data: theatre_operations ───────────────────────
--    Uses doctor user_ids from the existing sample data

INSERT IGNORE INTO theatre_operations
    (patient_id, surgeon_id, anaesthetist_id, operation_type,
     theatre_number, scheduled_date, scheduled_time, status,
     theatre_charge, pre_op_notes, created_by)
SELECT
    p.patient_id,
    s.user_id   AS surgeon_id,
    a.user_id   AS anaesthetist_id,
    ops.op_type,
    ops.theatre_num,
    ops.sched_date,
    ops.sched_time,
    ops.op_status,
    ops.charge,
    ops.notes,
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
FROM (
    SELECT 1 AS rn, 'Appendectomy'          AS op_type, 1 AS theatre_num, '2026-05-10' AS sched_date, '08:00:00' AS sched_time, 'completed'   AS op_status, 45000.00 AS charge, 'Routine appendix removal'   AS notes
    UNION ALL
    SELECT 2, 'Knee Replacement',              2, '2026-05-11', '09:30:00', 'completed',  85000.00, 'Left knee total replacement'
    UNION ALL
    SELECT 3, 'Caesarean Section',             3, '2026-05-12', '10:00:00', 'completed',  55000.00, 'Elective C-section, 38 weeks'
    UNION ALL
    SELECT 4, 'Coronary Bypass',               1, '2026-05-13', '07:00:00', 'completed', 120000.00, 'Triple bypass surgery'
    UNION ALL
    SELECT 5, 'Cyst Removal',                  4, '2026-05-14', '11:00:00', 'scheduled',  18000.00, 'Minor cyst removal procedure'
    UNION ALL
    SELECT 6, 'Hernia Repair',                 1, '2026-05-15', '08:30:00', 'scheduled',  32000.00, 'Inguinal hernia mesh repair'
    UNION ALL
    SELECT 7, 'Gallbladder Removal',           2, '2026-05-16', '09:00:00', 'confirmed',  40000.00, 'Laparoscopic cholecystectomy'
    UNION ALL
    SELECT 8, 'Tonsillectomy',                 4, '2026-05-17', '13:00:00', 'confirmed',  22000.00, 'Bilateral tonsil removal'
) ops
JOIN patients p ON p.patient_id = (
    SELECT patient_id FROM patients ORDER BY patient_id LIMIT 1 OFFSET (ops.rn - 1)
)
JOIN users s ON s.user_id = (
    SELECT u.user_id FROM users u JOIN doctors d ON d.user_id = u.user_id
    WHERE u.status = 'active' ORDER BY u.user_id LIMIT 1 OFFSET ((ops.rn - 1) % 5)
)
JOIN users a ON a.user_id = (
    SELECT u.user_id FROM users u JOIN doctors d ON d.user_id = u.user_id
    WHERE u.status = 'active' ORDER BY u.user_id DESC LIMIT 1 OFFSET ((ops.rn - 1) % 3)
);

-- ── 4. Dummy theatre_billing rows ───────────────────────────
INSERT IGNORE INTO theatre_billing
    (operation_id, patient_id, operation_charge, anaesthesia_charge, consumables_charge, billing_status, notes, created_by)
SELECT
    o.operation_id,
    o.patient_id,
    o.theatre_charge                             AS operation_charge,
    ROUND(o.theatre_charge * 0.15, 2)            AS anaesthesia_charge,
    ROUND(o.theatre_charge * 0.08, 2)            AS consumables_charge,
    CASE o.status
        WHEN 'completed'  THEN 'invoiced'
        WHEN 'scheduled'  THEN 'pending'
        WHEN 'confirmed'  THEN 'pending'
        ELSE 'pending'
    END                                          AS billing_status,
    CONCAT('Auto-generated for ', o.operation_type) AS notes,
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
FROM theatre_operations o
WHERE o.theatre_charge > 0;
