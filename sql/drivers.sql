
-- ============================================================
--  Driver-related tables for hospital_db
--  Run this in phpMyAdmin → SQL tab
-- ============================================================

USE hospital_db;

-- ------------------------------------------------------------
-- 1. drivers  (links to users table via user_id)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS drivers (
    driver_id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id              INT          NOT NULL UNIQUE,          -- FK → users.user_id
    license_number       VARCHAR(50)  NOT NULL,
    ambulance_number     VARCHAR(30)  DEFAULT NULL,
    ambulance_type       ENUM('basic','advanced','neonatal','other') DEFAULT 'basic',
    years_of_experience  INT          DEFAULT 0,
    status               ENUM('available','on_duty','off_duty','on_leave') NOT NULL DEFAULT 'available',
    assigned_emergency_id INT         DEFAULT NULL,             -- FK → emergency_requests.emergency_id
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_driver_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 2. emergency_requests  (create or alter if already exists)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS emergency_requests (
    emergency_id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    emergency_type       ENUM('cardiac','accident','breathing','stroke','burn','poisoning','fracture','other')
                                      NOT NULL DEFAULT 'other',
    patient_name         VARCHAR(120) DEFAULT NULL,
    patient_address      TEXT         DEFAULT NULL,
    contact_number       VARCHAR(20)  NOT NULL,
    is_conscious         ENUM('yes','semi','no') NOT NULL DEFAULT 'yes',
    assistance_on_site   ENUM('yes','no','medical') NOT NULL DEFAULT 'no',
    additional_notes     TEXT         DEFAULT NULL,

    status               ENUM('pending','dispatched','en_route','arrived','resolved','cancelled')
                                      NOT NULL DEFAULT 'pending',

    submitted_by         INT          DEFAULT NULL,            -- FK → users.user_id (dispatcher)
    assigned_driver_id   INT          DEFAULT NULL,            -- FK → users.user_id (driver)

    submitted_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dispatch_time        DATETIME     DEFAULT NULL,
    resolved_at          DATETIME     DEFAULT NULL,

    CONSTRAINT fk_er_submitted_by
        FOREIGN KEY (submitted_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT fk_er_driver
        FOREIGN KEY (assigned_driver_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 3. Add assigned_emergency_id FK on drivers (after both tables exist)
-- ------------------------------------------------------------
ALTER TABLE drivers
    ADD CONSTRAINT fk_driver_emergency
        FOREIGN KEY (assigned_emergency_id) REFERENCES emergency_requests(emergency_id)
        ON DELETE SET NULL ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- 4. Link existing driver users to the drivers table
--    (run once — skip rows that already exist)
-- ------------------------------------------------------------
INSERT IGNORE INTO drivers (user_id, license_number, ambulance_number, ambulance_type, years_of_experience, status)
SELECT user_id, 'PENDING', NULL, 'basic', 0, 'available'
FROM   users
WHERE  role = 'driver'
AND    user_id NOT IN (SELECT user_id FROM drivers);


-- ------------------------------------------------------------
-- 5. Quick check — view what was inserted
-- ------------------------------------------------------------
SELECT d.driver_id, u.full_name, u.email, d.license_number,
       d.ambulance_number, d.status
FROM   drivers d
JOIN   users   u ON d.user_id = u.user_id;