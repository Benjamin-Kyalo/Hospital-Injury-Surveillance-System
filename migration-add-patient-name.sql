-- Adds the patient name field to an existing HISS database without
-- removing any cases or users. Safe to run more than once.
ALTER TABLE injury_cases ADD COLUMN IF NOT EXISTS patient_name VARCHAR(100);
CREATE INDEX IF NOT EXISTS idx_cases_patient_name ON injury_cases (patient_name);

-- Fictional names for the original 16 demo cases. No real patient information.
-- Existing names are left alone.
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
END
WHERE patient_name IS NULL AND patient_code IN (
    'PT-1001','PT-1002','PT-1003','PT-1004','PT-1005','PT-1006','PT-1007','PT-1008',
    'PT-1009','PT-1010','PT-1011','PT-1012','PT-1013','PT-1014','PT-1015','PT-1016'
);
