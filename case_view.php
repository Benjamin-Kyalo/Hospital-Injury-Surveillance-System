<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$page_title = 'Case details';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT c.*, u.full_name AS recorded_by_name, r.role_name
    FROM injury_cases c
    LEFT JOIN users u ON u.user_id = c.recorded_by
    LEFT JOIN roles r ON r.role_id = u.role_id
    WHERE c.case_id = :id
');
$stmt->execute([':id' => $id]);
$case = $stmt->fetch();

if (!$case) {
    flash('error', 'That case could not be found.');
    redirect('cases.php');
}

include __DIR__ . '/includes/header.php';
?>

<div class="pagehead">
  <h1><?= e($case['case_reference']) ?></h1>
  <p class="muted">Recorded on <?= e(date('d M Y', strtotime($case['created_at']))) ?>
     by <?= e($case['recorded_by_name'] ?? 'unknown user') ?><?= $case['role_name'] ? ' (' . e($case['role_name']) . ')' : '' ?>.</p>
</div>

<div class="twocol">
  <section class="panel">
    <h2>Patient and incident</h2>
    <dl class="details">
      <dt>Patient code</dt><dd><?= e($case['patient_code']) ?></dd>
      <dt>Age</dt><dd><?= $case['age'] !== null ? e($case['age']) . ' years' : 'Not recorded' ?></dd>
      <dt>Sex</dt><dd><?= e($case['sex']) ?></dd>
      <dt>Incident date</dt><dd><?= e(date('d M Y', strtotime($case['incident_date']))) ?></dd>
      <dt>Incident time</dt><dd><?= $case['incident_time'] ? e(substr($case['incident_time'], 0, 5)) : 'Not recorded' ?></dd>
    </dl>
  </section>

  <section class="panel">
    <h2>Injury classification</h2>
    <dl class="details">
      <dt>Mechanism</dt><dd><?= e($case['mechanism']) ?></dd>
      <dt>Injury type</dt><dd><?= e($case['injury_type']) ?></dd>
      <dt>Body part</dt><dd><?= e($case['body_part']) ?></dd>
      <dt>Severity</dt><dd><?= e($case['classification']) ?></dd>
    </dl>
  </section>
</div>

<section class="panel">
  <h2>Treatment and outcome</h2>
  <dl class="details">
    <dt>Intervention</dt><dd><?= $case['treatment'] ? e($case['treatment']) : 'Not recorded' ?></dd>
    <dt>Outcome</dt><dd><?= e($case['outcome']) ?></dd>
    <dt>Notes</dt><dd><?= $case['notes'] ? nl2br(e($case['notes'])) : 'None' ?></dd>
    <?php if ($case['updated_at']): ?>
      <dt>Last updated</dt><dd><?= e(date('d M Y H:i', strtotime($case['updated_at']))) ?></dd>
    <?php endif; ?>
  </dl>
</section>

<p>
  <?php if (can('create')): ?>
    <a class="btn primary" href="case_form.php?id=<?= (int) $case['case_id'] ?>">Edit this case</a>
  <?php endif; ?>
  <a class="btn" href="cases.php">Back to cases</a>
</p>

<?php include __DIR__ . '/includes/footer.php'; ?>
