-- Gives the built-in admin role the "delete" permission without changing
-- any cases, users or other roles. Safe to run more than once.
UPDATE roles
SET permissions = permissions || ',delete'
WHERE role_name = 'admin'
  AND permissions !~ '(^|,)delete(,|$)';
