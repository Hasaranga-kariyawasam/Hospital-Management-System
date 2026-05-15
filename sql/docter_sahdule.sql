CREATE TABLE doctor_schedules (
  schedule_id   INT          NOT NULL AUTO_INCREMENT,
  doctor_id     INT          NOT NULL,
  day_of_week   TINYINT(1)   NOT NULL
                  CHECK (day_of_week BETWEEN 0 AND 6),
  start_time    TIME         NOT NULL,
  end_time      TIME         NOT NULL,
  slot_duration TINYINT      NOT NULL DEFAULT 15
                  CHECK (slot_duration IN (10, 15, 20, 30)),
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (schedule_id),
  UNIQUE KEY uq_doctor_day (doctor_id, day_of_week),

  CONSTRAINT fk_ds_doctor
    FOREIGN KEY (doctor_id)
    REFERENCES doctors (doctor_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;