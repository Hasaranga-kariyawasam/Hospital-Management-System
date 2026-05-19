-- ============================================================
--  Theatre Module — Dummy Data INSERT Queries
--  Hospital Management System | Group 05
--
--  ⚠️  Run hospital_db.sql FIRST, THEN theatre_billing_sql.sql,
--      THEN this file.
--
--  Tables covered:
--    1. users          (doctor/anaesthetist users)
--    2. doctors        (doctor profiles)
--    3. patients / users (patient accounts)
--    4. theatre_operations
--    5. theatre_billing
-- ============================================================

USE hospital_db;

-- ============================================================
-- STEP 1 — Doctor / Anaesthetist Users
--          (skip if already inserted from hospital_db.sql)
-- ============================================================

INSERT IGNORE INTO users
    (full_name, email, password_hash, role, status, staff_id, department)
VALUES
    ('Dr. Nimal Silva',       'nimal@medicare.lk',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'DOC001', 'General Surgery'),
    ('Dr. Kamal Perera',      'kamal@medicare.lk',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'DOC002', 'Orthopedics'),
    ('Dr. Kumari Jayawardena','kumari@medicare.lk',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'DOC003', 'Gynecology'),
    ('Dr. Anura Bandara',     'anura@medicare.lk',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'DOC004', 'Cardiology'),
    ('Dr. Shani Weerasinghe', 'shani@medicare.lk',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'DOC005', 'Anesthesiology'),
    ('Dr. Ruwan Dissanayake', 'ruwan@medicare.lk',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'DOC006', 'Neurosurgery'),
    ('Dr. Priya Fernando',    'priya@medicare.lk',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'DOC007', 'Anesthesiology');

-- ============================================================
-- STEP 2 — Doctor Profiles
-- ============================================================

INSERT IGNORE INTO doctors
    (user_id, specialization, qualifications, license_number, consultation_fee)
VALUES
    ((SELECT user_id FROM users WHERE email='nimal@medicare.lk'),  'General Surgery',  'MBBS, MS (Surgery)',           'DOC001', 1500.00),
    ((SELECT user_id FROM users WHERE email='kamal@medicare.lk'),  'Orthopedics',      'MBBS, MD (Ortho)',             'DOC002', 2000.00),
    ((SELECT user_id FROM users WHERE email='kumari@medicare.lk'), 'Gynecology',       'MBBS, MD (Gynae)',             'DOC003', 1800.00),
    ((SELECT user_id FROM users WHERE email='anura@medicare.lk'),  'Cardiology',       'MBBS, MD (Cardiology), FRCP', 'DOC004', 2500.00),
    ((SELECT user_id FROM users WHERE email='shani@medicare.lk'),  'Anesthesiology',   'MBBS, MD (Anaes)',             'DOC005', 1200.00),
    ((SELECT user_id FROM users WHERE email='ruwan@medicare.lk'),  'Neurosurgery',     'MBBS, MS (Neuro)',             'DOC006', 3000.00),
    ((SELECT user_id FROM users WHERE email='priya@medicare.lk'),  'Anesthesiology',   'MBBS, MD (Anaes)',             'DOC007', 1200.00);

-- ============================================================
-- STEP 3 — Patient Users + Patient Profiles
--          (10 sample patients)
-- ============================================================

INSERT IGNORE INTO users
    (full_name, email, password_hash, role, status)
VALUES
    ('Chaminda Rajapaksa',  'chaminda@gmail.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Dilrukshi Perera',    'dilrukshi@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Suresh Jayasinghe',   'suresh@gmail.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Malini Bandara',      'malini@gmail.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Kasun Wickramasinghe','kasun@gmail.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Nimali Gunawardena',  'nimali@gmail.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Tharaka Silva',       'tharaka@gmail.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Sanduni Rathnayake',  'sanduni@gmail.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Roshan Mendis',       'roshan@gmail.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active'),
    ('Hiruni Abesinghe',    'hiruni@gmail.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'active');

INSERT IGNORE INTO patients
    (user_id, nic, dob, gender, blood_type, phone, address, emergency_contact)
VALUES
    ((SELECT user_id FROM users WHERE email='chaminda@gmail.com'),  '198534500125', '1985-06-15', 'male',   'B+',  '0712345601', 'No.12, Galle Rd, Colombo',     'Anoma Rajapaksa - 0712345700'),
    ((SELECT user_id FROM users WHERE email='dilrukshi@gmail.com'), '199210200845', '1992-04-10', 'female', 'A+',  '0712345602', 'No.45, Kandy Rd, Kurunegala',  'Pradeep Perera - 0712345701'),
    ((SELECT user_id FROM users WHERE email='suresh@gmail.com'),    '197804301256', '1978-11-20', 'male',   'O+',  '0712345603', 'No.7, Temple Rd, Gampaha',     'Kanthi Jayasinghe - 0712345702'),
    ((SELECT user_id FROM users WHERE email='malini@gmail.com'),    '199005150987', '1990-05-15', 'female', 'AB+', '0712345604', 'No.33, Lake View, Kandy',      'Saman Bandara - 0712345703'),
    ((SELECT user_id FROM users WHERE email='kasun@gmail.com'),     '199312250134', '1993-12-25', 'male',   'A-',  '0712345605', 'No.22, Main St, Matara',       'Rupa Wickramasinghe - 0712345704'),
    ((SELECT user_id FROM users WHERE email='nimali@gmail.com'),    '198801180567', '1988-01-18', 'female', 'O-',  '0712345606', 'No.9, Beach Rd, Negombo',      'Sisira Gunawardena - 0712345705'),
    ((SELECT user_id FROM users WHERE email='tharaka@gmail.com'),   '199507080345', '1995-07-08', 'male',   'B-',  '0712345607', 'No.55, Hill St, Ratnapura',    'Kumari Silva - 0712345706'),
    ((SELECT user_id FROM users WHERE email='sanduni@gmail.com'),   '199109230678', '1991-09-23', 'female', 'B+',  '0712345608', 'No.18, Station Rd, Badulla',   'Nalika Rathnayake - 0712345707'),
    ((SELECT user_id FROM users WHERE email='roshan@gmail.com'),    '198703060456', '1987-03-06', 'male',   'A+',  '0712345609', 'No.4, King St, Jaffna',        'Mala Mendis - 0712345708'),
    ((SELECT user_id FROM users WHERE email='hiruni@gmail.com'),    '199603140789', '1996-03-14', 'female', 'O+',  '0712345610', 'No.27, Church Rd, Anuradhapura','Nimal Abesinghe - 0712345709');

-- ============================================================
-- STEP 4 — Theatre Operations
--          Status mix: completed / in_progress / confirmed / scheduled / cancelled
-- ============================================================

INSERT IGNORE INTO theatre_operations
    (patient_id, surgeon_id, anaesthetist_id, assistant_doctor_id,
     operation_type, theatre_number, scheduled_date, scheduled_time,
     status, theatre_charge, pre_op_notes, post_op_notes, recovery_instructions,
     post_op_room_type, created_by)
VALUES

-- ── COMPLETED Operations ─────────────────────────────────────
(
    (SELECT patient_id FROM patients WHERE nic='198534500125'),
    (SELECT user_id FROM users WHERE email='nimal@medicare.lk'),     -- surgeon
    (SELECT user_id FROM users WHERE email='shani@medicare.lk'),     -- anaesthetist
    (SELECT user_id FROM users WHERE email='kamal@medicare.lk'),     -- assistant
    'Appendectomy', 1, '2026-05-01', '08:00:00',
    'completed', 45000.00,
    'Patient cleared for surgery. Fasting since midnight.',
    'Surgery successful. No complications observed.',
    'Bed rest for 48hrs. Liquid diet for first day. Follow-up in 7 days.',
    'semi_private',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),
(
    (SELECT patient_id FROM patients WHERE nic='199210200845'),
    (SELECT user_id FROM users WHERE email='kamal@medicare.lk'),
    (SELECT user_id FROM users WHERE email='priya@medicare.lk'),
    NULL,
    'Left Knee Total Replacement', 2, '2026-05-03', '09:30:00',
    'completed', 85000.00,
    'Pre-op X-rays reviewed. Blood group confirmed A+.',
    'Implant fitted correctly. Post-op physio advised.',
    'Physiotherapy starting day 3. No weight bearing for 2 weeks.',
    'private',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),
(
    (SELECT patient_id FROM patients WHERE nic='199005150987'),
    (SELECT user_id FROM users WHERE email='kumari@medicare.lk'),
    (SELECT user_id FROM users WHERE email='shani@medicare.lk'),
    NULL,
    'Caesarean Section', 3, '2026-05-05', '10:00:00',
    'completed', 55000.00,
    'Elective C-section at 38 weeks. Mother and fetus stable.',
    'Healthy baby girl delivered 3.2 kg. Mother recovering well.',
    'Breastfeeding support provided. Wound care every 2 days.',
    'private',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),
(
    (SELECT patient_id FROM patients WHERE nic='197804301256'),
    (SELECT user_id FROM users WHERE email='anura@medicare.lk'),
    (SELECT user_id FROM users WHERE email='priya@medicare.lk'),
    (SELECT user_id FROM users WHERE email='ruwan@medicare.lk'),
    'Coronary Artery Bypass Graft (Triple)', 1, '2026-05-07', '07:00:00',
    'completed', 120000.00,
    'High-risk patient. Pre-op cardiac clearance obtained.',
    'Triple bypass completed. Patient moved to ICU post-op.',
    'ICU monitoring for 48hrs. Cardiac rehab to begin week 2.',
    'icu',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),
(
    (SELECT patient_id FROM patients WHERE nic='199312250134'),
    (SELECT user_id FROM users WHERE email='nimal@medicare.lk'),
    (SELECT user_id FROM users WHERE email='shani@medicare.lk'),
    NULL,
    'Laparoscopic Cholecystectomy (Gallbladder Removal)', 2, '2026-05-09', '11:00:00',
    'completed', 40000.00,
    'Ultrasound confirmed gallstones. Laparoscopic approach planned.',
    'Gallbladder removed successfully. 4 laparoscopic ports used.',
    'Soft diet for 1 week. Avoid fatty foods for 1 month.',
    'semi_private',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),

-- ── IN PROGRESS Operation ────────────────────────────────────
(
    (SELECT patient_id FROM patients WHERE nic='198801180567'),
    (SELECT user_id FROM users WHERE email='ruwan@medicare.lk'),
    (SELECT user_id FROM users WHERE email='priya@medicare.lk'),
    (SELECT user_id FROM users WHERE email='kamal@medicare.lk'),
    'Brain Tumour Excision', 1, '2026-05-14', '08:00:00',
    'in_progress', 150000.00,
    'MRI reviewed. Tumour location mapped. High-risk procedure.',
    NULL,
    NULL,
    'icu',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),

-- ── CONFIRMED Operations ─────────────────────────────────────
(
    (SELECT patient_id FROM patients WHERE nic='199507080345'),
    (SELECT user_id FROM users WHERE email='kamal@medicare.lk'),
    (SELECT user_id FROM users WHERE email='shani@medicare.lk'),
    NULL,
    'Hip Replacement', 2, '2026-05-16', '09:00:00',
    'confirmed', 90000.00,
    'Right hip degeneration confirmed. Cemented total hip replacement planned.',
    NULL,
    NULL,
    'private',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),
(
    (SELECT patient_id FROM patients WHERE nic='199109230678'),
    (SELECT user_id FROM users WHERE email='nimal@medicare.lk'),
    (SELECT user_id FROM users WHERE email='priya@medicare.lk'),
    NULL,
    'Hernia Repair (Inguinal Mesh)', 3, '2026-05-17', '10:30:00',
    'confirmed', 32000.00,
    'Reducible inguinal hernia. Mesh repair under GA planned.',
    NULL,
    NULL,
    'semi_private',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),

-- ── SCHEDULED Operations ─────────────────────────────────────
(
    (SELECT patient_id FROM patients WHERE nic='198703060456'),
    (SELECT user_id FROM users WHERE email='anura@medicare.lk'),
    (SELECT user_id FROM users WHERE email='shani@medicare.lk'),
    (SELECT user_id FROM users WHERE email='nimal@medicare.lk'),
    'Valve Replacement (Mitral)', 1, '2026-05-20', '07:30:00',
    'scheduled', 130000.00,
    'Severe mitral regurgitation. Mechanical valve replacement planned.',
    NULL,
    NULL,
    'icu',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),
(
    (SELECT patient_id FROM patients WHERE nic='199603140789'),
    (SELECT user_id FROM users WHERE email='kumari@medicare.lk'),
    (SELECT user_id FROM users WHERE email='priya@medicare.lk'),
    NULL,
    'Ovarian Cyst Removal (Laparoscopic)', 4, '2026-05-22', '13:00:00',
    'scheduled', 38000.00,
    '10cm endometriotic cyst confirmed on ultrasound.',
    NULL,
    NULL,
    'semi_private',
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
),

-- ── CANCELLED Operation ──────────────────────────────────────
(
    (SELECT patient_id FROM patients WHERE nic='198534500125'),
    (SELECT user_id FROM users WHERE email='nimal@medicare.lk'),
    (SELECT user_id FROM users WHERE email='shani@medicare.lk'),
    NULL,
    'Pilonidal Cyst Excision', 4, '2026-05-08', '14:00:00',
    'cancelled', 15000.00,
    'Minor cyst. Cancelled due to patient high BP on day of surgery.',
    NULL,
    NULL,
    NULL,
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
);

-- ============================================================
-- STEP 5 — Theatre Billing
--          Auto-calculated from theatre_operations charges
--          (operation + 15% anaesthesia + 8% consumables)
-- ============================================================

INSERT IGNORE INTO theatre_billing
    (operation_id, patient_id, operation_charge,
     anaesthesia_charge, consumables_charge,
     billing_status, notes, created_by)
SELECT
    o.operation_id,
    o.patient_id,
    o.theatre_charge                          AS operation_charge,
    ROUND(o.theatre_charge * 0.15, 2)         AS anaesthesia_charge,
    ROUND(o.theatre_charge * 0.08, 2)         AS consumables_charge,
    CASE o.status
        WHEN 'completed'   THEN 'invoiced'
        WHEN 'in_progress' THEN 'pending'
        WHEN 'confirmed'   THEN 'pending'
        WHEN 'scheduled'   THEN 'pending'
        WHEN 'cancelled'   THEN 'waived'
        ELSE 'pending'
    END                                       AS billing_status,
    CONCAT('Auto-generated billing for: ', o.operation_type) AS notes,
    (SELECT user_id FROM users WHERE role='admin' LIMIT 1)
FROM theatre_operations o
WHERE o.theatre_charge > 0;

-- ============================================================
-- ✅ VERIFY — Quick summary check after insert
-- ============================================================

SELECT
    o.operation_id,
    u.full_name                    AS patient_name,
    o.operation_type,
    o.theatre_number               AS theatre,
    o.scheduled_date,
    o.scheduled_time,
    o.status,
    FORMAT(o.theatre_charge, 2)    AS op_charge_LKR,
    FORMAT(tb.total_charge, 2)     AS total_bill_LKR,
    tb.billing_status
FROM theatre_operations o
JOIN patients p  ON p.patient_id = o.patient_id
JOIN users u     ON u.user_id    = p.user_id
LEFT JOIN theatre_billing tb ON tb.operation_id = o.operation_id
ORDER BY o.scheduled_date, o.scheduled_time;
