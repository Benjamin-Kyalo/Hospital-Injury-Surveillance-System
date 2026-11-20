<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$page_title = 'Reports';
$filters = read_filters();
$params = [];
$where = case_filter_sql($filters, $params);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM injury_cases $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

function summary_rows(PDO $pdo, string $where, array $params, string $column): array
{
    $stmt = $pdo->prepare("
        SELECT $column AS label, COUNT(*) AS total
        FROM injury_cases
        $where
        GROUP BY $column
        ORDER BY total DESC, label
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$mechanism_rows = summary_rows($pdo, $where, $params, 'mechanism');
$outcome_rows   = summary_rows($pdo, $where, $params, 'outcome');
$severity_rows  = summary_rows($pdo, $where, $params, 'classification');

$stmt = $pdo->prepare("
    SELECT to_char(incident_date, 'YYYY-MM') AS label, COUNT(*) AS total
    FROM injury_cases
    $where
    GROUP BY 1
    ORDER BY 1 DESC
    LIMIT 6
");
$stmt->execute($params);
$month_rows = array_reverse($stmt->fetchAll());

function summary_table(array $rows, string $total_label, int $total): void
{
    echo '<table class="simple"><thead><tr><th>' . e($total_label) . '</th><th>Cases</th><th>Share</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="3" class="muted">No matching cases.</td></tr>';
    }
    foreach ($rows as $row) {
        $label = $row['label'];
        if (preg_match('/^\d{4}-\d{2}$/', $label)) {
            $label = DateTime::createFromFormat('!Y-m', $label)->format('F Y');
        }
        $share = $total > 0 ? (int) round($row['total'] / $total * 100) : 0;
        echo '<tr><td>' . e($label) . '</td><td>' . (int) $row['total'] . '</td><td>' . $share . '%</td></tr>';
    }
    echo '</tbody></table>';
}

include __DIR__ . '/includes/header.php';
?>

<div class="pagehead">
  <h1>Reports</h1>
  <p class="muted">Totals are worked out from the cases that match the filters below.</p>
</div>

<section class="panel">
  <h2>Filters</h2>
  <form method="get" action="reports.php" class="filters">
    <div class="field">
      <label for="q">Reference, patient code or name</label>
      <input type="text" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. PT-1004 or Amina">
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
      <button type="submit" class="btn primary">Apply</button>
      <a class="btn subtle" href="reports.php">Clear</a>
    </div>
  </form>
</section>

<div class="pagehead small">
  <h2>Matching cases: <?= $total ?></h2>
  <?php if (can('export')): ?>
    <a class="btn" href="export.php?<?= e(http_build_query(array_filter($filters))) ?>">Download CSV</a>
  <?php endif; ?>
</div>

<div class="twocol">
  <section class="panel">
    <h2>By mechanism</h2>
    <?php summary_table($mechanism_rows, 'Mechanism', $total); ?>
  </section>
  <section class="panel">
    <h2>By outcome</h2>
    <?php summary_table($outcome_rows, 'Outcome', $total); ?>
  </section>
</div>

<div class="twocol">
  <section class="panel">
    <h2>By severity</h2>
    <?php summary_table($severity_rows, 'Severity', $total); ?>
  </section>
  <section class="panel">
    <h2>By month</h2>
    <?php summary_table($month_rows, 'Month', $total); ?>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
