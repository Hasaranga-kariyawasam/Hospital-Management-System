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

