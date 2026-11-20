-- Non-destructive: adds the editable settings table used for the hospital name.
-- Safe to run more than once; existing settings are left untouched.

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL
);

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('hospital_name', 'General Hospital')
ON CONFLICT (setting_key) DO NOTHING;
