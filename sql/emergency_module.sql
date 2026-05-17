-- ============================================================
-- Emergency Module — Tables + Dummy Data
-- hospital_db | ICT1242 Hospital Management System
-- Run this AFTER hospital_db.sql (users table must exist)
-- ============================================================

USE hospital_db;

-- ────────────────────────────────────────────────────────────
-- 1. DRIVERS TABLE
--    PRIMARY KEY: driver_id  (driver.php uses driver_id)
--    dispatcher.php originally used `id` — fix those queries
--    below in the PHP patch section.
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS drivers;

CREATE TABLE drivers (
    driver_id       INT             NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NULL,        -- links to users table (driver portal login)
    full_name       VARCHAR(150)    NOT NULL,
    nic             VARCHAR(20)     NOT NULL UNIQUE,
    phone           VARCHAR(20)     NOT NULL,
    license_no      VARCHAR(40)     NOT NULL,
    ambulance_no    VARCHAR(20)     NOT NULL,
    status          ENUM('available','on_duty','off_duty') NOT NULL DEFAULT 'available',
    password        VARCHAR(255)    NULL,        -- standalone login (dispatcher.php)
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (driver_id),
    INDEX idx_driver_user   (user_id),
    INDEX idx_driver_status (status),
    FOREIGN KEY fk_drv_user (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ────────────────────────────────────────────────────────────
-- 2. EMERGENCY_REQUESTS TABLE
--    save_emergency.php : patient_name, patient_address,
--                         contact_number, emergency_type,
--                         is_conscious, assistance_on_site
--    dispatcher.php     : driver_id, status, dispatched_at
--    driver.php         : assigned_driver_id, dispatch_time,
--                         completed_at
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS emergency_requests;

CREATE TABLE emergency_requests (
    emergency_id        INT             NOT NULL AUTO_INCREMENT,
    patient_name        VARCHAR(150)    DEFAULT NULL,
    patient_address     TEXT            DEFAULT NULL,
    contact_number      VARCHAR(20)     NOT NULL,
    emergency_type      ENUM(
                            'cardiac',
                            'accident',
                            'breathing',
                            'stroke',
                            'burn',
                            'poisoning',
                            'fracture',
                            'other'
                        )               DEFAULT NULL,
    is_conscious        ENUM('yes','semi','no')              DEFAULT NULL,
    assistance_on_site  ENUM('yes','no','medical')           DEFAULT NULL,

    -- dispatcher.php fields
    driver_id           INT             NULL,
    dispatched_at       TIMESTAMP       NULL,

    -- driver.php / driver_portal.php fields
    assigned_driver_id  INT             NULL,
    dispatch_time       TIMESTAMP       NULL,
    completed_at        TIMESTAMP       NULL,

    status              ENUM('pending','dispatched','en_route','arrived','resolved','cancelled')
                                        NOT NULL DEFAULT 'pending',
    submitted_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (emergency_id),
    INDEX idx_er_status     (status),
    INDEX idx_er_driver     (driver_id),
    INDEX idx_er_assigned   (assigned_driver_id),
    FOREIGN KEY fk_er_drv  (driver_id)          REFERENCES drivers(driver_id) ON DELETE SET NULL,
    FOREIGN KEY fk_er_adrv (assigned_driver_id)  REFERENCES drivers(driver_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- DUMMY DATA
-- ============================================================

-- ── Driver users in the users table ─────────────────────────
INSERT INTO users (full_name, email, password_hash, role, status, staff_id, department) VALUES
('Kasun Perera',    'kasun.driver@medicare.lk',   '$2y$10$dummyhashfordev001xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'driver', 'active', 'DRV001', 'Emergency'),
('Nimal Fernando',  'nimal.driver@medicare.lk',   '$2y$10$dummyhashfordev002xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'driver', 'active', 'DRV002', 'Emergency'),
('Ruwan Silva',     'ruwan.driver@medicare.lk',   '$2y$10$dummyhashfordev003xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'driver', 'active', 'DRV003', 'Emergency'),
('Chamara Bandara', 'chamara.driver@medicare.lk', '$2y$10$dummyhashfordev004xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'driver', 'active', 'DRV004', 'Emergency'),
('Tharaka Jayasinghe','tharaka.driver@medicare.lk','$2y$10$dummyhashfordev005xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx','driver','inactive','DRV005','Emergency');

-- ── Dispatcher user ──────────────────────────────────────────
INSERT INTO users (full_name, email, password_hash, role, status, staff_id, department) VALUES
('Dilani Madushani', 'dilani.dispatch@medicare.lk', '$2y$10$dummyhashfordev006xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'dispatcher', 'active', 'DSP001', 'Emergency');


-- ── Drivers (password = "driver123" hashed via password_hash) ──
-- NOTE: user_id values below assume the users inserted above start from
--       a known auto-increment. Adjust SET @uid_offset if your DB already
--       has rows in users.
SET @uid_offset = (SELECT COALESCE(MAX(user_id),0) - 5 FROM users WHERE role='driver' LIMIT 1);

INSERT INTO drivers (user_id, full_name, nic, phone, license_no, ambulance_no, status, password) VALUES
(
    (SELECT user_id FROM users WHERE email='kasun.driver@medicare.lk'),
    'Kasun Perera',     '199012345678', '0771234501', 'B1234567', 'AMB-001', 'available',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'   -- "password"
),
(
    (SELECT user_id FROM users WHERE email='nimal.driver@medicare.lk'),
    'Nimal Fernando',   '198834567890', '0771234502', 'B2345678', 'AMB-002', 'on_duty',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
),
(
    (SELECT user_id FROM users WHERE email='ruwan.driver@medicare.lk'),
    'Ruwan Silva',      '199523456789', '0771234503', 'B3456789', 'AMB-003', 'available',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
),
(
    (SELECT user_id FROM users WHERE email='chamara.driver@medicare.lk'),
    'Chamara Bandara',  '199145678901', '0771234504', 'B4567890', 'AMB-004', 'off_duty',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
),
(
    (SELECT user_id FROM users WHERE email='tharaka.driver@medicare.lk'),
    'Tharaka Jayasinghe','199267890123','0771234505', 'B5678901', 'AMB-005', 'off_duty',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);


-- ── Emergency Requests ───────────────────────────────────────
-- Get driver IDs for FK references
SET @drv1 = (SELECT driver_id FROM drivers WHERE ambulance_no = 'AMB-001');
SET @drv2 = (SELECT driver_id FROM drivers WHERE ambulance_no = 'AMB-002');
SET @drv3 = (SELECT driver_id FROM drivers WHERE ambulance_no = 'AMB-003');

-- 1. Pending — no driver assigned yet
INSERT INTO emergency_requests
    (patient_name, patient_address, contact_number, emergency_type, is_conscious, assistance_on_site, status, submitted_at)
VALUES
    ('Sunil Rathnayake',  '45 Galle Road, Colombo 03',     '0712345601', 'cardiac',   'semi', 'no',      'pending',    NOW() - INTERVAL 8  MINUTE),
    ('Priya Wickramasinghe','12 Kandy Road, Colombo 10',   '0712345602', 'accident',  'yes',  'no',      'pending',    NOW() - INTERVAL 3  MINUTE),
    ('Amara Dissanayake', '78 Negombo Road, Wattala',      '0712345603', 'breathing', 'no',   'medical', 'pending',    NOW() - INTERVAL 1  MINUTE);

-- 2. Dispatched — driver assigned by dispatcher.php
INSERT INTO emergency_requests
    (patient_name, patient_address, contact_number, emergency_type, is_conscious, assistance_on_site,
     status, driver_id, assigned_driver_id, dispatched_at, dispatch_time, submitted_at)
VALUES
    ('Ranjith Kumara',   '23 Station Road, Nugegoda',      '0712345604', 'stroke',    'semi', 'no',
     'dispatched', @drv2, @drv2, NOW() - INTERVAL 12 MINUTE, NOW() - INTERVAL 12 MINUTE, NOW() - INTERVAL 20 MINUTE),

    ('Malini Senanayake','55 High Level Road, Maharagama', '0712345605', 'fracture',  'yes',  'yes',
     'dispatched', @drv1, @drv1, NOW() - INTERVAL 5  MINUTE, NOW() - INTERVAL 5  MINUTE, NOW() - INTERVAL 15 MINUTE);

-- 3. Resolved — completed trips
INSERT INTO emergency_requests
    (patient_name, patient_address, contact_number, emergency_type, is_conscious, assistance_on_site,
     status, driver_id, assigned_driver_id, dispatched_at, dispatch_time, completed_at, submitted_at)
VALUES
    ('Thilak Gunawardena','14 Flower Road, Colombo 07',   '0712345606', 'burn',      'yes',  'no',
     'resolved', @drv3, @drv3,
     NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 2 HOUR,
     NOW() - INTERVAL 4 HOUR),

    ('Chamila Peris',    '90 Parliament Road, Kotte',      '0712345607', 'poisoning', 'semi', 'medical',
     'resolved', @drv1, @drv1,
     NOW() - INTERVAL 6 HOUR, NOW() - INTERVAL 6 HOUR, NOW() - INTERVAL 5 HOUR,
     NOW() - INTERVAL 7 HOUR),

    ('Anura Jayawardena','33 Baseline Road, Colombo 08',  '0712345608', 'accident',  'no',   'no',
     'resolved', @drv2, @drv2,
     NOW() - INTERVAL 10 HOUR, NOW() - INTERVAL 10 HOUR, NOW() - INTERVAL 9 HOUR,
     NOW() - INTERVAL 11 HOUR);

-- 4. Cancelled
INSERT INTO emergency_requests
    (patient_name, patient_address, contact_number, emergency_type, is_conscious, assistance_on_site,
     status, submitted_at)
VALUES
    ('Anonymous',        '67 Main Street, Moratuwa',       '0712345609', 'other',     'yes',  'yes',
     'cancelled', NOW() - INTERVAL 2 HOUR);


-- ============================================================
-- QUICK SANITY CHECK (comment out before production)
-- ============================================================
-- SELECT 'drivers' AS tbl, COUNT(*) AS rows FROM drivers
-- UNION ALL
-- SELECT 'emergency_requests', COUNT(*) FROM emergency_requests;
