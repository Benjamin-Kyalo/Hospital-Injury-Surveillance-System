<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Sign in';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare('
        SELECT u.user_id, u.username, u.full_name, u.password_hash,
               CASE WHEN u.active THEN 1 ELSE 0 END AS active,
               r.role_name, r.permissions
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.username = :username
    ');
    $stmt->execute([':username' => $username]);
    $account = $stmt->fetch();

    if ($account && (int) $account['active'] === 1 && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'user_id'   => (int) $account['user_id'],
            'username'  => $account['username'],
            'full_name' => $account['full_name'],
            'role_name' => $account['role_name'],
        ];
        $_SESSION['permissions'] = array_filter(explode(',', $account['permissions']), 'strlen');
        redirect('dashboard.php');
    }

    $error = 'Incorrect username or password, or the account is not active.';
}

include __DIR__ . '/includes/header.php';
?>

<div class="login-box">
  <h1>Sign in</h1>
  <p class="muted">Use the account given to you by the administrator.</p>

  <?php if ($error): ?>
    <div class="notice error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="index.php">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autocomplete="username"
           value="<?= value_of('username') ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">

    <button type="submit" class="btn primary full">Sign in</button>
  </form>

  <p class="fineprint">Demo accounts are listed in the README file. This system holds sample data only.</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
