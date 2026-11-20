-- Hospital Injury Surveillance - PostgreSQL schema and demo data
-- Loaded into the HISS database by start-injury-surveillance.bat.
-- Running it again will replace the tables with the original demo data.

DROP TABLE IF EXISTS injury_cases;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP SEQUENCE IF EXISTS case_ref_seq;

CREATE TABLE roles (
    role_id     SERIAL PRIMARY KEY,
    role_name   VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(120) NOT NULL,
    permissions TEXT NOT NULL
);

CREATE TABLE users (
    user_id      SERIAL PRIMARY KEY,
    username     VARCHAR(50) NOT NULL UNIQUE,
    full_name    VARCHAR(100) NOT NULL,
    password_hash TEXT NOT NULL,
    role_id      INT NOT NULL REFERENCES roles(role_id),
    active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE SEQUENCE case_ref_seq START 1;
CREATE SEQUENCE patient_code_seq START 1001;

CREATE TABLE injury_cases (
    case_id        SERIAL PRIMARY KEY,
    case_reference VARCHAR(30) NOT NULL UNIQUE,
    incident_date  DATE NOT NULL,
    incident_time  TIME,
    patient_code   VARCHAR(40) NOT NULL,
    patient_name   VARCHAR(100),
    age            SMALLINT CHECK (age BETWEEN 0 AND 120),
    sex            VARCHAR(20) NOT NULL DEFAULT 'Not recorded',
    mechanism      VARCHAR(50) NOT NULL,
    injury_type    VARCHAR(50) NOT NULL,
    body_part      VARCHAR(50) NOT NULL,
    classification VARCHAR(20) NOT NULL,
    treatment      VARCHAR(60),
    outcome        VARCHAR(60) NOT NULL,
    notes          TEXT,
    recorded_by    INT REFERENCES users(user_id),
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP
);

CREATE INDEX idx_cases_date ON injury_cases (incident_date DESC);
CREATE INDEX idx_cases_mechanism ON injury_cases (mechanism);
CREATE INDEX idx_cases_patient ON injury_cases (patient_code);
CREATE INDEX idx_cases_patient_name ON injury_cases (patient_name);

-- Roles. Permissions are comma-separated and checked by the PHP code.
INSERT INTO roles (role_name, description, permissions) VALUES
    ('admin',   'Full access, including user accounts', 'view,create,export,manage_users'),
    ('officer', 'Records injury cases and edits them',  'view,create'),
    ('records', 'Searches cases and prepares reports',  'view,export'),
    ('viewer',  'Read-only access',                     'view');

-- Demo accounts. Passwords: Admin@123, Nurse@123, Records@123, View@123
INSERT INTO users (username, full_name, password_hash, role_id, active) VALUES
    ('admin',    'Admin User',     '$2y$10$BGkFBTIRSUUSfP5qxzHVEuRbn9aQ2yWi5ITYgQWjQaTv3igZ4sK4C', 1, TRUE),
    ('nurse1',   'Nurse On Duty',  '$2y$10$c3z2m2fYy9eorWxRnadIVuORgOupdiPLAVaYALGB5IFigcLE0oI3O', 2, TRUE),
    ('records1', 'Records Officer','$2y$10$9UTR0RSP1MY2P8wh9qt3EOMAbwy05J9NXQILijaqrxtxTXLg3CUf.', 3, TRUE),
    ('viewer1',  'Reviewer',       '$2y$10$7Gex1t/5HXszoAm/uyzLJej.Y5BClPDMpF.a47oyD49CiLalLpzqy', 4, TRUE);

-- Synthetic case data. No real patient information.
INSERT INTO injury_cases (
    case_reference, incident_date, incident_time, patient_code, age, sex,
    mechanism, injury_type, body_part, classification, treatment, outcome, notes, recorded_by
) VALUES
    ('INJ-2026-0001', '2026-10-02', '08:30', 'PT-1001', 34, 'Male',   'Road traffic crash', 'Fracture',          'Lower limb',      'Severe',   'Immobilisation', 'Admitted',                     'Motorcycle versus car.', 2),
    ('INJ-2026-0002', '2026-10-04', '14:10', 'PT-1002', 22, 'Female', 'Fall',               'Sprain',            'Upper limb',      'Minor',    'First aid',      'Treated and discharged',       '', 2),
    ('INJ-2026-0003', '2026-10-07', '19:45', 'PT-1003', 41, 'Male',   'Assault',            'Laceration',        'Head / neck',     'Moderate', 'Wound care',     'Treated and discharged',       '', 2),
    ('INJ-2026-0004', '2026-10-09', '11:20', 'PT-1004', 8,  'Female', 'Burn',               'Burn',              'Upper limb',      'Moderate', 'Wound care',     'Referred to another facility', 'Hot water scald.', 2),
    ('INJ-2026-0005', '2026-10-12', '06:15', 'PT-1005', 55, 'Male',   'Machinery',          'Contusion',         'Upper limb',      'Moderate', 'First aid',      'Treated and discharged',       '', 2),
    ('INJ-2026-0006', '2026-10-15', '16:05', 'PT-1006', 29, 'Female', 'Road traffic crash', 'Contusion',         'Chest',           'Severe',   'Observation',    'Admitted',                     '', 2),
    ('INJ-2026-0007', '2026-10-18', '09:40', 'PT-1007', 17, 'Male',   'Sports / leisure',   'Sprain',            'Lower limb',      'Minor',    'First aid',      'Treated and discharged',       '', 2),
    ('INJ-2026-0008', '2026-10-21', '21:30', 'PT-1008', 63, 'Male',   'Fall',               'Fracture',          'Back / spine',    'Severe',   'Surgery',        'Still in care',                '', 2),
    ('INJ-2026-0009', '2026-10-24', '13:00', 'PT-1009', 36, 'Female', 'Assault',            'Contusion',         'Head / neck',     'Minor',    'Observation',    'Treated and discharged',       '', 2),
    ('INJ-2026-0010', '2026-10-28', '10:25', 'PT-1010', 47, 'Male',   'Drowning',           'Other',             'Chest',           'Severe',   'Referred',       'Referred to another facility', '', 2),
    ('INJ-2026-0011', '2026-11-01', '07:50', 'PT-1011', 19, 'Female', 'Road traffic crash', 'Laceration',        'Lower limb',      'Moderate', 'Wound care',     'Treated and discharged',       '', 2),
    ('INJ-2026-0012', '2026-11-04', '15:35', 'PT-1012', 31, 'Male',   'Fall',               'Fracture',          'Upper limb',      'Moderate', 'Immobilisation', 'Treated and discharged',       '', 2),
    ('INJ-2026-0013', '2026-11-07', '12:15', 'PT-1013', 26, 'Female', 'Machinery',          'Penetrating wound', 'Upper limb',      'Severe',   'Surgery',        'Admitted',                     '', 3),
    ('INJ-2026-0014', '2026-11-10', '18:20', 'PT-1014', 5,  'Male',   'Burn',               'Burn',              'Multiple sites',  'Severe',   'Referred',       'Referred to another facility', '', 3),
    ('INJ-2026-0015', '2026-11-14', '08:05', 'PT-1015', 44, 'Female', 'Road traffic crash', 'Contusion',         'Multiple sites',  'Moderate', 'Observation',    'Treated and discharged',       '', 2),
    ('INJ-2026-0016', '2026-11-18', '20:10', 'PT-1016', 38, 'Male',   'Assault',            'Laceration',        'Head / neck',     'Minor',    'Wound care',     'Left against advice',          '', 2);

-- Fictional names for the demo cases. No real patient information.
UPDATE injury_cases SET patient_name = CASE patient_code
    WHEN 'PT-1001' THEN 'Amina Otieno'
    WHEN 'PT-1002' THEN 'Brian Kiptoo'
    WHEN 'PT-1003' THEN 'Grace Wanjiku'
    WHEN 'PT-1004' THEN 'Faith Muthoni'
    WHEN 'PT-1005' THEN 'Peter Kamau'
    WHEN 'PT-1006' THEN 'Sarah Chebet'
    WHEN 'PT-1007' THEN 'Kevin Omondi'
    WHEN 'PT-1008' THEN 'Mary Njoroge'
    WHEN 'PT-1009' THEN 'Joseph Mwangi'
    WHEN 'PT-1010' THEN 'Lydia Achieng'
    WHEN 'PT-1011' THEN 'Dennis Kiprop'
    WHEN 'PT-1012' THEN 'Anne Wafula'
    WHEN 'PT-1013' THEN 'Samuel Maina'
    WHEN 'PT-1014' THEN 'Hassan Ali'
    WHEN 'PT-1015' THEN 'Rebecca Wambui'
    WHEN 'PT-1016' THEN 'Collins Ouma'
END;

SELECT setval('case_ref_seq', 16, TRUE);
SELECT setval('patient_code_seq', 1016, TRUE);
