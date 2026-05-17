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
    is_conscious        ENUM('yes','semi','no')         DEFAULT NULL,
    assistance_on_site  ENUM('yes','no','medical')      DEFAULT NULL,
    status              ENUM('pending','dispatched','resolved','cancelled')
                                                        NOT NULL DEFAULT 'pending',
    submitted_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (emergency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;