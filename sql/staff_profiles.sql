-- ============================================================
-- staff_profiles table
-- hospital_db | ICT1242 Hospital Management System
-- Run this AFTER hospital_db.sql (users table must exist)
-- ============================================================

USE hospital_db;

CREATE TABLE IF NOT EXISTS staff_profiles (
    profile_id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED    NOT NULL,

    -- role stored here for quick reference
    role                ENUM('pharmacist','reception','dispatcher','driver') NOT NULL,

    -- pharmacist
    pharmacy_license    VARCHAR(60)     NULL,

    -- reception
    desk_extension      VARCHAR(20)     NULL,

    -- dispatcher
    zone                VARCHAR(100)    NULL,

    -- driver
    driver_license      VARCHAR(60)     NULL,
    vehicle_number      VARCHAR(30)     NULL,
    phone               VARCHAR(20)     NULL,

    -- shared
    shift               ENUM('morning','evening','night') NULL,

    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (profile_id),
    UNIQUE  KEY uk_sp_user (user_id),
    FOREIGN KEY fk_sp_user (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
