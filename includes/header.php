<?php
$page_title = $page_title ?? 'Injury Surveillance';
$user = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
$hospital_name = isset($pdo) ? get_setting($pdo, 'hospital_name', 'General Hospital') : 'General Hospital';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> | Injury Surveillance</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="topbar">
  <div class="wrap topbar-inner">
    <div class="brand">
      <strong>Injury Surveillance</strong>
      <span><?= e($hospital_name) ?></span>
    </div>
    <?php if ($user): ?>
      <div class="topbar-user">
        <span><?= e($user['full_name']) ?> <small>(<?= e($user['role_name']) ?>)</small></span>
        <a href="logout.php">Sign out</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($user): ?>
<nav class="mainnav">
  <div class="wrap navlinks">
    <a href="dashboard.php"<?= $current_page === 'dashboard.php' ? ' class="active"' : '' ?>>Dashboard</a>
    <a href="cases.php"<?= in_array($current_page, ['cases.php', 'case_view.php', 'case_form.php'], true) ? ' class="active"' : '' ?>>Cases</a>
    <?php if (can('create')): ?>
      <a href="case_form.php"<?= $current_page === 'case_form.php' && empty($_GET['id']) ? ' class="active"' : '' ?>>New case</a>
    <?php endif; ?>
    <a href="reports.php"<?= in_array($current_page, ['reports.php', 'export.php'], true) ? ' class="active"' : '' ?>>Reports</a>
    <?php if (can('manage_users')): ?>
      <a href="users.php"<?= $current_page === 'users.php' ? ' class="active"' : '' ?>>Users &amp; roles</a>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>

<div class="wrap page">
  <?php $flash = take_flash(); if ($flash): ?>
    <div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
