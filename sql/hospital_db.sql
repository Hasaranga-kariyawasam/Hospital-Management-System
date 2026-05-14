-- ============================================================
-- hospital_db — Core Schema (stub tables for auth + registration)
-- Group 05 | ICT1242 Web Development Practicum
-- Run this in phpMyAdmin or MySQL CLI AFTER creating the database:
--   CREATE DATABASE hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ============================================================

USE hospital_db;

-- ── users (central identity for all roles) ──────────────────
CREATE TABLE IF NOT EXISTS users (
    user_id       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(120)     NOT NULL,
    email         VARCHAR(180)     NOT NULL UNIQUE,
    password_hash VARCHAR(255)     NOT NULL,
    role          ENUM('admin','doctor','reception','pharmacist','patient','dispatcher','driver') NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    staff_id      VARCHAR(40)      NULL,
    department    VARCHAR(100)     NULL,
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    INDEX idx_email (email),
    INDEX idx_role  (role)
) ENGINE=InnoDB;

-- ── patients (extended profile for patient users) ────────────
CREATE TABLE IF NOT EXISTS patients (
    patient_id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED  NOT NULL,
    nic               VARCHAR(20)   NOT NULL UNIQUE,
    dob               DATE          NOT NULL,
    gender            ENUM('male','female','other') NOT NULL,
    blood_type        ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NULL,
    phone             VARCHAR(20)   NOT NULL,
    address           TEXT          NULL,
    emergency_contact VARCHAR(120)  NULL,
    registered_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (patient_id),
    UNIQUE  KEY uk_user   (user_id),
    UNIQUE  KEY uk_nic    (nic),
    FOREIGN KEY fk_pat_user (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── doctors (extended profile for doctor users) ─────────────
CREATE TABLE IF NOT EXISTS doctors (
    doctor_id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED  NOT NULL,
    specialization   VARCHAR(120)  NOT NULL,
    qualifications   TEXT          NULL,
    license_number   VARCHAR(60)   NULL,
    consultation_fee DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    PRIMARY KEY (doctor_id),
    UNIQUE  KEY uk_doc_user (user_id),
    FOREIGN KEY fk_doc_user (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── appointments ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS appointments (
    appointment_id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    patient_id       INT UNSIGNED  NOT NULL,
    doctor_id        INT UNSIGNED  NOT NULL,
    appt_date        DATE          NOT NULL,
    appt_time        TIME          NOT NULL,
    source           ENUM('online','opd') NOT NULL DEFAULT 'online',
    status           ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    ref_number       VARCHAR(20)   NOT NULL UNIQUE,
    notes            TEXT          NULL,
    booked_by        INT UNSIGNED  NULL COMMENT 'user_id of staff if OPD walk-in',
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (appointment_id),
    INDEX idx_appt_date   (appt_date),
    INDEX idx_appt_doctor (doctor_id),
    INDEX idx_appt_patient(patient_id),
    FOREIGN KEY fk_appt_pat (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY fk_appt_doc (doctor_id)  REFERENCES doctors(doctor_id)
) ENGINE=InnoDB;

-- ── rooms ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rooms (
    room_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    room_number   VARCHAR(10)   NOT NULL UNIQUE,
    room_type     ENUM('semi_private','private','children','icu') NOT NULL,
    floor         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_available  TINYINT(1)    NOT NULL DEFAULT 1,
    daily_rate    DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    PRIMARY KEY (room_id),
    INDEX idx_room_type (room_type)
) ENGINE=InnoDB;

-- ── admissions ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admissions (
    admission_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    patient_id         INT UNSIGNED  NOT NULL,
    room_id            INT UNSIGNED  NOT NULL,
    doctor_id          INT UNSIGNED  NOT NULL,
    admitted_by        INT UNSIGNED  NOT NULL COMMENT 'reception user_id',
    admission_date     DATE          NOT NULL,
    discharge_date     DATE          NULL,
    dietary_notes      TEXT          NULL,
    status             ENUM('admitted','discharged') NOT NULL DEFAULT 'admitted',
    PRIMARY KEY (admission_id),
    FOREIGN KEY fk_adm_pat  (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY fk_adm_room (room_id)    REFERENCES rooms(room_id),
    FOREIGN KEY fk_adm_doc  (doctor_id)  REFERENCES doctors(doctor_id)
) ENGINE=InnoDB;

-- ── billing_invoices ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS billing_invoices (
    invoice_id     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    patient_id     INT UNSIGNED   NOT NULL,
    total_amount   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    paid_amount    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    balance        DECIMAL(10,2)  GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    status         ENUM('open','settled') NOT NULL DEFAULT 'open',
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (invoice_id),
    FOREIGN KEY fk_inv_pat (patient_id) REFERENCES patients(patient_id)
) ENGINE=InnoDB;

-- ── billing_items ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS billing_items (
    item_id       INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    invoice_id    INT UNSIGNED   NOT NULL,
    description   VARCHAR(180)   NOT NULL,
    category      ENUM('consultation','room','theatre','pharmacy','lab','misc') NOT NULL DEFAULT 'misc',
    unit_price    DECIMAL(8,2)   NOT NULL,
    quantity      SMALLINT       NOT NULL DEFAULT 1,
    line_total    DECIMAL(10,2)  GENERATED ALWAYS AS (unit_price * quantity) STORED,
    added_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    FOREIGN KEY fk_item_inv (invoice_id) REFERENCES billing_invoices(invoice_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── payments ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    payment_id     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    invoice_id     INT UNSIGNED   NOT NULL,
    amount         DECIMAL(10,2)  NOT NULL,
    payment_type   ENUM('full','advance') NOT NULL DEFAULT 'full',
    payment_method ENUM('cash','card','insurance','bank_transfer') NOT NULL DEFAULT 'cash',
    receipt_number VARCHAR(20)    NOT NULL UNIQUE,
    received_by    INT UNSIGNED   NOT NULL COMMENT 'reception user_id',
    paid_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    FOREIGN KEY fk_pay_inv (invoice_id) REFERENCES billing_invoices(invoice_id)
) ENGINE=InnoDB;

-- ── pharmacy_drugs ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pharmacy_drugs (
    drug_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    drug_name     VARCHAR(120)  NOT NULL,
    category      VARCHAR(80)   NOT NULL,
    unit          VARCHAR(30)   NOT NULL DEFAULT 'tablet',
    unit_price    DECIMAL(8,2)  NOT NULL,
    stock_qty     INT           NOT NULL DEFAULT 0,
    reorder_level INT           NOT NULL DEFAULT 10,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (drug_id)
) ENGINE=InnoDB;

-- ── prescriptions ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS prescriptions (
    prescription_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    appointment_id   INT UNSIGNED  NOT NULL,
    drug_id          INT UNSIGNED  NOT NULL,
    dosage           VARCHAR(80)   NOT NULL,
    frequency        VARCHAR(80)   NOT NULL,
    duration_days    SMALLINT      NOT NULL DEFAULT 7,
    status           ENUM('pending','dispensed') NOT NULL DEFAULT 'pending',
    dispensed_by     INT UNSIGNED  NULL COMMENT 'pharmacist user_id',
    dispensed_at     TIMESTAMP     NULL,
    PRIMARY KEY (prescription_id),
    FOREIGN KEY fk_rx_appt (appointment_id) REFERENCES appointments(appointment_id),
    FOREIGN KEY fk_rx_drug (drug_id)        REFERENCES pharmacy_drugs(drug_id)
) ENGINE=InnoDB;

-- ── emergency_requests ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS emergency_requests (
    request_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    patient_id      INT UNSIGNED  NULL COMMENT 'NULL if caller is not registered',
    caller_name     VARCHAR(120)  NOT NULL,
    caller_phone    VARCHAR(20)   NOT NULL,
    location_desc   TEXT          NOT NULL,
    gps_lat         DECIMAL(10,7) NULL,
    gps_lng         DECIMAL(10,7) NULL,
    status          ENUM('pending','dispatched','en_route','arrived') NOT NULL DEFAULT 'pending',
    ticket_number   VARCHAR(20)   NOT NULL UNIQUE,
    assigned_ambulance INT UNSIGNED NULL,
    dispatcher_id   INT UNSIGNED  NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id)
) ENGINE=InnoDB;

-- ── ambulances ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ambulances (
    ambulance_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    vehicle_number  VARCHAR(20)   NOT NULL UNIQUE,
    driver_name     VARCHAR(120)  NOT NULL,
    driver_phone    VARCHAR(20)   NOT NULL,
    is_available    TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (ambulance_id)
) ENGINE=InnoDB;

-- ── theatre_operations ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS theatre_operations (
    operation_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    patient_id        INT UNSIGNED  NOT NULL,
    surgeon_id        INT UNSIGNED  NOT NULL COMMENT 'doctor user reference',
    anaesthetist_id   INT UNSIGNED  NULL,
    operation_type    VARCHAR(120)  NOT NULL,
    theatre_number    TINYINT       NOT NULL,
    scheduled_at      DATETIME      NOT NULL,
    status            ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    pre_op_notes      TEXT          NULL,
    post_op_notes     TEXT          NULL,
    PRIMARY KEY (operation_id),
    INDEX idx_theatre_sched (scheduled_at, theatre_number)
) ENGINE=InnoDB;

-- ── doctor_schedules ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS doctor_schedules (
    schedule_id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    doctor_id     INT UNSIGNED  NOT NULL,
    day_of_week   TINYINT       NOT NULL COMMENT '0=Sun … 6=Sat',
    start_time    TIME          NOT NULL,
    end_time      TIME          NOT NULL,
    slot_duration TINYINT       NOT NULL DEFAULT 15 COMMENT 'minutes per slot',
    PRIMARY KEY (schedule_id),
    FOREIGN KEY fk_sched_doc (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── treatment_records ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS treatment_records (
    record_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    appointment_id INT UNSIGNED  NOT NULL UNIQUE,
    diagnosis      TEXT          NOT NULL,
    clinical_notes TEXT          NULL,
    follow_up_date DATE          NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (record_id),
    FOREIGN KEY fk_tr_appt (appointment_id) REFERENCES appointments(appointment_id)
) ENGINE=InnoDB;

-- ── newborn_records ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS newborn_records (
    newborn_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    mother_id     INT UNSIGNED  NOT NULL COMMENT 'patients.patient_id',
    operation_id  INT UNSIGNED  NULL,
    date_of_birth DATE          NOT NULL,
    weight_kg     DECIMAL(4,2)  NULL,
    health_status VARCHAR(80)   NULL,
    assigned_room INT UNSIGNED  NULL,
    PRIMARY KEY (newborn_id),
    FOREIGN KEY fk_nb_mother (mother_id) REFERENCES patients(patient_id)
) ENGINE=InnoDB;

-- ── Default admin account (password: Admin@1234) ─────────────
INSERT IGNORE INTO users (full_name, email, password_hash, role, status)
VALUES (
    'System Administrator',
    'admin@medicare-hospital.lk',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234 (bcrypt)
    'admin',
    'active'
);
======================================================================================================================================





USE hospital_db;

-- ── STEP 1: Drop the conflicting old admissions table ────────
-- (safe to drop if you haven't used it yet)
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `admissions`;
DROP TABLE IF EXISTS `rooms`;         -- old rooms has no ward_id, replace it too

SET FOREIGN_KEY_CHECKS = 1;

-- ── STEP 2: Create wards first (admissions depends on it) ────
CREATE TABLE IF NOT EXISTS `wards` (
    `ward_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ward_name`   VARCHAR(100) NOT NULL,
    `ward_type`   ENUM('general','icu','maternity','pediatric','surgical','emergency','private') NOT NULL DEFAULT 'general',
    `floor`       VARCHAR(20)  DEFAULT NULL,
    `total_rooms` INT          NOT NULL DEFAULT 0,
    `description` TEXT         DEFAULT NULL,
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STEP 3: New rooms table (replaces old one, adds ward_id) ─
CREATE TABLE IF NOT EXISTS `rooms` (
    `room_id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ward_id`        INT UNSIGNED NOT NULL,
    `room_number`    VARCHAR(20)  NOT NULL,
    `room_type`      ENUM('general','semi_private','private','icu','isolation','children') NOT NULL DEFAULT 'general',
    `total_beds`     INT          NOT NULL DEFAULT 1,
    `available_beds` INT          NOT NULL DEFAULT 1,
    `floor`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `daily_rate`     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `facilities`     TEXT         DEFAULT NULL,
    `status`         ENUM('available','full','maintenance','reserved') NOT NULL DEFAULT 'available',
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ward_id`) REFERENCES `wards`(`ward_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_room` (`ward_id`, `room_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STEP 4: Beds table ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `beds` (
    `bed_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id`    INT UNSIGNED NOT NULL,
    `bed_number` VARCHAR(20)  NOT NULL,
    `bed_type`   ENUM('standard','electric','icu','pediatric') NOT NULL DEFAULT 'standard',
    `status`     ENUM('available','occupied','maintenance','reserved') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`room_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_bed` (`room_id`, `bed_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STEP 5: Unified admissions table ─────────────────────────
-- Links to patients.patient_id (your original FK style)
-- AND to wards/rooms/beds (new ward module)
CREATE TABLE IF NOT EXISTS `admissions` (
    `admission_id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id`         INT UNSIGNED NOT NULL,   -- → patients.patient_id
    `bed_id`             INT UNSIGNED NOT NULL,   -- → beds.bed_id
    `room_id`            INT UNSIGNED NOT NULL,   -- → rooms.room_id
    `ward_id`            INT UNSIGNED NOT NULL,   -- → wards.ward_id
    `doctor_id`          INT UNSIGNED NOT NULL,   -- → doctors.doctor_id
    `admitted_by`        INT UNSIGNED NOT NULL,   -- reception/admin user_id → users.user_id
    `admission_date`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `discharge_date`     DATETIME     DEFAULT NULL,
    `expected_discharge` DATE         DEFAULT NULL,
    `admission_type`     ENUM('elective','emergency','transfer') NOT NULL DEFAULT 'elective',
    `diagnosis`          TEXT         DEFAULT NULL,
    `dietary_notes`      TEXT         DEFAULT NULL,
    `notes`              TEXT         DEFAULT NULL,
    `status`             ENUM('admitted','discharged','transferred','absconded') NOT NULL DEFAULT 'admitted',
    `total_charge`       DECIMAL(10,2) DEFAULT 0.00,
    `created_at`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ── Foreign Keys ──
    FOREIGN KEY `fk_adm_pat`   (`patient_id`)  REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
    FOREIGN KEY `fk_adm_bed`   (`bed_id`)      REFERENCES `beds`(`bed_id`),
    FOREIGN KEY `fk_adm_room`  (`room_id`)     REFERENCES `rooms`(`room_id`),
    FOREIGN KEY `fk_adm_ward`  (`ward_id`)     REFERENCES `wards`(`ward_id`),
    FOREIGN KEY `fk_adm_doc`   (`doctor_id`)   REFERENCES `doctors`(`doctor_id`),
    FOREIGN KEY `fk_adm_by`    (`admitted_by`) REFERENCES `users`(`user_id`),

    -- ── Indexes ──
    INDEX `idx_adm_patient` (`patient_id`),
    INDEX `idx_adm_status`  (`status`),
    INDEX `idx_adm_date`    (`admission_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STEP 6: Ward transfers ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ward_transfers` (
    `transfer_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admission_id`   INT UNSIGNED NOT NULL,
    `patient_id`     INT UNSIGNED NOT NULL,
    `from_bed_id`    INT UNSIGNED NOT NULL,
    `to_bed_id`      INT UNSIGNED NOT NULL,
    `from_ward_id`   INT UNSIGNED NOT NULL,
    `to_ward_id`     INT UNSIGNED NOT NULL,
    `transferred_by` INT UNSIGNED NOT NULL,
    `transfer_date`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reason`         TEXT         DEFAULT NULL,
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`admission_id`)   REFERENCES `admissions`(`admission_id`),
    FOREIGN KEY (`patient_id`)     REFERENCES `patients`(`patient_id`),
    FOREIGN KEY (`from_bed_id`)    REFERENCES `beds`(`bed_id`),
    FOREIGN KEY (`to_bed_id`)      REFERENCES `beds`(`bed_id`),
    FOREIGN KEY (`transferred_by`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STEP 7: Sample ward data ──────────────────────────────────
INSERT IGNORE INTO `wards` (`ward_name`, `ward_type`, `floor`, `total_rooms`, `description`) VALUES
('Male General Ward',   'general',   'Ground Floor', 10, 'General male patients ward'),
('Female General Ward', 'general',   'Ground Floor', 10, 'General female patients ward'),
('Intensive Care Unit', 'icu',       '1st Floor',     4, 'Critical care unit with 24hr monitoring'),
('Maternity Ward',      'maternity', '2nd Floor',     8, 'Labour, delivery and postnatal care'),
('Pediatric Ward',      'pediatric', '2nd Floor',     6, 'Children under 12 years'),
('Surgical Ward',       'surgical',  '1st Floor',     6, 'Post-operative recovery'),
('Private Ward',        'private',   '3rd Floor',    12, 'Private rooms with premium facilities'),
('Emergency Ward',      'emergency', 'Ground Floor',  5, 'Emergency observation beds');

-- ── STEP 8: Performance indexes ───────────────────────────────
CREATE INDEX IF NOT EXISTS idx_beds_status   ON beds(status);
CREATE INDEX IF NOT EXISTS idx_rooms_status  ON rooms(status);
CREATE INDEX IF NOT EXISTS idx_rooms_ward    ON rooms(ward_id);