-- ============================================================
--  emergency_setup.sql
--  Run this in phpMyAdmin AFTER hospital_db.sql
--  This is the SINGLE correct schema for the emergency module.
-- ============================================================

USE hospital_db;

-- ── 1. DROP old tables (clean slate) ────────────────────────
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS emergency_requests;
DROP TABLE IF EXISTS drivers;
SET FOREIGN_KEY_CHECKS = 1;

-- ── 2. DRIVERS TABLE ─────────────────────────────────────────
--   driver_id  = auto PK
--   user_id    = FK → users.user_id  (used for login session)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE drivers (
    driver_id             INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id               INT UNSIGNED  NOT NULL UNIQUE,         -- matches users.user_id INT UNSIGNED
    license_number        VARCHAR(50)   NOT NULL DEFAULT 'PENDING',
    ambulance_number      VARCHAR(30)   DEFAULT NULL,
    ambulance_type        ENUM('basic','advanced','neonatal','other') DEFAULT 'basic',
    years_of_experience   INT           DEFAULT 0,
    status                ENUM('available','on_duty','off_duty','on_leave') NOT NULL DEFAULT 'available',
    assigned_emergency_id INT UNSIGNED  DEFAULT NULL,            -- filled after emergency_requests is created
    created_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_driver_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. EMERGENCY_REQUESTS TABLE ──────────────────────────────
--   assigned_driver_id = FK → drivers.driver_id  (NOT user_id)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE emergency_requests (
    emergency_id        INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_name        VARCHAR(150) DEFAULT NULL,
    patient_address     TEXT         DEFAULT NULL,
    contact_number      VARCHAR(20)  NOT NULL,
    emergency_type      ENUM('cardiac','accident','breathing','stroke','burn','poisoning','fracture','other')
                                     NOT NULL DEFAULT 'other',
    is_conscious        ENUM('yes','semi','no') NOT NULL DEFAULT 'yes',
    assistance_on_site  ENUM('yes','no','medical') NOT NULL DEFAULT 'no',
    status              ENUM('pending','dispatched','en_route','arrived','resolved','cancelled')
                                     NOT NULL DEFAULT 'pending',
    assigned_driver_id  INT UNSIGNED  DEFAULT NULL,  -- FK → drivers.driver_id (UNSIGNED to match)
    submitted_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dispatch_time       DATETIME     DEFAULT NULL,
    resolved_at         DATETIME     DEFAULT NULL,
    INDEX idx_er_status (status),
    INDEX idx_er_driver (assigned_driver_id),
    CONSTRAINT fk_er_driver
        FOREIGN KEY (assigned_driver_id) REFERENCES drivers(driver_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. Add FK on drivers.assigned_emergency_id ───────────────
ALTER TABLE drivers
    ADD CONSTRAINT fk_driver_emergency
        FOREIGN KEY (assigned_emergency_id) REFERENCES emergency_requests(emergency_id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 5. Auto-create driver records for all users with role=driver ──
INSERT IGNORE INTO drivers (user_id, license_number, status)
SELECT user_id, 'PENDING', 'available'
FROM   users
WHERE  role = 'driver';

-- ── 6. SAMPLE DATA ───────────────────────────────────────────
-- Driver users (password = "driver123")
INSERT IGNORE INTO users (full_name, email, password_hash, role, status, staff_id, department) VALUES
('Kasun Perera',     'kasun.driver@medicare.lk',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver',     'active', 'DRV001', 'Emergency'),
('Nimal Fernando',   'nimal.driver@medicare.lk',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver',     'active', 'DRV002', 'Emergency'),
('Ruwan Silva',      'ruwan.driver@medicare.lk',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver',     'active', 'DRV003', 'Emergency'),
('Dilani Madushani', 'dilani.dispatch@medicare.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dispatcher', 'active', 'DSP001', 'Emergency');
-- password for all above = "password"

-- Sync new users → drivers table
INSERT IGNORE INTO drivers (user_id, license_number, ambulance_number, ambulance_type, years_of_experience, status)
SELECT u.user_id,
       CASE u.staff_id WHEN 'DRV001' THEN 'B1234567' WHEN 'DRV002' THEN 'B2345678' WHEN 'DRV003' THEN 'B3456789' ELSE 'PENDING' END,
       CASE u.staff_id WHEN 'DRV001' THEN 'AMB-001'  WHEN 'DRV002' THEN 'AMB-002'  WHEN 'DRV003' THEN 'AMB-003'  ELSE NULL END,
       'basic', 3, 'available'
FROM users u
WHERE u.role = 'driver'
AND   u.user_id NOT IN (SELECT user_id FROM drivers);

-- Sample pending emergencies
INSERT INTO emergency_requests (patient_name, patient_address, contact_number, emergency_type, is_conscious, assistance_on_site, status) VALUES
('Sunil Rathnayake',   '45 Galle Road, Colombo 03',   '0712345601', 'cardiac',   'semi', 'no',      'pending'),
('Priya Wickramasinghe','12 Kandy Road, Colombo 10',  '0712345602', 'accident',  'yes',  'no',      'pending'),
('Amara Dissanayake',  '78 Negombo Road, Wattala',    '0712345603', 'breathing', 'no',   'medical', 'pending');
