<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$page_title = 'Cases';
$filters = read_filters();
$params = [];
$where = case_filter_sql($filters, $params);

$stmt = $pdo->prepare("
    SELECT case_id, case_reference, incident_date, patient_code, patient_name, mechanism,
           injury_type, body_part, classification, outcome
    FROM injury_cases
    $where
    ORDER BY incident_date DESC, case_id DESC
    LIMIT 100
");
$stmt->execute($params);
$cases = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="pagehead">
  <h1>Injury cases</h1>
  <p class="muted">Search the register, then open a case to see the full record.</p>
</div>

<section class="panel">
  <h2>Find a case</h2>
  <form method="get" action="cases.php" class="filters">
    <div class="field">
      <label for="q">Reference, patient code or name</label>
      <input type="text" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. INJ-2026, PT-1004 or Amina">
    </div>
    <div class="field">
      <label for="mechanism">Mechanism</label>
      <select id="mechanism" name="mechanism"><?= options($mechanism_options, $filters['mechanism']) ?></select>
    </div>
    <div class="field">
      <label for="outcome">Outcome</label>
      <select id="outcome" name="outcome"><?= options($outcome_options, $filters['outcome']) ?></select>
    </div>
    <div class="field">
      <label for="from">Date from</label>
      <input type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
      <label for="to">Date to</label>
      <input type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field actions">
      <button type="submit" class="btn primary">Search</button>
      <a class="btn subtle" href="cases.php">Clear</a>
    </div>
  </form>
</section>

<section class="panel">
  <div class="panelhead">
    <h2>Results</h2>
    <span class="muted"><?= count($cases) ?> case<?= count($cases) === 1 ? '' : 's' ?> found</span>
  </div>

  <?php if (!$cases): ?>
    <p class="muted">No cases match those filters.</p>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th>Reference</th>
            <th>Incident date</th>
            <th>Patient code</th>
            <th>Patient name</th>
            <th>Mechanism</th>
            <th>Injury</th>
            <th>Outcome</th>
            <th class="right">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($cases as $case): ?>
          <tr>
            <td><a href="case_view.php?id=<?= (int) $case['case_id'] ?>"><?= e($case['case_reference']) ?></a></td>
            <td><?= e(date('d M Y', strtotime($case['incident_date']))) ?></td>
            <td><?= e($case['patient_code']) ?></td>
            <td><?= $case['patient_name'] ? e($case['patient_name']) : '<span class="muted">Not recorded</span>' ?></td>
            <td><?= e($case['mechanism']) ?></td>
            <td><?= e($case['injury_type']) ?></td>
            <td><?= e($case['outcome']) ?></td>
            <td class="right">
              <a href="case_view.php?id=<?= (int) $case['case_id'] ?>">View</a>
              <?php if (can('create')): ?>
                &middot; <a href="case_form.php?id=<?= (int) $case['case_id'] ?>">Edit</a>
              <?php endif; ?>
              <?php if (can('delete')): ?>
                &middot;
                <form method="post" action="case_delete.php" class="inlineform"
                      data-confirm="Delete case <?= e($case['case_reference']) ?>? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="case_id" value="<?= (int) $case['case_id'] ?>">
                  <button type="submit" class="linkdelete">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="fineprint">Showing the 100 newest matching cases at most.</p>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
