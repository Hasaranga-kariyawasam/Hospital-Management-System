-- ============================================================
-- hospital_db — Core Schema (stub tables for auth + registration)
-- Group 05 | ICT1242 Web Development Practicum
-- Run this in phpMyAdmin or MySQL CLI AFTER creating the database:
--   CREATE DATABASE hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ============================================================

USE hospital_db;

-- ── users (central identity for all roles) ──────────────────
CREATE TABLE IF NOT EXISTS users (
    user_id       VARCHAR(10)     NOT NULL ,
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
    patient_id        VARCHAR(10)   NOT NULL ,
    user_id           VARCHAR(10)   NOT NULL,
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
    doctor_id        VARCHAR(10)   NOT NULL,
    user_id          VARCHAR(10)   NOT NULL,
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
    room_id       VARCHAR(10)INT   NOT NULL ,
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
    admission_id       VARCHAR(10)   NOT NULL ,
    patient_id         VARCHAR(10)   NOT NULL,
    room_id            VARCHAR(10)   NOT NULL,
    doctor_id          VARCHAR(10)   NOT NULL,
    admitted_by        VARCHAR(10)   NOT NULL COMMENT 'reception user_id',
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
    invoice_id       VARCHAR(10)   NOT NULL ,
    patient_id     VARCHAR(10)   NOT NULL,
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
    item_id       varchar(10)   NOT NULL ,
    invoice_id    VARCHAR(10)   NOT NULL,
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
    payment_id     VARCHAR(10)    NOT NULL ,
    invoice_id     VARCHAR(10)   NOT NULL,
    amount         DECIMAL(10,2)  NOT NULL,
    payment_type   ENUM('full','advance') NOT NULL DEFAULT 'full',
    payment_method ENUM('cash','card','insurance','bank_transfer') NOT NULL DEFAULT 'cash',
    receipt_number VARCHAR(20)    NOT NULL UNIQUE,
    received_by    VARCHAR(10)   NOT NULL COMMENT 'reception user_id',
    paid_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    FOREIGN KEY fk_pay_inv (invoice_id) REFERENCES billing_invoices(invoice_id)
) ENGINE=InnoDB;

-- ── pharmacy_drugs ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pharmacy_drugs (
    drug_id       VARCHAR(10)   NOT NULL ,
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
    prescription_id varchar(10)  NOT NULL ,
    appointment_id   VARCHAR(10)   NOT NULL,
    drug_id          VARCHAR(10)   NOT NULL,
    dosage           VARCHAR(80)   NOT NULL,
    frequency        VARCHAR(80)   NOT NULL,
    duration_days    SMALLINT      NOT NULL DEFAULT 7,
    status           ENUM('pending','dispensed') NOT NULL DEFAULT 'pending',
    dispensed_by     VARCHAR(10)  NULL COMMENT 'pharmacist user_id',
    dispensed_at     TIMESTAMP     NULL,
    PRIMARY KEY (prescription_id),
    FOREIGN KEY fk_rx_appt (appointment_id) REFERENCES appointments(appointment_id),
    FOREIGN KEY fk_rx_drug (drug_id)        REFERENCES pharmacy_drugs(drug_id)
) ENGINE=InnoDB;

-- ── emergency_requests ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS emergency_requests (
    request_id      VARCHAR(10)  NOT NULL ,
    patient_id      VARCHAR(10)  NULL COMMENT 'NULL if caller is not registered',
    caller_name     VARCHAR(120)  NOT NULL,
    caller_phone    VARCHAR(20)   NOT NULL,
    location_desc   TEXT          NOT NULL,
    gps_lat         DECIMAL(10,7) NULL,
    gps_lng         DECIMAL(10,7) NULL,
    status          ENUM('pending','dispatched','en_route','arrived') NOT NULL DEFAULT 'pending',
    ticket_number   VARCHAR(20)   NOT NULL UNIQUE,
    assigned_ambulance VARCHAR(10)  NULL,
    dispatcher_id   VARCHAR(10)   NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id)
) ENGINE=InnoDB;

-- ── ambulances ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ambulances (
    ambulance_id    VARCHAR(10)   NOT NULL,
    vehicle_number  VARCHAR(20)   NOT NULL UNIQUE,
    driver_name     VARCHAR(120)  NOT NULL,
    driver_phone    VARCHAR(20)   NOT NULL,
    is_available    TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (ambulance_id)
) ENGINE=InnoDB;
DROP TABLE IF EXISTS theatre_operations;
CREATE TABLE theatre_operations (
    operation_id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    patient_id            INT UNSIGNED   NOT NULL,
    surgeon_id            INT UNSIGNED   NOT NULL,
    anaesthetist_id       INT UNSIGNED   NULL,
    assistant_doctor_id   INT UNSIGNED   NULL,
    operation_type        VARCHAR(120)   NOT NULL,
    theatre_number        TINYINT        NOT NULL,
    scheduled_date        DATE           NOT NULL,
    scheduled_time        TIME           NOT NULL,
    scheduled_at          TIMESTAMP      GENERATED ALWAYS AS (TIMESTAMP(scheduled_date, scheduled_time)) STORED,
    status                ENUM('scheduled','confirmed','in_progress','completed','cancelled','transferred') NOT NULL DEFAULT 'scheduled',
    pre_op_notes          TEXT           NULL,
    post_op_notes         TEXT           NULL,
    recovery_instructions TEXT           NULL,
    post_op_room_type     VARCHAR(40)    NULL,
    created_by            INT UNSIGNED   NULL,
    created_at            TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (operation_id),
    INDEX idx_theatre_date (theatre_number, scheduled_date, scheduled_time),
    FOREIGN KEY fk_th_patient   (patient_id)         REFERENCES patients(patient_id),
    FOREIGN KEY fk_th_surgeon   (surgeon_id)          REFERENCES users(user_id),
    FOREIGN KEY fk_th_anaes     (anaesthetist_id)     REFERENCES users(user_id),
    FOREIGN KEY fk_th_assistant (assistant_doctor_id) REFERENCES users(user_id),
    FOREIGN KEY fk_th_created   (created_by)          REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ── doctor_schedules ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS doctor_schedules (
    schedule_id   VARCHAR(10)   NOT NULL ,
    doctor_id     VARCHAR(10)   NOT NULL,
    day_of_week   TINYINT       NOT NULL COMMENT '0=Sun … 6=Sat',
    start_time    TIME          NOT NULL,
    end_time      TIME          NOT NULL,
    slot_duration TINYINT       NOT NULL DEFAULT 15 COMMENT 'minutes per slot',
    PRIMARY KEY (schedule_id),
    FOREIGN KEY fk_sched_doc (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── treatment_records ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS treatment_records (
    record_id      VARCHAR(10)   NOT NULL ,
    appointment_id VARCHAR(10)   NOT NULL UNIQUE,
    diagnosis      TEXT          NOT NULL,
    clinical_notes TEXT          NULL,
    follow_up_date DATE          NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (record_id),
    FOREIGN KEY fk_tr_appt (appointment_id) REFERENCES appointments(appointment_id)
) ENGINE=InnoDB;

-- ── newborn_records ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS newborn_records (
    newborn_id    VARCHAR(10)   NOT NULL ,
    mother_id     VARCHAR(10)   NOT NULL COMMENT 'patients.patient_id',
    operation_id  VARCHAR(10)   NULL,
    date_of_birth DATE          NOT NULL,
    weight_kg     DECIMAL(4,2)  NULL,
    health_status VARCHAR(80)   NULL,
    assigned_room VARCHAR(10)  NULL,
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
 
-- Add missing columns to theatre_operations
-- (The base table from hospital_db.sql has fewer columns)
 
-----sample data-------

INSERT IGNORE INTO users (full_name, email, password_hash, role, status) VALUES
('Dr. Nimal Silva', 'nimal@medicare.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active'),
('Dr. Kamal Perera', 'kamal@medicare.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active'),
('Dr. Kumari Jayawardena', 'kumari@medicare.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active'),
('Dr. Anura Bandara', 'anura@medicare.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active'),
('Dr. Shani Weerasinghe', 'shani@medicare.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active');


INSERT IGNORE INTO doctors (user_id, specialization, license_number) VALUES
((SELECT user_id FROM users WHERE email = 'nimal@medicare.lk'), 'General Surgery', 'DOC001'),
((SELECT user_id FROM users WHERE email = 'kamal@medicare.lk'), 'Orthopedics', 'DOC002'),
((SELECT user_id FROM users WHERE email = 'kumari@medicare.lk'), 'Gynecology', 'DOC003'),
((SELECT user_id FROM users WHERE email = 'anura@medicare.lk'), 'Cardiology', 'DOC004'),
((SELECT user_id FROM users WHERE email = 'shani@medicare.lk'), 'Anesthesiology', 'DOC005');




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


Insert sample data for testing login and registration functionality
INSERT INTO users (user_id, full_name, email, password_hash, role, status) 
VALUES 
('USR-001', 'Aruna Perera', 'aruna@hospital.lk', 'hash_v1_1', 'admin', 'active'),
('USR-002', 'Sanduni Silva', 'sanduni@hospital.lk', 'hash_v1_2', 'doctor', 'active'),
('USR-003', 'Ravi Gamage', 'ravi@hospital.lk', 'hash_v1_3', 'pharmacist', 'active'),
('USR-004', 'Nilanthi De Silva', 'nilanthi@hospital.lk', 'hash_v1_4', 'reception', 'active'),
('USR-005', 'Kamal Siriwardana', 'kamal@hospital.lk', 'hash_v1_5', 'patient', 'inactive');



INSERT INTO patients (patient_id, user_id, nic, dob, gender, blood_type, phone, address, emergency_contact) 
VALUES 
('PAT-001', 'USR-001', '199012345678', '1990-05-15', 'male', 'A+', '0712345678', '123 Main St, Colombo', 'Nimal - 0771234567'),
('PAT-002', 'USR-002', '199256781234', '1992-10-20', 'female', 'O+', '0723456789', '45 Galle Rd, Matara', 'Saman - 0719876543'),
('PAT-003', 'USR-003', '198511223344', '1985-02-10', 'male', 'B-', '0754567890', 'No 12, Kandy', 'Amara - 0701234567'),
('PAT-004', 'USR-004', '199588776655', '1995-12-30', 'female', 'AB+', '0775678901', 'Green Lane, Jaffna', 'Kamal - 0765432109'),
('PAT-005', 'USR-005', '200033445566', '2000-07-05', 'male', 'O-', '0786789012', 'Temple Rd, Negombo', 'Priya - 0722334455');


INSERT INTO doctors (doctor_id, user_id, specialization, qualifications, license_number, consultation_fee) 
VALUES 
('DOC-001', 'USR-001', 'Cardiologist', 'MBBS, MD, MRCP', 'SLMC-12345', 2500.00),
('DOC-002', 'USR-002', 'Pediatrician', 'MBBS, DCH, MD', 'SLMC-67890', 1500.00),
('DOC-003', 'USR-003', 'Neurologist', 'MBBS, MD (Neurology)', 'SLMC-11223', 3000.00),
('DOC-004', 'USR-004', 'Dermatologist', 'MBBS, MD', 'SLMC-44556', 2000.00),
('DOC-005', 'USR-005', 'General Physician', 'MBBS', 'SLMC-77889', 1000.00);



INSERT INTO appointments (appointment_id, patient_id, doctor_id, appt_date, appt_time, source, status, ref_number, notes) 
VALUES 
('APT-001', 'PAT-001', 'DOC-001', '2026-05-01', '09:00:00', 'online', 'confirmed', 'REF1001', 'Patient has high blood pressure'),
('APT-002', 'PAT-002', 'DOC-002', '2026-05-13', '10:30:00', 'opd', 'pending', 'REF1002', 'Routine checkup for child'),
('APT-003', 'PAT-003', 'DOC-003', '2026-06-02', '14:00:00', 'online', 'confirmed', 'REF1003', 'Frequent headaches'),
('APT-004', 'PAT-004', 'DOC-004', '2026-05-20', '16:15:00', 'online', 'completed', 'REF1004', 'Skin allergy follow-up'),
('APT-005', 'PAT-005', 'DOC-005', '2026-05-23', '08:45:00', 'opd', 'cancelled', 'REF1005', 'Patient requested cancellation');



INSERT INTO pharmacy_drugs (drug_id, drug_name, category, unit, unit_price, stock_qty, reorder_level) 
VALUES 
('DRG-001', 'Paracetamol 500mg', 'Analgesic', 'tablet', 2.50, 1000, 100),
('DRG-002', 'Amoxicillin 250mg', 'Antibiotic', 'capsule', 15.00, 500, 50),
('DRG-003', 'Metformin 500mg', 'Antidiabetic', 'tablet', 5.00, 2000, 200),
('DRG-004', 'Atorvastatin 10mg', 'Statins', 'tablet', 12.00, 800, 100),
('DRG-005', 'Omeprazole 20mg', 'Antacid', 'capsule', 8.00, 600, 100),
('DRG-006', 'Salbutamol Inhaler', 'Bronchodilator', 'unit', 450.00, 50, 10),
('DRG-007', 'Cetirizine 10mg', 'Antihistamine', 'tablet', 3.00, 1200, 150),
('DRG-008', 'Losartan 50mg', 'Antihypertensive', 'tablet', 18.00, 900, 100),
('DRG-009', 'Amlodipine 5mg', 'Antihypertensive', 'tablet', 6.00, 1500, 200),
('DRG-010', 'Azithromycin 500mg', 'Antibiotic', 'tablet', 65.00, 300, 30),
('DRG-011', 'Diclofenac Sodium 50mg', 'NSAID', 'tablet', 4.50, 1000, 100),
('DRG-012', 'Furosemide 40mg', 'Diuretic', 'tablet', 2.00, 500, 50),
('DRG-013', 'Gliclazide 80mg', 'Antidiabetic', 'tablet', 9.50, 1100, 100),
('DRG-014', 'Ciprofloxacin 500mg', 'Antibiotic', 'tablet', 22.00, 400, 50),
('DRG-015', 'Prednisolone 5mg', 'Corticosteroid', 'tablet', 1.50, 2000, 200),
('DRG-016', 'Ranitidine 150mg', 'Antacid', 'tablet', 3.50, 800, 100),
('DRG-017', 'Domperidone 10mg', 'Antiemetic', 'tablet', 4.00, 1000, 100),
('DRG-018', 'Aspirin 75mg', 'Antiplatelet', 'tablet', 1.50, 3000, 300),
('DRG-019', 'Clopidogrel 75mg', 'Antiplatelet', 'tablet', 25.00, 600, 50),
('DRG-020', 'Vitamin C 500mg', 'Supplement', 'tablet', 5.00, 5000, 500),
('DRG-021', 'Multivitamin', 'Supplement', 'capsule', 12.00, 1500, 200),
('DRG-022', 'Insulin Soluble', 'Antidiabetic', 'vial', 850.00, 40, 10),
('DRG-023', 'Hydrochlorothiazide', 'Diuretic', 'tablet', 3.00, 700, 100),
('DRG-024', 'Pantoprazole 40mg', 'Antacid', 'tablet', 14.00, 900, 100),
('DRG-025', 'Warfarin 5mg', 'Anticoagulant', 'tablet', 10.00, 300, 50),
('DRG-026', 'Digoxin 0.25mg', 'Cardiac Glycoside', 'tablet', 7.00, 200, 40),
('DRG-027', 'Enalapril 5mg', 'Antihypertensive', 'tablet', 4.00, 800, 100),
('DRG-028', 'Dexamethasone 0.5mg', 'Corticosteroid', 'tablet', 2.00, 1500, 150),
('DRG-029', 'Metronidazole 400mg', 'Antibiotic', 'tablet', 6.00, 1000, 100),
('DRG-030', 'Levothyroxine 50mcg', 'Hormone', 'tablet', 8.00, 1200, 150),
('DRG-031', 'Fluconazole 150mg', 'Antifungal', 'capsule', 45.00, 200, 30),
('DRG-032', 'Ibuprofen 400mg', 'NSAID', 'tablet', 6.00, 1200, 100),
('DRG-033', 'Doxycycline 100mg', 'Antibiotic', 'capsule', 18.00, 500, 50),
('DRG-034', 'Spironolactone 25mg', 'Diuretic', 'tablet', 12.50, 400, 50),
('DRG-035', 'Bisoprolol 5mg', 'Beta-blocker', 'tablet', 22.00, 600, 60),
('DRG-036', 'Carvedilol 6.25mg', 'Beta-blocker', 'tablet', 15.00, 500, 50),
('DRG-037', 'Clarithromycin 500mg', 'Antibiotic', 'tablet', 85.00, 250, 25),
('DRG-038', 'Glibenclamide 5mg', 'Antidiabetic', 'tablet', 3.50, 1000, 100),
('DRG-039', 'Mebendazole 100mg', 'Anthelmintic', 'tablet', 10.00, 300, 50),
('DRG-040', 'Oral Rehydration Salts', 'Rehydration', 'sachet', 40.00, 200, 50),
('DRG-041', 'Chlorpheniramine 4mg', 'Antihistamine', 'tablet', 1.00, 5000, 500),
('DRG-042', 'Erythromycin 250mg', 'Antibiotic', 'tablet', 12.00, 600, 60),
('DRG-043', 'Nifedipine 10mg', 'Calcium Blocker', 'capsule', 8.00, 700, 70),
('DRG-044', 'Theophylline 150mg', 'Bronchodilator', 'tablet', 14.00, 400, 40),
('DRG-045', 'Ferrous Sulfate', 'Supplement', 'tablet', 2.00, 3000, 300),
('DRG-046', 'Folic Acid 5mg', 'Supplement', 'tablet', 1.50, 3000, 300),
('DRG-047', 'Calcium Carbonate', 'Supplement', 'tablet', 5.00, 2000, 200),
('DRG-048', 'Tramadol 50mg', 'Analgesic', 'capsule', 25.00, 400, 40),
('DRG-049', 'Loperamide 2mg', 'Antidiarrheal', 'capsule', 5.00, 500, 50),
('DRG-050', 'Hyoscine Butylbromide', 'Antispasmodic', 'tablet', 12.00, 600, 60);



-- Ambulance fleet table
CREATE TABLE IF NOT EXISTS ambulances (
    ambulance_id   INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_no     VARCHAR(20)  NOT NULL,
    driver_name    VARCHAR(100) NOT NULL,
    driver_phone   VARCHAR(15)  NOT NULL,
    status         ENUM('available', 'dispatched', 'maintenance') DEFAULT 'available',
    last_location  VARCHAR(255)
) ENGINE=InnoDB;

-- Emergency requests table (no foreign keys - safe standalone version)
CREATE TABLE IF NOT EXISTS emergency_requests (
    request_id      INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no       VARCHAR(20)  NOT NULL UNIQUE,
    patient_id      INT          DEFAULT NULL,
    requester_name  VARCHAR(100) NOT NULL,
    phone           VARCHAR(15)  NOT NULL,
    gps_lat         DECIMAL(10,8) DEFAULT NULL,
    gps_lng         DECIMAL(11,8) DEFAULT NULL,
    description     TEXT,
    status          ENUM('pending','dispatched','en_route','arrived','closed') DEFAULT 'pending',
    ambulance_id    INT          DEFAULT NULL,
    dispatcher_id   INT          DEFAULT NULL,
    dispatched_at   DATETIME     DEFAULT NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Sample ambulance data
INSERT INTO ambulances (vehicle_no, driver_name, driver_phone, status) VALUES
('AMB-001', 'Kamal Perera',   '0771234567', 'available'),
('AMB-002', 'Sunil Fernando', '0779876543', 'available'),
('AMB-003', 'Nimal Silva',    '0761122334', 'maintenance');

