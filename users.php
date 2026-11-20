<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_permission('manage_users');

$page_title = 'Users';

$roles = $pdo->query('SELECT role_id, role_name, description FROM roles ORDER BY role_id')->fetchAll();
$role_choices = [];
foreach ($roles as $role) {
    $role_choices[$role['role_id']] = $role['role_name'] . ' - ' . $role['description'];
}

$action = (string) ($_GET['action'] ?? '');
$edit_id = (int) ($_GET['id'] ?? 0);
$editing = $action === 'edit' && $edit_id > 0;
$show_form = $action === 'new' || $editing;
$form_user = [];
$errors = [];

if ($editing) {
    $stmt = $pdo->prepare('SELECT user_id, username, full_name, role_id,
                           CASE WHEN active THEN 1 ELSE 0 END AS active
                           FROM users WHERE user_id = :id');
    $stmt->execute([':id' => $edit_id]);
    $form_user = $stmt->fetch();
    if (!$form_user) {
        flash('error', 'That user could not be found.');
        redirect('users.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $post_action = (string) ($_POST['action'] ?? 'new');
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $is_edit = $post_action === 'edit' && $user_id > 0;
    $show_form = true;
    $editing = $is_edit;
    $form_user = [
        'user_id'   => $user_id,
        'username'  => trim((string) ($_POST['username'] ?? '')),
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'role_id'   => (int) ($_POST['role_id'] ?? 0),
        'active'    => isset($_POST['active']) ? 1 : 0,
    ];

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');
    $role_ids = array_column($roles, 'role_id');

    if ($form_user['username'] === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $form_user['username'])) {
        $errors[] = 'Username may only contain letters, numbers, dots, dashes and underscores.';
    }
    if (mb_strlen($form_user['full_name']) < 2 || mb_strlen($form_user['full_name']) > 100) {
        $errors[] = 'Enter the full name (2 to 100 characters).';
    }
    if (!in_array($form_user['role_id'], $role_ids, true)) {
        $errors[] = 'Choose a valid role.';
    }
    if (!$is_edit || $password !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }
    }
    if ($is_edit && $user_id === current_user()['user_id'] && !$form_user['active']) {
        $errors[] = 'You cannot deactivate your own account.';
    }

    $stmt = $pdo->prepare(
        $is_edit
            ? 'SELECT COUNT(*) FROM users WHERE username = :username AND user_id <> :id'
            : 'SELECT COUNT(*) FROM users WHERE username = :username'
    );
    $check = [':username' => $form_user['username']];
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
                    ':username'  => $form_user['username'],
                    ':full_name' => $form_user['full_name'],
                    ':role_id'   => $form_user['role_id'],
                    ':active'    => $form_user['active'],
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
                    ':username'      => $form_user['username'],
                    ':full_name'     => $form_user['full_name'],
                    ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    ':role_id'       => $form_user['role_id'],
                    ':active'        => $form_user['active'],
                ]);
                $message = 'User account created.';
            }
            flash('success', $message);
            redirect('users.php');
        } catch (PDOException $e) {
            error_log('User save failed: ' . $e->getMessage());
            $errors[] = 'The account could not be saved. Please try again.';
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
  <h1>User accounts</h1>
  <p class="muted">Add staff accounts and decide what each role is allowed to do.</p>
</div>

<?php if ($show_form): ?>
  <section class="panel">
    <h2><?= $editing ? 'Edit account' : 'New account' ?></h2>

    <?php if ($errors): ?>
      <div class="notice error">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" action="users.php" data-password-form>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'new' ?>">
      <input type="hidden" name="user_id" value="<?= (int) ($form_user['user_id'] ?? 0) ?>">

      <div class="formgrid">
        <div class="field">
          <label for="username">Username *</label>
          <input type="text" id="username" name="username" required maxlength="50"
                 value="<?= e($form_user['username'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="full_name">Full name *</label>
          <input type="text" id="full_name" name="full_name" required maxlength="100"
                 value="<?= e($form_user['full_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="role_id">Role *</label>
          <select id="role_id" name="role_id" required>
            <?php foreach ($roles as $role): ?>
              <option value="<?= (int) $role['role_id'] ?>"<?= (int) ($form_user['role_id'] ?? 0) === (int) $role['role_id'] ? ' selected' : '' ?>>
                <?= e($role['role_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="checkline">
            <input type="checkbox" name="active" value="1"<?= (int) ($form_user['active'] ?? 1) === 1 ? ' checked' : '' ?>>
            Account active
          </label>
        </div>
        <div class="field">
          <label for="password"><?= $editing ? 'New password' : 'Password *' ?></label>
          <input type="password" id="password" name="password" autocomplete="new-password"
                 <?= $editing ? '' : 'required' ?>>
          <small><?= $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.' ?></small>
        </div>
        <div class="field">
          <label for="confirm_password">Confirm password<?= $editing ? '' : ' *' ?></label>
          <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                 <?= $editing ? '' : 'required' ?>>
        </div>
      </div>

      <p>
        <button type="submit" class="btn primary"><?= $editing ? 'Save changes' : 'Create account' ?></button>
        <a class="btn" href="users.php">Cancel</a>
      </p>
    </form>
  </section>
<?php else: ?>
  <p><a class="btn primary" href="users.php?action=new">Add a user</a></p>
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
          <td class="right"><a href="users.php?action=edit&id=<?= (int) $account['user_id'] ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
