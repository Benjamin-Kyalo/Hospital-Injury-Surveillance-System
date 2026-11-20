-- Keeps a case when the staff member who recorded it is deleted.
-- PostgreSQL's default is to refuse the delete; SET NULL keeps the case and
-- the details page then shows "unknown user" for the recorder.
-- Safe to run more than once.
ALTER TABLE injury_cases DROP CONSTRAINT IF EXISTS injury_cases_recorded_by_fkey;

ALTER TABLE injury_cases ADD CONSTRAINT injury_cases_recorded_by_fkey
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL;
