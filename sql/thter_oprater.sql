INSERT INTO theatre_operations (
    patient_id, 
    surgeon_id, 
    anaesthetist_id, 
    assistant_doctor_id, 
    operation_type, 
    theatre_number, 
    scheduled_date, 
    scheduled_time, 
    status, 
    pre_op_notes, 
    post_op_notes, 
    recovery_instructions, 
    post_op_room_type, 
    theatre_charge, 
    created_by
) VALUES

-- Operation 1
(1, 
 (SELECT user_id FROM users WHERE email = 'nimal@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'shani@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'kamal@medicare.lk' LIMIT 1),
 'Appendectomy (Emergency)', 
 1, '2026-05-20', '09:00:00', 'scheduled',
 'Patient has acute appendicitis. NPO since midnight.', NULL, 
 'Monitor for infection, pain management with paracetamol.', 
 'General Ward',
 65000.00, 
 (SELECT user_id FROM users WHERE email = 'nimal@medicare.lk' LIMIT 1)),

-- Operation 2
(2, 
 (SELECT user_id FROM users WHERE email = 'kamal@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'shani@medicare.lk' LIMIT 1),
 NULL,
 'Total Knee Replacement', 
 2, '2026-05-21', '14:30:00', 'confirmed',
 'Osteoarthritis patient. Pre-op physio done.', 
 'Surgery successful. No complications.', 
 'Physiotherapy starting day 1 post-op.', 
 'Semi-Private',
 285000.00, 
 (SELECT user_id FROM users WHERE role = 'admin' LIMIT 1)),

-- Operation 3
(3, 
 (SELECT user_id FROM users WHERE email = 'kumari@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'shani@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'anura@medicare.lk' LIMIT 1),
 'Caesarean Section', 
 1, '2026-05-22', '08:00:00', 'in_progress',
 '37 weeks gestation, previous C-section.', NULL, 
 'Breastfeeding support, wound care.', 
 'Private',
 125000.00, 
 (SELECT user_id FROM users WHERE email = 'kumari@medicare.lk' LIMIT 1)),

-- Operation 4
(4, 
 (SELECT user_id FROM users WHERE email = 'anura@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'shani@medicare.lk' LIMIT 1),
 NULL,
 'Coronary Artery Bypass Graft (CABG)', 
 3, '2026-05-25', '10:00:00', 'scheduled',
 'Triple vessel disease confirmed by angiogram.', NULL, 
 'Cardiac rehab program mandatory.', 
 'ICU',
 675000.00, 
 (SELECT user_id FROM users LIMIT 1)),

-- Operation 5
(5, 
 (SELECT user_id FROM users WHERE email = 'nimal@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'shani@medicare.lk' LIMIT 1),
 (SELECT user_id FROM users WHERE email = 'kamal@medicare.lk' LIMIT 1),
 'Cholecystectomy (Laparoscopic)', 
 2, '2026-05-15', '11:00:00', 'completed',
 'Gallstones with cholecystitis.', 
 'Surgery uneventful. 4 ports used.', 
 'Low fat diet for 2 weeks.', 
 'General Ward',
 95000.00, 
 (SELECT user_id FROM users WHERE email = 'nimal@medicare.lk' LIMIT 1));



/* update that code */

 UPDATE theatre_operations 
SET theatre_charge = CASE 
    WHEN operation_type LIKE '%Appendectomy%' THEN 65000.00
    WHEN operation_type LIKE '%Knee Replacement%' THEN 285000.00
    WHEN operation_type LIKE '%Caesarean%' THEN 125000.00
    WHEN operation_type LIKE '%Bypass%' OR operation_type LIKE '%CABG%' THEN 675000.00
    WHEN operation_type LIKE '%Cholecystectomy%' THEN 95000.00
    ELSE 85000.00 
END
WHERE theatre_charge = 0 OR theatre_charge IS NULL;