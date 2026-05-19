-- ============================================================
--  HOSPITAL_DB  –  FRESH INSTALL SCRIPT
--  Based on: hospital_db__1_.sql  (latest version, May 15 2026)
--  
--  HOW TO USE IN phpMyAdmin:
--  1. Top menu → SQL tab paste karanna
--  2. OR: Import tab → file select karanna
--  3. Run / Go click karanna
-- ============================================================

-- Step 1: Drop & recreate the database
DROP DATABASE IF EXISTS `hospital_db`;
CREATE DATABASE `hospital_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `hospital_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;   -- FK checks off during build
SET time_zone = "+00:00";

-- ============================================================
-- TABLE: users   (must be first – others FK to this)
-- ============================================================
CREATE TABLE `users` (
  `user_id`       int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`     varchar(120) NOT NULL,
  `email`         varchar(180) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role`          enum('admin','doctor','reception','pharmacist','patient','dispatcher','driver') NOT NULL,
  `status`        enum('active','inactive') NOT NULL DEFAULT 'inactive',
  `staff_id`      varchar(40) DEFAULT NULL,
  `department`    varchar(100) DEFAULT NULL,
  `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`,`full_name`,`email`,`password_hash`,`role`,`status`,`staff_id`,`department`,`created_at`) VALUES
(1,  'Doctor',                'docter@gmail.com',           '$2y$10$t4zPx.NYcSj1nnFeNoKhF.hjcc5BlWigDcwsYt7BVaGLuCk8bS.KW','doctor',     'active',   'dsfsdsdf','fdsd',             '2026-05-11 06:38:01'),
(2,  'partient',              'patient@gmail.com',          '$2y$10$UGc2wOJOCBOKpntvIYMu/uhPnVt8WClqvQz1ym0CTYNLFnGx7/Xx2','patient',    'active',   NULL,      NULL,               '2026-05-11 07:05:27'),
(3,  'Maheesha Hasaranga',    'hass.kariyawasam@gmail.com', '$2y$10$ffY4VwknqaBrEzS8QdiOzefPOaHPbC22/077.NXriqiXT43KbX33.', 'doctor',    'inactive', '5465',    '52',               '2026-05-11 10:57:52'),
(4,  'Doctor',                'doctor@gmail.com',           '$2y$10$QNBYb1v8K9Up/p1srwHiUuIwtaAqAvzJ5VuWkhU1s5WYmwKcnitAy','doctor',     'active',   '5465',    '52',               '2026-05-11 10:58:21'),
(5,  'cndms',                 'xcv@jds.com',                '$2y$10$6Z0kdpo4.CVCbzqmnwZaouI4MdCMPeWSt9C41bdyDJ4J5GbcKDEYG','doctor',     'inactive', 'fdsf',    'dff',              '2026-05-11 11:16:10'),
(6,  'Maheesha Hasaranga',    'hass@gmail.com',             '$2y$10$mU4BunEQtFDeRhePQgzBVucvJcwmbl1.YvA/sbRylAPxftTzw3eTC','admin',      'active',   NULL,      NULL,               '2026-05-12 16:22:36'),
(7,  'hass',                  'hass1@gmail.com',            '$2y$10$xfWgwhJC2nvyTjxTJDid8.pBpwfriMAGzaOVO5GZ7/qfqomLK0KCi','doctor',     'active',   '5465',    '52',               '2026-05-12 16:26:34'),
(8,  'paiants',               'kf@jdsjf.com',               '$2y$10$sWHBUlcXaHfbGvVSlyqEmO9oj20vmXbSlkXxExDu9XXKMmuEPGc6K','patient',    'active',   NULL,      NULL,               '2026-05-12 19:36:38'),
(9,  'Maheesha Hasaranga',    'hjb@gmail.com',              '$2y$10$jMrsXYXjtoEbUdm1lib63ej9eWbm.Q4kcAIzDKXWURw1llzmVAW4W','patient',    'active',   NULL,      NULL,               '2026-05-12 20:30:58'),
(10, 'Khm Hasaranga',         'hasspa@gmail.com',           '$2y$10$0.UzLCILR6YZORQpxdRuleJxcQFXO37.Q3uzZtUnq14zIeDCyKPB.','patient',    'active',   NULL,      NULL,               '2026-05-13 05:34:46'),
(11, 'sksmdcsz',              'h@h.h',                      '$2y$10$379UGwcjbIAAl6RIbgkrouYy4fJOhQrJddQJ7siwsdFZrhx/XrcKy','doctor',     'inactive', 'sdfs',    'fs',               '2026-05-13 10:03:08'),
(12, 'repihas',               'hassrep@gmail.com',          '$2y$10$McbaL.RuabaI2L3jecmZGefGsQd4NMr3n80kyGOFiY0MouVgmyIUy','reception', 'active',   'ds',      'fds',              '2026-05-13 10:42:26'),
(13, 'Reception',             'Reception@gmail.com',        '$2y$10$rxiVB/BhJ0t47eTZ3DLZGOR7YANkg1trPuGq.qnUJuXG7a/sEMzRq','reception', 'active',   'fd',      'fds',              '2026-05-14 11:40:01'),
(14, 'System Administrator',  'admin@medicare-hospital.lk', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',      'active',   NULL,      NULL,               '2026-05-14 11:50:08'),
(15, 'Dr. Nimal Silva',       'nimal@medicare.lk',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor',     'active',   'DOC001',  'General Surgery',  '2026-05-14 13:02:11'),
(16, 'Dr. Kamal Perera',      'kamal@medicare.lk',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor',     'active',   'DOC002',  'Orthopedics',      '2026-05-14 13:02:11'),
(17, 'Dr. Kumari Jayawardena','kumari@medicare.lk',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor',     'active',   'DOC003',  'Gynecology',       '2026-05-14 13:02:11'),
(18, 'Dr. Anura Bandara',     'anura@medicare.lk',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor',     'active',   'DOC004',  'Cardiology',       '2026-05-14 13:02:11'),
(19, 'Dr. Shani Weerasinghe', 'shani@medicare.lk',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor',     'active',   'DOC005',  'Anesthesiology',   '2026-05-14 13:02:11'),
(20, 'Dr. Ruwan Dissanayake', 'ruwan@medicare.lk',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor',     'active',   'DOC006',  'Neurosurgery',     '2026-05-14 13:02:11'),
(21, 'Dr. Priya Fernando',    'priya@medicare.lk',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor',     'active',   'DOC007',  'Anesthesiology',   '2026-05-14 13:02:11'),
(22, 'Chaminda Rajapaksa',    'chaminda@gmail.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(23, 'Dilrukshi Perera',      'dilrukshi@gmail.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(24, 'Suresh Jayasinghe',     'suresh@gmail.com',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(25, 'Malini Bandara',        'malini@gmail.com',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(26, 'Kasun Wickramasinghe',  'kasun@gmail.com',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(27, 'Nimali Gunawardena',    'nimali@gmail.com',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(28, 'Tharaka Silva',         'tharaka@gmail.com',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(29, 'Sanduni Rathnayake',    'sanduni@gmail.com',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(30, 'Roshan Mendis',         'roshan@gmail.com',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(31, 'Hiruni Abesinghe',      'hiruni@gmail.com',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient',    'active',   NULL,      NULL,               '2026-05-14 13:02:11'),
(32, 'hassPation',            'hasspatient@gmail.com',      '$2y$10$ztL0trPGPO0kscDlP4l/HewONHVooAAs8qE2wRmV.Ko3e93uSz6DC', 'patient',   'active',   NULL,      NULL,               '2026-05-14 20:25:42'),
(33, 'driverhass',            'driver@gmail.com',           '$2y$10$MPa8SQGPenbbg2QGS/veROf1b8i65V44nDE1mIc9SMSY99xMTfwOK', 'driver',    'inactive', '653',     '2',                '2026-05-14 21:13:47'),
(34, 'PharmacyHass',          'Pharmacy@gmail.com',         '$2y$10$KhO9nUShpP7nGrYUa8PgJeFEyGJ/Ox9KRtIDGKv8w4XrQWL5b1Ej2','pharmacist','active',   '5',       '65',               '2026-05-14 21:26:11'),
(35, 'sjsjkbc',               'Docter1@gmail.com',          '$2y$10$.318xdkriL5elp/.6n05ueCMarxrbwWxUhAS52udya9c4HhHLRKfK', 'doctor',    'active',   '123456',  '656',              '2026-05-15 04:28:04'),
(36, 'Aruna Perera',          'aruna@hospital.lk',          'hash_v1_1',                                                     'admin',      'active',   NULL,      NULL,               '2026-05-15 13:00:48'),
(37, 'Sanduni Silva',         'sanduni@hospital.lk',        'hash_v1_2',                                                     'doctor',     'active',   NULL,      NULL,               '2026-05-15 13:00:48'),
(38, 'Ravi Gamage',           'ravi@hospital.lk',           'hash_v1_3',                                                     'pharmacist', 'active',   NULL,      NULL,               '2026-05-15 13:00:48'),
(39, 'Nilanthi De Silva',     'nilanthi@hospital.lk',       'hash_v1_4',                                                     'reception',  'active',   NULL,      NULL,               '2026-05-15 13:00:48'),
(40, 'Kamal Siriwardana',     'kamal@hospital.lk',          'hash_v1_5',                                                     'patient',    'inactive', NULL,      NULL,               '2026-05-15 13:00:48');

ALTER TABLE `users` AUTO_INCREMENT = 63;

-- ============================================================
-- TABLE: rooms
-- ============================================================
CREATE TABLE `rooms` (
  `room_id`     int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_number` varchar(10) NOT NULL,
  `room_type`   enum('general_ward','semi_private','private','children','icu','maternity','recovery') NOT NULL,
  `floor`       tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `daily_rate`  decimal(8,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`room_id`),
  UNIQUE KEY `room_number` (`room_number`),
  KEY `idx_room_type` (`room_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rooms` (`room_id`,`room_number`,`room_type`,`floor`,`is_available`,`daily_rate`) VALUES
(1,'001','general_ward',1,1,50000.00);

ALTER TABLE `rooms` AUTO_INCREMENT = 3;

-- ============================================================
-- TABLE: beds
-- ============================================================
CREATE TABLE `beds` (
  `bed_id`      int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id`     int(10) UNSIGNED NOT NULL,
  `bed_number`  varchar(10) NOT NULL,
  `status`      enum('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`bed_id`),
  UNIQUE KEY `uk_room_bed` (`room_id`,`bed_number`),
  CONSTRAINT `fk_bed_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `beds` (`bed_id`,`room_id`,`bed_number`,`status`) VALUES
(1,1,'B1','available'),
(2,1,'B2','available');

ALTER TABLE `beds` AUTO_INCREMENT = 3;

-- ============================================================
-- TABLE: doctors
-- ============================================================
CREATE TABLE `doctors` (
  `doctor_id`        int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          int(10) UNSIGNED NOT NULL,
  `specialization`   varchar(120) NOT NULL,
  `qualifications`   text DEFAULT NULL,
  `license_number`   varchar(60) DEFAULT NULL,
  `consultation_fee` decimal(8,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`doctor_id`),
  UNIQUE KEY `uk_doc_user` (`user_id`),
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `doctors` (`doctor_id`,`user_id`,`specialization`,`qualifications`,`license_number`,`consultation_fee`) VALUES
(1,  7,  'fdvdff',          'sdfsdfd',              'dsfsf',  1000.00),
(2,  11, 'ckkjsds',         'dksf',                 'efjkes', 60000.00),
(3,  15, 'General Surgery', 'MBBS, MS (Surgery)',   'DOC001', 1500.00),
(4,  16, 'Orthopedics',     'MBBS, MD (Ortho)',     'DOC002', 2000.00),
(5,  17, 'Gynecology',      'MBBS, MD (Gynae)',     'DOC003', 1800.00),
(6,  18, 'Cardiology',      'MBBS, MD (Cardiology), FRCP','DOC004', 2500.00),
(7,  19, 'Anesthesiology',  'MBBS, MD (Anaes)',     'DOC005', 1200.00),
(8,  20, 'Neurosurgery',    'MBBS, MS (Neuro)',     'DOC006', 3000.00),
(9,  21, 'Anesthesiology',  'MBBS, MD (Anaes)',     'DOC007', 1200.00),
(10, 35, 'cas',             'jnkdcj,nx',            '546',    2500.00);

ALTER TABLE `doctors` AUTO_INCREMENT = 18;

-- ============================================================
-- TABLE: doctor_schedules
-- ============================================================
CREATE TABLE `doctor_schedules` (
  `schedule_id`   int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`     int(10) UNSIGNED NOT NULL,
  `day_of_week`   tinyint(4) NOT NULL COMMENT '0=Sun … 6=Sat',
  `start_time`    time NOT NULL,
  `end_time`      time NOT NULL,
  `slot_duration` tinyint(4) NOT NULL DEFAULT 15 COMMENT 'minutes per slot',
  PRIMARY KEY (`schedule_id`),
  KEY `fk_sched_doc` (`doctor_id`),
  CONSTRAINT `fk_sched_doc` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `doctor_schedules` (`schedule_id`,`doctor_id`,`day_of_week`,`start_time`,`end_time`,`slot_duration`) VALUES
(1,1,6,'08:30:00','17:30:00',15),
(2,6,5,'08:30:00','09:30:00',15);

ALTER TABLE `doctor_schedules` AUTO_INCREMENT = 3;

-- ============================================================
-- TABLE: patients
-- ============================================================
CREATE TABLE `patients` (
  `patient_id`        int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           int(10) UNSIGNED NOT NULL,
  `nic`               varchar(20) NOT NULL,
  `dob`               date NOT NULL,
  `gender`            enum('male','female','other') NOT NULL,
  `blood_type`        enum('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
  `phone`             varchar(20) NOT NULL,
  `address`           text DEFAULT NULL,
  `emergency_contact` varchar(120) DEFAULT NULL,
  `registered_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `nic`     (`nic`),
  UNIQUE KEY `uk_user` (`user_id`),
  UNIQUE KEY `uk_nic`  (`nic`),
  CONSTRAINT `fk_pat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `patients` (`patient_id`,`user_id`,`nic`,`dob`,`gender`,`blood_type`,`phone`,`address`,`emergency_contact`,`registered_at`) VALUES
(1,  6,  '566',          '2026-05-21','male',  NULL,'484564',     '',                  '',                                  '2026-05-12 16:22:55'),
(2,  8,  'dmsv ,',       '2026-05-21','male',  'A+','0774310265','m,ds',              'dm,ds',                             '2026-05-12 19:37:02'),
(3,  9,  'ff',           '2026-05-07','male',  NULL,'f',          'ff',                'f',                                 '2026-05-12 20:31:20'),
(4,  10, 'fddf',         '2026-05-27','male',  NULL,'542',        '5dff',              '',                                  '2026-05-13 05:35:05'),
(5,  22, '198534500125', '1985-06-15','male',  'B+','0712345601','No.12, Galle Rd, Colombo',       'Anoma Rajapaksa - 0712345700',  '2026-05-14 13:02:11'),
(6,  23, '199210200845', '1992-04-10','female','A+','0712345602','No.45, Kandy Rd, Kurunegala',    'Pradeep Perera - 0712345701',   '2026-05-14 13:02:11'),
(7,  24, '197804301256', '1978-11-20','male',  'O+','0712345603','No.7, Temple Rd, Gampaha',       'Kanthi Jayasinghe - 0712345702','2026-05-14 13:02:11'),
(8,  25, '199005150987', '1990-05-15','female','AB+','0712345604','No.33, Lake View, Kandy',       'Saman Bandara - 0712345703',    '2026-05-14 13:02:11'),
(9,  26, '199312250134', '1993-12-25','male',  'A-','0712345605','No.22, Main St, Matara',         'Rupa Wickramasinghe - 0712345704','2026-05-14 13:02:11'),
(10, 27, '198801180567', '1988-01-18','female','O-','0712345606','No.9, Beach Rd, Negombo',        'Sisira Gunawardena - 0712345705','2026-05-14 13:02:11'),
(11, 28, '199507080345', '1995-07-08','male',  'B-','0712345607','No.55, Hill St, Ratnapura',      'Kumari Silva - 0712345706',     '2026-05-14 13:02:11'),
(12, 29, '199109230678', '1991-09-23','female','B+','0712345608','No.18, Station Rd, Badulla',     'Nalika Rathnayake - 0712345707','2026-05-14 13:02:11'),
(13, 30, '198703060456', '1987-03-06','male',  'A+','0712345609','No.4, King St, Jaffna',          'Mala Mendis - 0712345708',      '2026-05-14 13:02:11'),
(14, 31, '199603140789', '1996-03-14','female','O+','0712345610','No.27, Church Rd, Anuradhapura', 'Nimal Abesinghe - 0712345709',  '2026-05-14 13:02:11'),
(15, 32, '200423201902', '2004-08-19','male',  'A+','0774310265','asjkd',             'daskn',                             '2026-05-14 20:26:18');

ALTER TABLE `patients` AUTO_INCREMENT = 31;

-- ============================================================
-- TABLE: appointments
-- ============================================================
CREATE TABLE `appointments` (
  `appointment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`     int(10) UNSIGNED NOT NULL,
  `doctor_id`      int(10) UNSIGNED NOT NULL,
  `appt_date`      date NOT NULL,
  `appt_time`      time NOT NULL,
  `source`         enum('online','opd') NOT NULL DEFAULT 'online',
  `status`         enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `ref_number`     varchar(20) NOT NULL,
  `notes`          text DEFAULT NULL,
  `booked_by`      int(10) UNSIGNED DEFAULT NULL COMMENT 'user_id of staff if OPD walk-in',
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`appointment_id`),
  UNIQUE KEY `ref_number` (`ref_number`),
  KEY `idx_appt_date`    (`appt_date`),
  KEY `idx_appt_doctor`  (`doctor_id`),
  KEY `idx_appt_patient` (`patient_id`),
  CONSTRAINT `fk_appt_doc` FOREIGN KEY (`doctor_id`)  REFERENCES `doctors`  (`doctor_id`),
  CONSTRAINT `fk_appt_pat` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `appointments` (`appointment_id`,`patient_id`,`doctor_id`,`appt_date`,`appt_time`,`source`,`status`,`ref_number`,`notes`,`booked_by`,`created_at`) VALUES
(1,1,2,'2025-10-25','10:00:00','opd',   'completed','REF-2025-001','Routine checkup',          NULL,'2026-05-13 07:40:52'),
(2,1,1,'2025-11-03','09:30:00','online','confirmed', 'REF-2025-002','Chest pain follow-up',    NULL,'2026-05-13 07:40:52'),
(3,2,3,'2025-11-10','11:00:00','opd',   'completed','REF-2025-003','Child fever',              13,  '2026-05-13 07:40:52'),
(4,2,2,'2025-11-15','14:00:00','opd',   'cancelled', 'REF-2025-004','Patient did not show up', 13,  '2026-05-13 07:40:52'),
(5,3,1,'2025-11-20','08:00:00','online','confirmed', 'REF-2025-005','ECG review',              NULL,'2026-05-13 07:40:52'),
(6,1,3,'2025-12-01','15:30:00','opd',   'pending',  'REF-2025-006','Post-op checkup',         13,  '2026-05-13 07:40:52'),
(7,3,2,'2025-12-05','10:30:00','online','completed','REF-2025-007','General consultation',     NULL,'2026-05-13 07:40:52'),
(8,2,1,'2025-12-10','13:00:00','opd',   'pending',  'REF-2025-008','Heart screening',         13,  '2026-05-13 07:40:52');

ALTER TABLE `appointments` AUTO_INCREMENT = 9;

-- ============================================================
-- TABLE: treatment_records
-- ============================================================
CREATE TABLE `treatment_records` (
  `record_id`      int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `diagnosis`      text NOT NULL,
  `clinical_notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `fk_tr_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: patient_billing_status
-- ============================================================
CREATE TABLE `patient_billing_status` (
  `billing_status_id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id`    varchar(20) NOT NULL,
  `patient_id`        int(11) NOT NULL,
  `payment_status`    enum('Pending','Done','Cancelled') NOT NULL DEFAULT 'Pending',
  `paid_amount`       decimal(10,2) DEFAULT 0.00,
  `payment_method`    enum('Cash','Card','Online Transfer','Bank Payment') DEFAULT NULL,
  `payment_date`      date DEFAULT NULL,
  `receipt_number`    varchar(30) DEFAULT NULL,
  `received_by`       int(11) DEFAULT NULL,
  `notes`             text DEFAULT NULL,
  `created_at`        timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`        timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`billing_status_id`),
  UNIQUE KEY `uq_appointment` (`appointment_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_status`  (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `patient_billing_status` (`billing_status_id`,`appointment_id`,`patient_id`,`payment_status`,`paid_amount`,`payment_method`,`payment_date`,`receipt_number`,`received_by`,`notes`,`created_at`,`updated_at`) VALUES
(1,'A01',1,'Done',    2500.00,'Cash',NULL,'RCP-2025-00001',3,NULL,'2026-05-15 14:12:18','2026-05-15 14:12:18'),
(2,'A02',1,'Pending', 0.00,  NULL, NULL, NULL,            NULL,NULL,'2026-05-15 14:12:18','2026-05-15 14:12:18'),
(3,'A03',2,'Cancelled',0.00, NULL, NULL, NULL,            NULL,NULL,'2026-05-15 14:12:18','2026-05-15 14:12:18');

ALTER TABLE `patient_billing_status` AUTO_INCREMENT = 4;

-- ============================================================
-- TABLE: pharmacy_drugs
-- ============================================================
CREATE TABLE `pharmacy_drugs` (
  `drug_id`      varchar(10) NOT NULL,
  `drug_name`    varchar(120) NOT NULL,
  `category`     varchar(80) NOT NULL,
  `unit`         varchar(30) NOT NULL DEFAULT 'tablet',
  `unit_price`   decimal(8,2) NOT NULL,
  `stock_qty`    int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `is_active`    tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`drug_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: prescriptions
-- ============================================================
CREATE TABLE `prescriptions` (
  `prescription_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id`  int(10) UNSIGNED NOT NULL,
  `drug_id`         int(10) UNSIGNED NOT NULL,
  `dosage`          varchar(80) NOT NULL,
  `frequency`       varchar(80) NOT NULL,
  `duration_days`   smallint(6) NOT NULL DEFAULT 7,
  `status`          enum('pending','dispensed') NOT NULL DEFAULT 'pending',
  `dispensed_by`    int(10) UNSIGNED DEFAULT NULL COMMENT 'pharmacist user_id',
  `dispensed_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`prescription_id`),
  KEY `fk_rx_appt` (`appointment_id`),
  KEY `fk_rx_drug` (`drug_id`),
  CONSTRAINT `fk_rx_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: billing_invoices
-- ============================================================
CREATE TABLE `billing_invoices` (
  `invoice_id`     int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(20) DEFAULT NULL,
  `patient_id`     int(10) UNSIGNED NOT NULL,
  `admission_id`   int(10) UNSIGNED DEFAULT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `total_amount`   decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount`    decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance`        decimal(10,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `status`         enum('open','settled') NOT NULL DEFAULT 'open',
  `notes`          text DEFAULT NULL,
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`     timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_inv_pat` (`patient_id`),
  KEY `fk_inv_adm` (`admission_id`),
  KEY `fk_inv_apt` (`appointment_id`),
  CONSTRAINT `fk_inv_pat` FOREIGN KEY (`patient_id`)     REFERENCES `patients`     (`patient_id`),
  CONSTRAINT `fk_inv_apt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `trg_invoice_number` BEFORE INSERT ON `billing_invoices` FOR EACH ROW BEGIN
    IF NEW.invoice_number IS NULL THEN
        SET NEW.invoice_number = CONCAT('INV-', LPAD(
            (SELECT IFNULL(MAX(invoice_id),0) + 1 FROM billing_invoices i2),
        5,'0'));
    END IF;
END$$
DELIMITER ;

INSERT INTO `billing_invoices` (`invoice_id`,`invoice_number`,`patient_id`,`admission_id`,`appointment_id`,`total_amount`,`paid_amount`,`status`,`notes`,`created_at`,`updated_at`) VALUES
(1,'INV-00001',3,NULL,NULL,0.00,0.00,'open','mkf','2026-05-15 12:48:14','2026-05-15 12:48:14');

ALTER TABLE `billing_invoices` AUTO_INCREMENT = 2;

-- ============================================================
-- TABLE: billing_items
-- ============================================================
CREATE TABLE `billing_items` (
  `item_id`     int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`  int(10) UNSIGNED NOT NULL,
  `description` varchar(180) NOT NULL,
  `category`    enum('consultation','room','theatre','pharmacy','lab','misc') NOT NULL DEFAULT 'misc',
  `unit_price`  decimal(8,2) NOT NULL,
  `quantity`    smallint(6) NOT NULL DEFAULT 1,
  `line_total`  decimal(10,2) GENERATED ALWAYS AS (`unit_price` * `quantity`) STORED,
  `added_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `fk_item_inv` (`invoice_id`),
  CONSTRAINT `fk_item_inv` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`invoice_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: payments
-- ============================================================
CREATE TABLE `payments` (
  `payment_id`     int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`     int(10) UNSIGNED NOT NULL,
  `amount`         decimal(10,2) NOT NULL,
  `payment_type`   enum('full','advance') NOT NULL DEFAULT 'full',
  `payment_method` enum('cash','card','insurance','bank_transfer') NOT NULL DEFAULT 'cash',
  `receipt_number` varchar(20) NOT NULL,
  `notes`          text DEFAULT NULL,
  `received_by`    int(10) UNSIGNED NOT NULL COMMENT 'reception user_id',
  `paid_at`        timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `fk_pay_inv` (`invoice_id`),
  CONSTRAINT `fk_pay_inv` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: admission_requests
-- ============================================================
CREATE TABLE `admission_requests` (
  `request_id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`         int(10) UNSIGNED NOT NULL,
  `requested_by`       int(10) UNSIGNED NOT NULL COMMENT 'reception user_id',
  `assigned_doctor_id` int(10) UNSIGNED NOT NULL,
  `admission_reason`   text NOT NULL,
  `request_notes`      text DEFAULT NULL,
  `status`             enum('pending','approved','rejected','admitted','cancelled') NOT NULL DEFAULT 'pending',
  `approved_room_type` enum('general_ward','semi_private','private','children','icu','maternity','recovery') DEFAULT NULL,
  `meal_required`      tinyint(1) DEFAULT 1,
  `meal_type`          enum('hospital_standard','doctor_prescribed','liquid','diabetic','low_salt','no_meal','outside_food') DEFAULT NULL,
  `meal_notes`         text DEFAULT NULL,
  `priority_level`     enum('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  `doctor_notes`       text DEFAULT NULL,
  `reviewed_at`        timestamp NULL DEFAULT NULL,
  `room_id`            int(10) UNSIGNED DEFAULT NULL,
  `bed_id`             int(10) UNSIGNED DEFAULT NULL,
  `admission_id`       int(10) UNSIGNED DEFAULT NULL COMMENT 'links to admissions after admitted',
  `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `idx_req_patient` (`patient_id`),
  KEY `idx_req_doctor`  (`assigned_doctor_id`),
  KEY `idx_req_status`  (`status`),
  KEY `fk_req_room`     (`room_id`),
  KEY `fk_req_bed`      (`bed_id`),
  CONSTRAINT `fk_req_patient` FOREIGN KEY (`patient_id`)         REFERENCES `patients` (`patient_id`),
  CONSTRAINT `fk_req_doctor`  FOREIGN KEY (`assigned_doctor_id`) REFERENCES `doctors`  (`doctor_id`),
  CONSTRAINT `fk_req_room`    FOREIGN KEY (`room_id`)            REFERENCES `rooms`    (`room_id`),
  CONSTRAINT `fk_req_bed`     FOREIGN KEY (`bed_id`)             REFERENCES `beds`     (`bed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admission_requests` (`request_id`,`patient_id`,`requested_by`,`assigned_doctor_id`,`admission_reason`,`request_notes`,`status`,`approved_room_type`,`meal_required`,`meal_type`,`meal_notes`,`priority_level`,`doctor_notes`,`reviewed_at`,`room_id`,`bed_id`,`admission_id`,`created_at`,`updated_at`) VALUES
(1,1,13,1,'rjfdkfs','kfjds','pending',NULL,1,NULL,NULL,'normal',NULL,NULL,NULL,NULL,NULL,'2026-05-14 12:13:09','2026-05-14 12:13:09'),
(2,1,13,1,'dkj','jkfds','approved','general_ward',1,'doctor_prescribed','jkm,','urgent','jm,k','2026-05-14 12:20:35',NULL,NULL,NULL,'2026-05-14 12:18:05','2026-05-14 12:20:35'),
(3,1,13,1,'dkj','jkfds','pending',NULL,1,NULL,NULL,'normal',NULL,NULL,NULL,NULL,NULL,'2026-05-14 12:20:45','2026-05-14 12:20:45');

ALTER TABLE `admission_requests` AUTO_INCREMENT = 4;

-- ============================================================
-- TABLE: admissions
-- ============================================================
CREATE TABLE `admissions` (
  `admission_id`   int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`     int(10) UNSIGNED NOT NULL,
  `room_id`        int(10) UNSIGNED NOT NULL,
  `bed_id`         int(10) UNSIGNED DEFAULT NULL,
  `request_id`     int(10) UNSIGNED DEFAULT NULL,
  `doctor_id`      int(10) UNSIGNED NOT NULL,
  `admitted_by`    int(10) UNSIGNED NOT NULL COMMENT 'reception user_id',
  `admission_date` date NOT NULL,
  `discharge_date` date DEFAULT NULL,
  `dietary_notes`  text DEFAULT NULL,
  `meal_required`  tinyint(1) NOT NULL DEFAULT 1,
  `meal_type`      enum('hospital_standard','doctor_prescribed','liquid','diabetic','low_salt','no_meal','outside_food') DEFAULT NULL,
  `priority_level` enum('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  `advance_paid`   decimal(10,2) NOT NULL DEFAULT 0.00,
  `invoice_id`     int(10) UNSIGNED DEFAULT NULL,
  `status`         enum('admitted','discharged') NOT NULL DEFAULT 'admitted',
  PRIMARY KEY (`admission_id`),
  KEY `fk_adm_pat` (`patient_id`),
  KEY `fk_adm_room` (`room_id`),
  KEY `fk_adm_doc` (`doctor_id`),
  KEY `fk_adm_bed` (`bed_id`),
  KEY `fk_adm_req` (`request_id`),
  CONSTRAINT `fk_adm_pat`  FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  CONSTRAINT `fk_adm_room` FOREIGN KEY (`room_id`)    REFERENCES `rooms`    (`room_id`),
  CONSTRAINT `fk_adm_doc`  FOREIGN KEY (`doctor_id`)  REFERENCES `doctors`  (`doctor_id`),
  CONSTRAINT `fk_adm_bed`  FOREIGN KEY (`bed_id`)     REFERENCES `beds`     (`bed_id`),
  CONSTRAINT `fk_adm_req`  FOREIGN KEY (`request_id`) REFERENCES `admission_requests` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- billing_invoices.admission_id FK can now be added (admissions table exists)
ALTER TABLE `billing_invoices`
  ADD CONSTRAINT `fk_inv_adm` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`admission_id`);

-- ============================================================
-- TABLE: ambulances
-- ============================================================
CREATE TABLE `ambulances` (
  `ambulance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_no`   varchar(20) NOT NULL,
  `driver_name`  varchar(100) NOT NULL,
  `driver_phone` varchar(15) NOT NULL,
  `status`       enum('available','dispatched','maintenance') DEFAULT 'available',
  `last_location` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ambulance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ambulances` (`ambulance_id`,`vehicle_no`,`driver_name`,`driver_phone`,`status`,`last_location`) VALUES
(1,'AMB-001','Kamal Perera', '0771234567','available',NULL),
(2,'AMB-002','Sunil Fernando','0779876543','available',NULL),
(3,'AMB-003','Nimal Silva',  '0761122334','maintenance',NULL);

ALTER TABLE `ambulances` AUTO_INCREMENT = 4;

-- ============================================================
-- TABLE: emergency_requests
-- ============================================================
CREATE TABLE `emergency_requests` (
  `request_id`    int(11) NOT NULL AUTO_INCREMENT,
  `ticket_no`     varchar(20) NOT NULL,
  `patient_id`    int(11) DEFAULT NULL,
  `requester_name` varchar(100) NOT NULL,
  `phone`         varchar(15) NOT NULL,
  `gps_lat`       decimal(10,8) DEFAULT NULL,
  `gps_lng`       decimal(11,8) DEFAULT NULL,
  `description`   text DEFAULT NULL,
  `status`        enum('pending','dispatched','en_route','arrived','closed') DEFAULT 'pending',
  `ambulance_id`  int(11) DEFAULT NULL,
  `dispatcher_id` int(11) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `created_at`    datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `ticket_no` (`ticket_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: newborn_records
-- ============================================================
CREATE TABLE `newborn_records` (
  `newborn_id`    int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mother_id`     int(10) UNSIGNED NOT NULL COMMENT 'patients.patient_id',
  `operation_id`  int(10) UNSIGNED DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `weight_kg`     decimal(4,2) DEFAULT NULL,
  `health_status` varchar(80) DEFAULT NULL,
  `assigned_room` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`newborn_id`),
  KEY `fk_nb_mother` (`mother_id`),
  CONSTRAINT `fk_nb_mother` FOREIGN KEY (`mother_id`) REFERENCES `patients` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: theatre_operations
-- ============================================================
CREATE TABLE `theatre_operations` (
  `operation_id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`           int(10) UNSIGNED NOT NULL,
  `surgeon_id`           int(10) UNSIGNED NOT NULL,
  `anaesthetist_id`      int(10) UNSIGNED DEFAULT NULL,
  `assistant_doctor_id`  int(10) UNSIGNED DEFAULT NULL,
  `operation_type`       varchar(120) NOT NULL,
  `theatre_number`       tinyint(4) NOT NULL,
  `scheduled_date`       date NOT NULL,
  `scheduled_time`       time NOT NULL,
  `scheduled_at`         timestamp GENERATED ALWAYS AS (timestamp(`scheduled_date`,`scheduled_time`)) STORED,
  `status`               enum('scheduled','confirmed','in_progress','completed','cancelled','transferred') NOT NULL DEFAULT 'scheduled',
  `pre_op_notes`         text DEFAULT NULL,
  `post_op_notes`        text DEFAULT NULL,
  `recovery_instructions` text DEFAULT NULL,
  `post_op_room_type`    varchar(40) DEFAULT NULL,
  `created_by`           int(10) UNSIGNED DEFAULT NULL,
  `created_at`           timestamp NOT NULL DEFAULT current_timestamp(),
  `theatre_charge`       decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee charged for this operation',
  PRIMARY KEY (`operation_id`),
  KEY `idx_theatre_date`   (`theatre_number`,`scheduled_date`,`scheduled_time`),
  KEY `fk_th_patient`      (`patient_id`),
  KEY `fk_th_surgeon`      (`surgeon_id`),
  KEY `fk_th_anaes`        (`anaesthetist_id`),
  KEY `fk_th_assistant`    (`assistant_doctor_id`),
  KEY `fk_th_created`      (`created_by`),
  CONSTRAINT `fk_th_patient`   FOREIGN KEY (`patient_id`)          REFERENCES `patients` (`patient_id`),
  CONSTRAINT `fk_th_surgeon`   FOREIGN KEY (`surgeon_id`)          REFERENCES `users`    (`user_id`),
  CONSTRAINT `fk_th_anaes`     FOREIGN KEY (`anaesthetist_id`)     REFERENCES `users`    (`user_id`),
  CONSTRAINT `fk_th_assistant` FOREIGN KEY (`assistant_doctor_id`) REFERENCES `users`    (`user_id`),
  CONSTRAINT `fk_th_created`   FOREIGN KEY (`created_by`)          REFERENCES `users`    (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `theatre_operations` (`operation_id`,`patient_id`,`surgeon_id`,`anaesthetist_id`,`assistant_doctor_id`,`operation_type`,`theatre_number`,`scheduled_date`,`scheduled_time`,`status`,`pre_op_notes`,`post_op_notes`,`recovery_instructions`,`post_op_room_type`,`created_by`,`created_at`,`theatre_charge`) VALUES
(1, 5, 15,19,16,'Appendectomy',                              1,'2026-05-01','08:00:00','completed','Patient cleared for surgery. Fasting since midnight.','Surgery successful. No complications observed.','Bed rest for 48hrs. Liquid diet for first day. Follow-up in 7 days.','semi_private',6,'2026-05-14 13:02:11',45000.00),
(2, 6, 16,21,NULL,'Left Knee Total Replacement',             2,'2026-05-03','09:30:00','completed','Pre-op X-rays reviewed. Blood group confirmed A+.','Implant fitted correctly. Post-op physio advised.','Physiotherapy starting day 3. No weight bearing for 2 weeks.','private',6,'2026-05-14 13:02:11',85000.00),
(3, 8, 17,19,NULL,'Caesarean Section',                      3,'2026-05-05','10:00:00','completed','Elective C-section at 38 weeks. Mother and fetus stable.','Healthy baby girl delivered 3.2 kg. Mother recovering well.','Breastfeeding support provided. Wound care every 2 days.','private',6,'2026-05-14 13:02:11',55000.00),
(4, 7, 18,21,20,'Coronary Artery Bypass Graft (Triple)',     1,'2026-05-07','07:00:00','completed','High-risk patient. Pre-op cardiac clearance obtained.','Triple bypass completed. Patient moved to ICU post-op.','ICU monitoring for 48hrs. Cardiac rehab to begin week 2.','icu',6,'2026-05-14 13:02:11',120000.00),
(5, 9, 15,19,NULL,'Laparoscopic Cholecystectomy (Gallbladder Removal)',2,'2026-05-09','11:00:00','completed','Ultrasound confirmed gallstones. Laparoscopic approach planned.','Gallbladder removed successfully. 4 laparoscopic ports used.','Soft diet for 1 week. Avoid fatty foods for 1 month.','semi_private',6,'2026-05-14 13:02:11',40000.00),
(6, 10,20,21,16,'Brain Tumour Excision',                     1,'2026-05-14','08:00:00','in_progress','MRI reviewed. Tumour location mapped. High-risk procedure.',NULL,NULL,'icu',6,'2026-05-14 13:02:11',150000.00),
(7, 11,16,19,NULL,'Hip Replacement',                         2,'2026-05-16','09:00:00','confirmed','Right hip degeneration confirmed. Cemented total hip replacement planned.',NULL,NULL,'private',6,'2026-05-14 13:02:11',90000.00),
(8, 12,15,21,NULL,'Hernia Repair (Inguinal Mesh)',            3,'2026-05-17','10:30:00','confirmed','Reducible inguinal hernia. Mesh repair under GA planned.',NULL,NULL,'semi_private',6,'2026-05-14 13:02:11',32000.00),
(9, 13,18,19,15,'Valve Replacement (Mitral)',                 1,'2026-05-20','07:30:00','scheduled','Severe mitral regurgitation. Mechanical valve replacement planned.',NULL,NULL,'icu',6,'2026-05-14 13:02:11',130000.00),
(10,14,17,21,NULL,'Ovarian Cyst Removal (Laparoscopic)',     4,'2026-05-22','13:00:00','scheduled','10cm endometriotic cyst confirmed on ultrasound.',NULL,NULL,'semi_private',6,'2026-05-14 13:02:11',38000.00),
(11,5, 15,19,NULL,'Pilonidal Cyst Excision',                  4,'2026-05-08','14:00:00','cancelled','Minor cyst. Cancelled due to patient high BP on day of surgery.',NULL,NULL,NULL,6,'2026-05-14 13:02:11',15000.00),
(12,5, 16,17,16,'hgfcvb',                                    2,'2026-05-15','14:18:00','confirmed','kjhg','','',NULL,1,'2026-05-14 17:46:07',85000.00),
(13,5, 15,19,16,'Appendectomy',                              1,'2026-05-01','08:00:00','completed','Patient cleared for surgery. Fasting since midnight.','Surgery successful. No complications observed.','Bed rest for 48hrs. Liquid diet for first day. Follow-up in 7 days.','semi_private',6,'2026-05-15 14:12:57',45000.00),
(14,6, 16,21,NULL,'Left Knee Total Replacement',             2,'2026-05-03','09:30:00','completed','Pre-op X-rays reviewed. Blood group confirmed A+.','Implant fitted correctly. Post-op physio advised.','Physiotherapy starting day 3. No weight bearing for 2 weeks.','private',6,'2026-05-15 14:12:57',85000.00),
(15,8, 17,19,NULL,'Caesarean Section',                      3,'2026-05-05','10:00:00','completed','Elective C-section at 38 weeks. Mother and fetus stable.','Healthy baby girl delivered 3.2 kg. Mother recovering well.','Breastfeeding support provided. Wound care every 2 days.','private',6,'2026-05-15 14:12:57',55000.00),
(16,7, 18,21,20,'Coronary Artery Bypass Graft (Triple)',     1,'2026-05-07','07:00:00','completed','High-risk patient. Pre-op cardiac clearance obtained.','Triple bypass completed. Patient moved to ICU post-op.','ICU monitoring for 48hrs. Cardiac rehab to begin week 2.','icu',6,'2026-05-15 14:12:57',120000.00),
(17,9, 15,19,NULL,'Laparoscopic Cholecystectomy (Gallbladder Removal)',2,'2026-05-09','11:00:00','completed','Ultrasound confirmed gallstones. Laparoscopic approach planned.','Gallbladder removed successfully. 4 laparoscopic ports used.','Soft diet for 1 week. Avoid fatty foods for 1 month.','semi_private',6,'2026-05-15 14:12:57',40000.00),
(18,10,20,21,16,'Brain Tumour Excision',                     1,'2026-05-14','08:00:00','in_progress','MRI reviewed. Tumour location mapped. High-risk procedure.',NULL,NULL,'icu',6,'2026-05-15 14:12:57',150000.00),
(19,11,16,19,NULL,'Hip Replacement',                         2,'2026-05-16','09:00:00','confirmed','Right hip degeneration confirmed. Cemented total hip replacement planned.',NULL,NULL,'private',6,'2026-05-15 14:12:57',90000.00),
(20,12,15,21,NULL,'Hernia Repair (Inguinal Mesh)',            3,'2026-05-17','10:30:00','confirmed','Reducible inguinal hernia. Mesh repair under GA planned.',NULL,NULL,'semi_private',6,'2026-05-15 14:12:57',32000.00),
(21,13,18,19,15,'Valve Replacement (Mitral)',                 1,'2026-05-20','07:30:00','scheduled','Severe mitral regurgitation. Mechanical valve replacement planned.',NULL,NULL,'icu',6,'2026-05-15 14:12:57',130000.00),
(22,14,17,21,NULL,'Ovarian Cyst Removal (Laparoscopic)',     4,'2026-05-22','13:00:00','scheduled','10cm endometriotic cyst confirmed on ultrasound.',NULL,NULL,'semi_private',6,'2026-05-15 14:12:57',38000.00),
(23,5, 15,19,NULL,'Pilonidal Cyst Excision',                  4,'2026-05-08','14:00:00','cancelled','Minor cyst. Cancelled due to patient high BP on day of surgery.',NULL,NULL,NULL,6,'2026-05-15 14:12:57',15000.00),
(24,5, 15,19,16,'Appendectomy (Emergency)',                  1,'2026-05-20','09:00:00','scheduled','Patient has acute appendicitis. NPO since midnight.',NULL,'Monitor for infection, pain management with paracetamol.','General Ward',15,'2026-05-15 14:13:16',65000.00),
(25,6, 16,19,NULL,'Total Knee Replacement',                  2,'2026-05-21','14:30:00','confirmed','Osteoarthritis patient. Pre-op physio done.','Surgery successful. No complications.','Physiotherapy starting day 1 post-op.','Semi-Private',6,'2026-05-15 14:13:16',285000.00),
(26,8, 17,19,18,'Caesarean Section',                         1,'2026-05-22','08:00:00','in_progress','37 weeks gestation, previous C-section.',NULL,'Breastfeeding support, wound care.','Private',17,'2026-05-15 14:13:16',125000.00),
(27,7, 18,19,NULL,'Coronary Artery Bypass Graft (CABG)',     3,'2026-05-25','10:00:00','scheduled','Triple vessel disease confirmed by angiogram.',NULL,'Cardiac rehab program mandatory.','ICU',6,'2026-05-15 14:13:16',675000.00),
(28,5, 15,19,16,'Cholecystectomy (Laparoscopic)',             2,'2026-05-15','11:00:00','completed','Gallstones with cholecystitis.','Surgery uneventful. 4 ports used.','Low fat diet for 2 weeks.','General Ward',15,'2026-05-15 14:13:16',95000.00);

ALTER TABLE `theatre_operations` AUTO_INCREMENT = 29;

-- ============================================================
-- TABLE: theatre_billing
-- ============================================================
CREATE TABLE `theatre_billing` (
  `theatre_billing_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_id`       int(10) UNSIGNED NOT NULL,
  `patient_id`         int(10) UNSIGNED NOT NULL,
  `invoice_id`         int(10) UNSIGNED DEFAULT NULL COMMENT 'FK billing_invoices — set when invoice created',
  `operation_charge`   decimal(10,2) NOT NULL DEFAULT 0.00,
  `anaesthesia_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `consumables_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_charge`       decimal(10,2) GENERATED ALWAYS AS (`operation_charge` + `anaesthesia_charge` + `consumables_charge`) STORED,
  `billing_status`     enum('pending','invoiced','paid','waived') NOT NULL DEFAULT 'pending',
  `notes`              text DEFAULT NULL,
  `created_by`         int(10) UNSIGNED DEFAULT NULL COMMENT 'reception/admin user_id',
  `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`theatre_billing_id`),
  UNIQUE KEY `uq_operation` (`operation_id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_status`  (`billing_status`),
  KEY `fk_tb_inv`   (`invoice_id`),
  CONSTRAINT `fk_tb_op`  FOREIGN KEY (`operation_id`) REFERENCES `theatre_operations` (`operation_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tb_pat` FOREIGN KEY (`patient_id`)   REFERENCES `patients`           (`patient_id`),
  CONSTRAINT `fk_tb_inv` FOREIGN KEY (`invoice_id`)   REFERENCES `billing_invoices`   (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `theatre_billing` (`theatre_billing_id`,`operation_id`,`patient_id`,`invoice_id`,`operation_charge`,`anaesthesia_charge`,`consumables_charge`,`billing_status`,`notes`,`created_by`,`created_at`,`updated_at`) VALUES
(1, 1, 5, NULL,45000.00, 6750.00, 3600.00,'invoiced','Auto-generated billing for: Appendectomy',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(2, 2, 6, NULL,85000.00,12750.00, 6800.00,'invoiced','Auto-generated billing for: Left Knee Total Replacement',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(3, 3, 8, NULL,55000.00, 8250.00, 4400.00,'invoiced','Auto-generated billing for: Caesarean Section',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(4, 4, 7, NULL,120000.00,18000.00,9600.00,'invoiced','Auto-generated billing for: Coronary Artery Bypass Graft (Triple)',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(5, 5, 9, NULL,40000.00, 6000.00, 3200.00,'invoiced','Auto-generated billing for: Laparoscopic Cholecystectomy (Gallbladder Removal)',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(6, 6, 10,NULL,150000.00,22500.00,12000.00,'pending','Auto-generated billing for: Brain Tumour Excision',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(7, 7, 11,NULL,90000.00,13500.00, 7200.00,'pending','Auto-generated billing for: Hip Replacement',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(8, 8, 12,NULL,32000.00, 4800.00, 2560.00,'pending','Auto-generated billing for: Hernia Repair (Inguinal Mesh)',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(9, 9, 13,NULL,130000.00,19500.00,10400.00,'pending','Auto-generated billing for: Valve Replacement (Mitral)',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(10,10,14,NULL,38000.00, 5700.00, 3040.00,'paid',   'Auto-generated billing for: Ovarian Cyst Removal (Laparoscopic)',6,'2026-05-14 13:02:11','2026-05-14 13:06:36'),
(11,11, 5,NULL,15000.00, 2250.00, 1200.00,'waived', 'Auto-generated billing for: Pilonidal Cyst Excision',6,'2026-05-14 13:02:11','2026-05-14 13:02:11'),
(12,13, 5,NULL,45000.00, 6750.00, 3600.00,'invoiced','Auto-generated billing for: Appendectomy',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(13,14, 6,NULL,85000.00,12750.00, 6800.00,'invoiced','Auto-generated billing for: Left Knee Total Replacement',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(14,15, 8,NULL,55000.00, 8250.00, 4400.00,'invoiced','Auto-generated billing for: Caesarean Section',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(15,16, 7,NULL,120000.00,18000.00,9600.00,'invoiced','Auto-generated billing for: Coronary Artery Bypass Graft (Triple)',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(16,17, 9,NULL,40000.00, 6000.00, 3200.00,'invoiced','Auto-generated billing for: Laparoscopic Cholecystectomy (Gallbladder Removal)',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(17,18,10,NULL,150000.00,22500.00,12000.00,'pending','Auto-generated billing for: Brain Tumour Excision',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(18,19,11,NULL,90000.00,13500.00, 7200.00,'pending','Auto-generated billing for: Hip Replacement',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(19,20,12,NULL,32000.00, 4800.00, 2560.00,'pending','Auto-generated billing for: Hernia Repair (Inguinal Mesh)',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(20,21,13,NULL,130000.00,19500.00,10400.00,'pending','Auto-generated billing for: Valve Replacement (Mitral)',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(21,22,14,NULL,38000.00, 5700.00, 3040.00,'pending','Auto-generated billing for: Ovarian Cyst Removal (Laparoscopic)',6,'2026-05-15 14:12:57','2026-05-15 14:12:57'),
(22,23, 5,NULL,15000.00, 2250.00, 1200.00,'waived', 'Auto-generated billing for: Pilonidal Cyst Excision',6,'2026-05-15 14:12:57','2026-05-15 14:12:57');

ALTER TABLE `theatre_billing` AUTO_INCREMENT = 27;

-- ============================================================
-- Re-enable foreign key checks
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DONE  ✓
-- Tables created: users, rooms, beds, doctors, doctor_schedules,
--   patients, appointments, treatment_records, patient_billing_status,
--   pharmacy_drugs, prescriptions, billing_invoices, billing_items,
--   payments, admission_requests, admissions, ambulances,
--   emergency_requests, newborn_records, theatre_operations,
--   theatre_billing
-- ============================================================