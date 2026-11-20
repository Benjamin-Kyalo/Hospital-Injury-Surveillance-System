<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_permission('manage_users');

$page_title = 'Roles and permissions';

$action  = (string) ($_GET['action'] ?? '');
$edit_id = (int) ($_GET['id'] ?? 0);
$editing = $action === 'edit' && $edit_id > 0;
$show_form = $action === 'new' || $editing;
$form_role = [];
$errors = [];

if ($editing) {
    $stmt = $pdo->prepare('SELECT role_id, role_name, description, permissions FROM roles WHERE role_id = :id');
    $stmt->execute([':id' => $edit_id]);
    $form_role = $stmt->fetch();
    if (!$form_role) {
        flash('error', 'That role could not be found.');
        redirect('roles.php');
    }
    $form_role['permissions'] = explode(',', $form_role['permissions']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $post_action = (string) ($_POST['action'] ?? 'new');

    // Delete is handled on its own so it can never fall through to a save.
    if ($post_action === 'delete') {
        require_permission('delete');

        $delete_id = (int) ($_POST['role_id'] ?? 0);
        if ($delete_id <= 0) {
            flash('error', 'No role was selected for deletion.');
            redirect('roles.php');
        }

        $stmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = :id');
        $stmt->execute([':id' => $delete_id]);
        $role_name = $stmt->fetchColumn();
        if (!$role_name) {
            flash('error', 'That role could not be found. It may already have been deleted.');
            redirect('roles.php');
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = :id');
        $stmt->execute([':id' => $delete_id]);
        $user_count = (int) $stmt->fetchColumn();
        if ($user_count > 0) {
            flash('error', 'Role ' . $role_name . ' is still assigned to ' . $user_count .
                           ' account(s). Move those accounts to another role first.');
            redirect('roles.php');
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM roles WHERE role_id = :id');
            $stmt->execute([':id' => $delete_id]);
            flash('success', 'Role ' . $role_name . ' has been deleted.');
        } catch (PDOException $e) {
            error_log('Role delete failed: ' . $e->getMessage());
            flash('error', 'The role could not be deleted. Please try again.');
        }
        redirect('roles.php');
    }

    $role_id   = (int) ($_POST['role_id'] ?? 0);
    $is_edit   = $post_action === 'edit' && $role_id > 0;
    $show_form = true;
    $editing   = $is_edit;

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
            redirect('roles.php');
        } catch (PDOException $e) {
            error_log('Role save failed: ' . $e->getMessage());
            $errors[] = 'The role could not be saved. Please try again.';
        }
    }
}

$roles = $pdo->query('
    SELECT r.role_id, r.role_name, r.description, r.permissions,
           (SELECT COUNT(*) FROM users u WHERE u.role_id = r.role_id) AS user_count
    FROM roles r
    ORDER BY r.role_id
')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="pagehead">
  <h1>Roles and permissions</h1>
  <p class="muted">Create roles, choose their permissions, and delete roles that are no longer used.</p>
</div>

<?php if ($show_form): ?>
  <section class="panel">
    <h2><?= $editing ? 'Edit role' : 'New role' ?></h2>

    <?php if ($errors): ?>
      <div class="notice error">
        <strong>Please check the form:</strong>
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" action="roles.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'new' ?>">
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
        <button type="submit" class="btn primary"><?= $editing ? 'Save changes' : 'Create role' ?></button>
        <a class="btn" href="roles.php">Cancel</a>
      </p>
    </form>
  </section>
<?php else: ?>
  <p><a class="btn primary" href="roles.php?action=new">Add a role</a></p>
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
            <a href="roles.php?action=edit&id=<?= (int) $role['role_id'] ?>">Edit</a>
            <?php if (can('delete')): ?>
              &middot;
              <form method="post" action="roles.php" class="inlineform"
                    data-confirm="Delete role <?= e($role['role_name']) ?>? This works only while no accounts use it.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
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
  <p class="fineprint">A role with accounts assigned to it cannot be deleted. Move those accounts first.</p>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
