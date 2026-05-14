-- ============================================================
-- Ward & Room Management — Additional Tables
-- Run AFTER hospital_db.sql
-- Group 05 | ICT1242 | Advanced Ward and Room Management
-- ============================================================

USE hospital_db;

-- ── beds (per-room bed tracking for semi-private/general ward) ──
CREATE TABLE IF NOT EXISTS beds (
    bed_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    room_id       INT UNSIGNED    NOT NULL,
    bed_number    VARCHAR(10)     NOT NULL,
    status        ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
    PRIMARY KEY (bed_id),
    UNIQUE KEY uk_room_bed (room_id, bed_number),
    FOREIGN KEY fk_bed_room (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Update rooms table: add general_ward and maternity/recovery types ──
ALTER TABLE rooms
    MODIFY COLUMN room_type
        ENUM('general_ward','semi_private','private','children','icu','maternity','recovery')
        NOT NULL;

-- ── admission_requests (Reception creates → Doctor approves) ──
CREATE TABLE IF NOT EXISTS admission_requests (
    request_id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    patient_id          INT UNSIGNED    NOT NULL,
    requested_by        INT UNSIGNED    NOT NULL COMMENT 'reception user_id',
    assigned_doctor_id  INT UNSIGNED    NOT NULL,
    admission_reason    TEXT            NOT NULL,
    request_notes       TEXT            NULL,
    status              ENUM('pending','approved','rejected','admitted','cancelled')
                        NOT NULL DEFAULT 'pending',
    -- Doctor fills in on approval --
    approved_room_type  ENUM('general_ward','semi_private','private','children','icu','maternity','recovery') NULL,
    meal_required       TINYINT(1)      NULL DEFAULT 1,
    meal_type           ENUM('hospital_standard','doctor_prescribed','liquid','diabetic','low_salt','no_meal','outside_food') NULL,
    meal_notes          TEXT            NULL,
    priority_level      ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
    doctor_notes        TEXT            NULL,
    reviewed_at         TIMESTAMP       NULL,
    -- Reception fills in on room assignment --
    room_id             INT UNSIGNED    NULL,
    bed_id              INT UNSIGNED    NULL,
    admission_id        INT UNSIGNED    NULL COMMENT 'links to admissions table after admitted',
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    INDEX idx_req_patient (patient_id),
    INDEX idx_req_doctor  (assigned_doctor_id),
    INDEX idx_req_status  (status),
    FOREIGN KEY fk_req_patient (patient_id)           REFERENCES patients(patient_id),
    FOREIGN KEY fk_req_doctor  (assigned_doctor_id)   REFERENCES doctors(doctor_id),
    FOREIGN KEY fk_req_room    (room_id)               REFERENCES rooms(room_id),
    FOREIGN KEY fk_req_bed     (bed_id)                REFERENCES beds(bed_id)
) ENGINE=InnoDB;

-- ── Extend admissions table with new fields ──
ALTER TABLE admissions
    ADD COLUMN IF NOT EXISTS bed_id              INT UNSIGNED  NULL AFTER room_id,
    ADD COLUMN IF NOT EXISTS request_id          INT UNSIGNED  NULL AFTER bed_id,
    ADD COLUMN IF NOT EXISTS meal_required       TINYINT(1)    NOT NULL DEFAULT 1 AFTER dietary_notes,
    ADD COLUMN IF NOT EXISTS meal_type           ENUM('hospital_standard','doctor_prescribed','liquid','diabetic','low_salt','no_meal','outside_food') NULL AFTER meal_required,
    ADD COLUMN IF NOT EXISTS priority_level      ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal' AFTER meal_type,
    ADD COLUMN IF NOT EXISTS advance_paid        DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER priority_level,
    ADD COLUMN IF NOT EXISTS invoice_id          INT UNSIGNED  NULL AFTER advance_paid,
    ADD FOREIGN KEY fk_adm_bed (bed_id) REFERENCES beds(bed_id),
    ADD FOREIGN KEY fk_adm_req (request_id) REFERENCES admission_requests(request_id);

-- ── newborn_records: add gender field if missing ──
ALTER TABLE newborn_records
    ADD COLUMN IF NOT EXISTS gender   ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown' AFTER weight_kg,
    ADD COLUMN IF NOT EXISTS baby_name VARCHAR(100) NULL AFTER newborn_id,
    ADD COLUMN IF NOT EXISTS notes     TEXT NULL AFTER health_status;

-- ── Sample room data ──
INSERT IGNORE INTO rooms (room_number, room_type, floor, is_available, daily_rate) VALUES
('G01','general_ward',1,1,1500.00),
('G02','general_ward',1,1,1500.00),
('G03','general_ward',1,1,1500.00),
('SP01','semi_private',2,1,3500.00),
('SP02','semi_private',2,1,3500.00),
('PR01','private',3,1,6000.00),
('PR02','private',3,1,6000.00),
('PR03','private',3,1,6000.00),
('CH01','children',2,1,3000.00),
('CH02','children',2,1,3000.00),
('ICU01','icu',4,1,12000.00),
('ICU02','icu',4,1,12000.00),
('ICU03','icu',4,1,12000.00),
('MAT01','maternity',3,1,5000.00),
('MAT02','maternity',3,1,5000.00),
('REC01','recovery',4,1,8000.00),
('REC02','recovery',4,1,8000.00);

-- ── Sample beds for general ward and semi-private ──
INSERT IGNORE INTO beds (room_id, bed_number, status)
SELECT r.room_id, CONCAT('B',n.n), 'available'
FROM rooms r
JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) n
WHERE r.room_type IN ('general_ward','semi_private');
