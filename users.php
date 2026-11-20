<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_permission('manage_users');

$page_title = 'Users and roles';

/*
 * Administration lives on this one page:
 *   users.php?tab=accounts  staff accounts (default)
 *   users.php?tab=roles     roles and their permissions
 * Every write is POST-only, CSRF-protected and permission-checked again here.
 */

$tab = (string) ($_GET['tab'] ?? '') === 'roles' ? 'roles' : 'accounts';
$return_to = 'users.php?tab=' . $tab;

$roles = $pdo->query('
    SELECT r.role_id, r.role_name, r.description, r.permissions,
           (SELECT COUNT(*) FROM users u WHERE u.role_id = r.role_id) AS user_count
    FROM roles r
    ORDER BY r.role_id
')->fetchAll();

$action = (string) ($_GET['action'] ?? '');
$edit_id = (int) ($_GET['id'] ?? 0);
$wants_form = $action === 'new' || ($action === 'edit' && $edit_id > 0);
$account_editing = $tab === 'accounts' && $action === 'edit' && $edit_id > 0;
$role_editing = $tab === 'roles' && $action === 'edit' && $edit_id > 0;
$show_account_form = $tab === 'accounts' && $wants_form;
$show_role_form = $tab === 'roles' && $wants_form;
$form_account = [];
$form_role = [];
$errors = [];

if ($account_editing) {
    $stmt = $pdo->prepare('SELECT user_id, username, full_name, role_id,
                           CASE WHEN active THEN 1 ELSE 0 END AS active
                           FROM users WHERE user_id = :id');
    $stmt->execute([':id' => $edit_id]);
    $form_account = $stmt->fetch();
    if (!$form_account) {
        flash('error', 'That user could not be found.');
        redirect('users.php?tab=accounts');
    }
}

if ($role_editing) {
    $stmt = $pdo->prepare('SELECT role_id, role_name, description, permissions FROM roles WHERE role_id = :id');
    $stmt->execute([':id' => $edit_id]);
    $form_role = $stmt->fetch();
    if (!$form_role) {
        flash('error', 'That role could not be found.');
        redirect('users.php?tab=roles');
    }
    $form_role['permissions'] = explode(',', $form_role['permissions']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $tab = (string) ($_POST['tab'] ?? 'accounts') === 'roles' ? 'roles' : 'accounts';
    $return_to = 'users.php?tab=' . $tab;
    $post_action = (string) ($_POST['action'] ?? '');

    /* ---------------- Delete an account ---------------- */
    if ($post_action === 'account_delete') {
        require_permission('delete');

        $delete_id = (int) ($_POST['user_id'] ?? 0);
        $me = current_user()['user_id'];

        if ($delete_id <= 0) {
            flash('error', 'No account was selected for deletion.');
            redirect($return_to);
        }
        if ($delete_id === $me) {
            flash('error', 'You cannot delete your own account.');
            redirect($return_to);
        }

        $stmt = $pdo->prepare('SELECT u.username, r.role_name
                               FROM users u JOIN roles r ON r.role_id = u.role_id
                               WHERE u.user_id = :id');
        $stmt->execute([':id' => $delete_id]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', 'That account could not be found. It may already have been deleted.');
            redirect($return_to);
        }

        if ($target['role_name'] === 'admin') {
            $admin_count = (int) $pdo->query("
                SELECT COUNT(*) FROM users u
                JOIN roles r ON r.role_id = u.role_id
                WHERE r.role_name = 'admin'
            ")->fetchColumn();
            if ($admin_count <= 1) {
                flash('error', 'This is the last administrator account. Add another admin before deleting it.');
                redirect($return_to);
            }
        }

        try {
            // injury_cases.recorded_by is ON DELETE SET NULL, so recorded cases are kept.
            $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id');
            $stmt->execute([':id' => $delete_id]);
            flash('success', 'Account ' . $target['username'] . ' has been deleted. Its cases are kept.');
        } catch (PDOException $e) {
            error_log('User delete failed: ' . $e->getMessage());
            flash('error', 'The account could not be deleted. Please try again.');
        }
        redirect($return_to);
    }

    /* ---------------- Delete a role ---------------- */
    if ($post_action === 'role_delete') {
        require_permission('delete');

        $delete_id = (int) ($_POST['role_id'] ?? 0);
        if ($delete_id <= 0) {
            flash('error', 'No role was selected for deletion.');
            redirect($return_to);
        }

        $stmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = :id');
        $stmt->execute([':id' => $delete_id]);
        $role_name = $stmt->fetchColumn();
        if (!$role_name) {
            flash('error', 'That role could not be found. It may already have been deleted.');
            redirect($return_to);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = :id');
        $stmt->execute([':id' => $delete_id]);
        $user_count = (int) $stmt->fetchColumn();
        if ($user_count > 0) {
            flash('error', 'Role ' . $role_name . ' is still assigned to ' . $user_count .
                           ' account(s). Move those accounts to another role first.');
            redirect($return_to);
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM roles WHERE role_id = :id');
            $stmt->execute([':id' => $delete_id]);
            flash('success', 'Role ' . $role_name . ' has been deleted.');
        } catch (PDOException $e) {
            error_log('Role delete failed: ' . $e->getMessage());
            flash('error', 'The role could not be deleted. Please try again.');
        }
        redirect($return_to);
    }

    /* ---------------- Create or update an account ---------------- */
    if ($post_action === 'account_create' || $post_action === 'account_update') {
        $tab = 'accounts';
        $return_to = 'users.php?tab=accounts';
        $is_edit = $post_action === 'account_update';
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $show_account_form = true;
        $show_role_form = false;
        $account_editing = $is_edit;

        $form_account = [
            'user_id'   => $user_id,
            'username'  => trim((string) ($_POST['username'] ?? '')),
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'role_id'   => (int) ($_POST['role_id'] ?? 0),
            'active'    => isset($_POST['active']) ? 1 : 0,
        ];

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');
        $role_ids = array_column($roles, 'role_id');

        if ($form_account['username'] === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $form_account['username'])) {
            $errors[] = 'Username may only contain letters, numbers, dots, dashes and underscores.';
        }
        if (mb_strlen($form_account['full_name']) < 2 || mb_strlen($form_account['full_name']) > 100) {
            $errors[] = 'Enter the full name (2 to 100 characters).';
        }
        if (!in_array($form_account['role_id'], $role_ids, true)) {
            $errors[] = 'Choose a valid role.';
        }
        if (!$is_edit || $password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirm) {
                $errors[] = 'The two passwords do not match.';
            }
        }
        if ($is_edit && $user_id === current_user()['user_id']) {
            if (!$form_account['active']) {
                $errors[] = 'You cannot deactivate your own account.';
            }
            // Do not allow self-lockout by moving your own account to a role without manage_users.
            foreach ($roles as $role) {
                if ((int) $role['role_id'] === $form_account['role_id'] &&
                    !in_array('manage_users', explode(',', $role['permissions']), true)) {
                    $errors[] = 'You cannot move your own account to a role without account/role management.';
                    break;
                }
            }
        }

        $stmt = $pdo->prepare(
            $is_edit
                ? 'SELECT COUNT(*) FROM users WHERE username = :username AND user_id <> :id'
                : 'SELECT COUNT(*) FROM users WHERE username = :username'
        );
        $check = [':username' => $form_account['username']];
        if ($is_edit) {
            $check[':id'] = $user_id;
        }
        $stmt->execute($check);
        if ($stmt->fetchColumn()) {
            $errors[] = 'That username is already taken.';
        }

        if (!$errors) {
            try {
                if ($is_edit) {
                    $sql = 'UPDATE users SET username = :username, full_name = :full_name,
                            role_id = :role_id, active = :active';
                    $params = [
                        ':username'  => $form_account['username'],
                        ':full_name' => $form_account['full_name'],
                        ':role_id'   => $form_account['role_id'],
                        ':active'    => $form_account['active'],
                        ':id'        => $user_id,
                    ];
                    if ($password !== '') {
                        $sql .= ', password_hash = :password_hash';
                        $params[':password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    $sql .= ' WHERE user_id = :id';
                    $pdo->prepare($sql)->execute($params);
                    $message = 'User account updated.';
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO users (username, full_name, password_hash, role_id, active)
                        VALUES (:username, :full_name, :password_hash, :role_id, :active)
                    ');
                    $stmt->execute([
                        ':username'      => $form_account['username'],
                        ':full_name'     => $form_account['full_name'],
                        ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                        ':role_id'       => $form_account['role_id'],
                        ':active'        => $form_account['active'],
                    ]);
                    $message = 'User account created.';
                }
                flash('success', $message);
                redirect($return_to);
            } catch (PDOException $e) {
                error_log('User save failed: ' . $e->getMessage());
                $errors[] = 'The account could not be saved. Please try again.';
            }
        }
    }

    /* ---------------- Create or update a role ---------------- */
    if ($post_action === 'role_create' || $post_action === 'role_update') {
        $tab = 'roles';
        $return_to = 'users.php?tab=roles';
        $is_edit = $post_action === 'role_update';
        $role_id = (int) ($_POST['role_id'] ?? 0);
        $show_role_form = true;
        $show_account_form = false;
        $role_editing = $is_edit;

        // Only accept permissions that the application actually understands.
        $chosen = $_POST['permissions'] ?? [];
        if (!is_array($chosen)) {
            $chosen = [];
        }
        $chosen = array_values(array_unique(array_intersect(
            array_keys($permission_options),
            array_map('strval', $chosen)
        )));

        $form_role = [
            'role_id'     => $role_id,
            'role_name'   => trim((string) ($_POST['role_name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'permissions' => $chosen,
        ];

        if (!preg_match('/^[a-z][a-z0-9_]{1,29}$/', $form_role['role_name'])) {
            $errors[] = 'Role name must be 2-30 characters: lowercase letters, numbers or underscores, starting with a letter.';
        }
        if (mb_strlen($form_role['description']) < 3 || mb_strlen($form_role['description']) > 120) {
            $errors[] = 'Enter a description (3 to 120 characters).';
        }
        if (!$chosen) {
            $errors[] = 'Choose at least one permission.';
        }

        // Do not let the current administrator remove their own access to this page.
        $stmt = $pdo->prepare('SELECT role_id FROM users WHERE user_id = :id');
        $stmt->execute([':id' => current_user()['user_id']]);
        $my_role_id = (int) $stmt->fetchColumn();
        if ($is_edit && $role_id === $my_role_id && !in_array('manage_users', $chosen, true)) {
            $errors[] = 'You cannot remove account/role management from your own role.';
        }

        $stmt = $pdo->prepare(
            $is_edit
                ? 'SELECT COUNT(*) FROM roles WHERE role_name = :name AND role_id <> :id'
                : 'SELECT COUNT(*) FROM roles WHERE role_name = :name'
        );
        $check = [':name' => $form_role['role_name']];
        if ($is_edit) {
            $check[':id'] = $role_id;
        }
        $stmt->execute($check);
        if ($stmt->fetchColumn()) {
            $errors[] = 'That role name is already in use.';
        }

        if (!$errors) {
            try {
                $permissions = implode(',', $chosen);
                if ($is_edit) {
                    $stmt = $pdo->prepare('UPDATE roles SET role_name = :name, description = :description,
                                           permissions = :permissions WHERE role_id = :id');
                    $stmt->execute([
                        ':name'        => $form_role['role_name'],
                        ':description' => $form_role['description'],
                        ':permissions' => $permissions,
                        ':id'          => $role_id,
                    ]);
                    $message = 'Role updated. Affected users must sign out and back in to get the new permissions.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO roles (role_name, description, permissions)
                                           VALUES (:name, :description, :permissions)');
                    $stmt->execute([
                        ':name'        => $form_role['role_name'],
                        ':description' => $form_role['description'],
                        ':permissions' => $permissions,
                    ]);
                    $message = 'Role created.';
                }
                flash('success', $message);
                redirect($return_to);
            } catch (PDOException $e) {
                error_log('Role save failed: ' . $e->getMessage());
                $errors[] = 'The role could not be saved. Please try again.';
            }
        }
    }
}

$users = $pdo->query('
    SELECT u.user_id, u.username, u.full_name,
           CASE WHEN u.active THEN 1 ELSE 0 END AS active,
           u.created_at, r.role_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    ORDER BY u.user_id
')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="pagehead">
  <h1>Users and roles</h1>
  <p class="muted">One place to manage staff accounts, roles and what each role is allowed to do.</p>
</div>

<nav class="tabs" aria-label="Administration sections">
  <a class="tab<?= $tab === 'accounts' ? ' active' : '' ?>" href="users.php?tab=accounts">Accounts</a>
  <a class="tab<?= $tab === 'roles' ? ' active' : '' ?>" href="users.php?tab=roles">Roles &amp; permissions</a>
</nav>

<?php if ($tab === 'accounts'): ?>

  <?php if ($show_account_form): ?>
    <section class="panel">
      <h2><?= $account_editing ? 'Edit account' : 'New account' ?></h2>

      <?php if ($errors): ?>
        <div class="notice error">
          <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" action="users.php" data-password-form>
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="accounts">
        <input type="hidden" name="action" value="<?= $account_editing ? 'account_update' : 'account_create' ?>">
        <input type="hidden" name="user_id" value="<?= (int) ($form_account['user_id'] ?? 0) ?>">

        <div class="formgrid">
          <div class="field">
            <label for="username">Username *</label>
            <input type="text" id="username" name="username" required maxlength="50"
                   value="<?= e($form_account['username'] ?? '') ?>">
          </div>
          <div class="field">
            <label for="full_name">Full name *</label>
            <input type="text" id="full_name" name="full_name" required maxlength="100"
                   value="<?= e($form_account['full_name'] ?? '') ?>">
          </div>
          <div class="field">
            <label for="role_id">Role *</label>
            <select id="role_id" name="role_id" required>
              <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['role_id'] ?>"<?= (int) ($form_account['role_id'] ?? 0) === (int) $role['role_id'] ? ' selected' : '' ?>>
                  <?= e($role['role_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="checkline">
              <input type="checkbox" name="active" value="1"<?= (int) ($form_account['active'] ?? 1) === 1 ? ' checked' : '' ?>>
              Account active
            </label>
          </div>
          <div class="field">
            <label for="password"><?= $account_editing ? 'New password' : 'Password *' ?></label>
            <input type="password" id="password" name="password" autocomplete="new-password"
                   <?= $account_editing ? '' : 'required' ?>>
            <small><?= $account_editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.' ?></small>
          </div>
          <div class="field">
            <label for="confirm_password">Confirm password<?= $account_editing ? '' : ' *' ?></label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                   <?= $account_editing ? '' : 'required' ?>>
          </div>
        </div>

        <p>
          <button type="submit" class="btn primary"><?= $account_editing ? 'Save changes' : 'Create account' ?></button>
          <a class="btn" href="users.php?tab=accounts">Cancel</a>
        </p>
      </form>
    </section>
  <?php else: ?>
    <p><a class="btn primary" href="users.php?tab=accounts&amp;action=new">Add a user</a></p>
  <?php endif; ?>

  <section class="panel">
    <h2>Accounts</h2>
    <div class="tablewrap">
      <table>
        <thead>
          <tr><th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Created</th><th class="right">Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $account): ?>
          <tr>
            <td><?= e($account['username']) ?></td>
            <td><?= e($account['full_name']) ?></td>
            <td><?= e($account['role_name']) ?></td>
            <td>
              <span class="status <?= $account['active'] ? 'on' : 'off' ?>">
                <?= $account['active'] ? 'Active' : 'Disabled' ?>
              </span>
            </td>
            <td><?= e(date('d M Y', strtotime($account['created_at']))) ?></td>
            <td class="right">
              <a href="users.php?tab=accounts&amp;action=edit&amp;id=<?= (int) $account['user_id'] ?>">Edit</a>
              <?php if (can('delete')): ?>
                <?php if ((int) $account['user_id'] === current_user()['user_id']): ?>
                  &middot; <span class="muted">You</span>
                <?php else: ?>
                  &middot;
                  <form method="post" action="users.php" class="inlineform"
                        data-confirm="Delete account <?= e($account['username']) ?>? Cases they recorded are kept.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tab" value="accounts">
                    <input type="hidden" name="action" value="account_delete">
                    <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
                    <button type="submit" class="linkdelete">Delete</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="fineprint">Administrators can create, edit, reset passwords for, disable and delete other accounts. You cannot delete yourself or the last administrator.</p>
  </section>

<?php else: ?>

  <?php if ($show_role_form): ?>
    <section class="panel">
      <h2><?= $role_editing ? 'Edit role' : 'New role' ?></h2>

      <?php if ($errors): ?>
        <div class="notice error">
          <strong>Please check the form:</strong>
          <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" action="users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="roles">
        <input type="hidden" name="action" value="<?= $role_editing ? 'role_update' : 'role_create' ?>">
        <input type="hidden" name="role_id" value="<?= (int) ($form_role['role_id'] ?? 0) ?>">

        <div class="formgrid">
          <div class="field">
            <label for="role_name">Role name *</label>
            <input type="text" id="role_name" name="role_name" maxlength="30" required
                   placeholder="e.g. supervisor"
                   value="<?= e($form_role['role_name'] ?? '') ?>">
            <small>Lowercase letters, numbers and underscores. Example: data_clerk</small>
          </div>
          <div class="field">
            <label for="description">Description *</label>
            <input type="text" id="description" name="description" maxlength="120" required
                   value="<?= e($form_role['description'] ?? '') ?>">
            <small>Shown in the account form so administrators know what the role is for.</small>
          </div>
        </div>

        <div class="field">
          <label>Permissions *</label>
          <div class="permgrid">
            <?php foreach ($permission_options as $key => $label): ?>
              <label class="checkline">
                <input type="checkbox" name="permissions[]" value="<?= e($key) ?>"
                       <?= in_array($key, $form_role['permissions'] ?? [], true) ? 'checked' : '' ?>>
                <?= e($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <small>Permissions are copied into a user's session at sign-in, so users must sign out and back in after a change.</small>
        </div>

        <p>
          <button type="submit" class="btn primary"><?= $role_editing ? 'Save changes' : 'Create role' ?></button>
          <a class="btn" href="users.php?tab=roles">Cancel</a>
        </p>
      </form>
    </section>
  <?php else: ?>
    <p><a class="btn primary" href="users.php?tab=roles&amp;action=new">Add a role</a></p>
  <?php endif; ?>

  <section class="panel">
    <h2>Existing roles</h2>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th>Role</th><th>Description</th><th>Permissions</th><th>Accounts</th><th class="right">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $role): ?>
          <tr>
            <td><?= e($role['role_name']) ?></td>
            <td><?= e($role['description']) ?></td>
            <td><?= e(str_replace(',', ', ', $role['permissions'])) ?></td>
            <td><?= (int) $role['user_count'] ?></td>
            <td class="right">
              <a href="users.php?tab=roles&amp;action=edit&amp;id=<?= (int) $role['role_id'] ?>">Edit</a>
              <?php if (can('delete')): ?>
                &middot;
                <form method="post" action="users.php" class="inlineform"
                      data-confirm="Delete role <?= e($role['role_name']) ?>? This works only while no accounts use it.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="tab" value="roles">
                  <input type="hidden" name="action" value="role_delete">
                  <input type="hidden" name="role_id" value="<?= (int) $role['role_id'] ?>">
                  <button type="submit" class="linkdelete">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="fineprint">A role with accounts assigned to it cannot be deleted. Move those accounts to the Accounts tab first.</p>
  </section>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
