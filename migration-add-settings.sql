-- Non-destructive: creates the editable hospital profile and adds any missing
-- settings. Safe to run more than once; values already saved are never changed.

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL
);

INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('hospital_name', 'General Hospital'),
    ('hospital_phone', ''),
    ('hospital_email', ''),
    ('hospital_address', '')
ON CONFLICT (setting_key) DO NOTHING;
