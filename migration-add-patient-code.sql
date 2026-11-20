-- Adds the patient code sequence to an existing HISS database without
-- changing any cases. Safe to run more than once.
CREATE SEQUENCE IF NOT EXISTS patient_code_seq START 1001;

-- Continue after the highest code already in use.
SELECT setval(
    'patient_code_seq',
    COALESCE((
        SELECT MAX(regexp_replace(patient_code, '[^0-9]', '', 'g')::int)
        FROM injury_cases
        WHERE patient_code ~ '[0-9]'
    ), 1000),
    TRUE
);
