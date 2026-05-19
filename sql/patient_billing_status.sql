-- ============================================================
-- patient_billing_status  (NEW TABLE)
-- Links each appointment to its payment status.
-- Used by my_bills.php (Patient Portal → My Bills page).
-- ============================================================

CREATE TABLE IF NOT EXISTS `patient_billing_status` (
    `billing_status_id`  INT          NOT NULL AUTO_INCREMENT,
    `appointment_id`     VARCHAR(20)  NOT NULL,              -- FK → appointments.appointment_id
    `patient_id`         INT          NOT NULL,              -- FK → patients.patient_id  (denorm for quick lookup)
    `payment_status`     ENUM('Pending','Done','Cancelled')
                         NOT NULL DEFAULT 'Pending',
    `paid_amount`        DECIMAL(10,2)         DEFAULT 0.00,
    `payment_method`     ENUM('Cash','Card','Online Transfer','Bank Payment')
                         DEFAULT NULL,
    `payment_date`       DATE                  DEFAULT NULL,
    `receipt_number`     VARCHAR(30)           DEFAULT NULL,  -- e.g. RCP-2025-00042
    `received_by`        INT                   DEFAULT NULL,  -- FK → users.user_id (reception staff)
    `notes`              TEXT                  DEFAULT NULL,
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`billing_status_id`),
    UNIQUE  KEY `uq_appointment` (`appointment_id`),          -- one row per appointment
    KEY     `idx_patient`        (`patient_id`),
    KEY     `idx_status`         (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Sample seed data (matches screenshot example)
-- ============================================================

INSERT INTO `patient_billing_status`
    (`appointment_id`, `patient_id`, `payment_status`, `paid_amount`,
     `payment_method`, `payment_date`, `receipt_number`, `received_by`)
VALUES
    ('A01', 1, 'Done',    2500.00, 'Cash',   '2025-10-25', 'RCP-2025-00001', 3),
    ('A02', 1, 'Pending',    0.00,  NULL,     NULL,          NULL,            NULL),
    ('A03', 2, 'Cancelled',  0.00,  NULL,     NULL,          NULL,            NULL);

-- ============================================================
-- Notes
-- ============================================================
-- payment_status values:
--   Pending   → appointment booked, payment not yet collected
--   Done      → full payment received, receipt issued
--   Cancelled → appointment or payment cancelled
--
-- receipt_number format suggested: RCP-YYYY-NNNNN
--   Generate in PHP: 'RCP-' . date('Y') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT)
--
-- received_by references the reception staff (users.user_id)
--   who recorded the payment — used for audit trail.
--
-- When a payment is recorded by Reception:
--   UPDATE patient_billing_status
--   SET payment_status  = 'Done',
--       paid_amount     = ?,
--       payment_method  = ?,
--       payment_date    = CURDATE(),
--       receipt_number  = ?,
--       received_by     = ?
--   WHERE appointment_id = ?;
-- ============================================================
