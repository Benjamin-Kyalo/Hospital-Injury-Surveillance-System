<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$page_title = 'Dashboard';

$totals = $pdo->query('
    SELECT COUNT(*) AS total,
           COUNT(*) FILTER (WHERE incident_date >= date_trunc(\'month\', CURRENT_DATE)) AS this_month,
           COUNT(*) FILTER (WHERE classification = \'Severe\') AS severe,
           COUNT(DISTINCT patient_code) AS patients
    FROM injury_cases
')->fetch();

$by_mechanism = $pdo->query('
    SELECT mechanism, COUNT(*) AS total
    FROM injury_cases
    GROUP BY mechanism
    ORDER BY total DESC, mechanism
')->fetchAll();

$by_outcome = $pdo->query('
    SELECT outcome, COUNT(*) AS total
    FROM injury_cases
    GROUP BY outcome
    ORDER BY total DESC, outcome
')->fetchAll();

$months = array_reverse($pdo->query('
    SELECT to_char(incident_date, \'YYYY-MM\') AS month, COUNT(*) AS total
    FROM injury_cases
    GROUP BY 1
    ORDER BY 1 DESC
    LIMIT 6
')->fetchAll());

function bar_rows(array $rows, string $empty_text): void
{
    if (!$rows) {
        echo '<p class="muted">' . e($empty_text) . '</p>';
        return;
    }
    $max = max(array_column($rows, 'total'));
    echo '<div class="bars">';
    foreach ($rows as $row) {
        $width = $max > 0 ? (int) round($row['total'] / $max * 100) : 0;
        echo '<div class="barrow">';
        echo '<span class="barlabel">' . e($row['mechanism'] ?? $row['outcome']) . '</span>';
        echo '<span class="bartrack"><i style="width:' . $width . '%"></i></span>';
        echo '<b>' . (int) $row['total'] . '</b>';
        echo '</div>';
    }
    echo '</div>';
}

include __DIR__ . '/includes/header.php';
?>

<div class="pagehead">
  <h1>Dashboard</h1>
  <p class="muted">A quick view of the injury cases stored so far.</p>
</div>

<div class="tiles">
  <div class="tile">
    <span class="tilelabel">All cases</span>
    <strong><?= (int) $totals['total'] ?></strong>
  </div>
  <div class="tile">
    <span class="tilelabel">This month</span>
    <strong><?= (int) $totals['this_month'] ?></strong>
  </div>
  <div class="tile">
    <span class="tilelabel">Severe cases</span>
    <strong><?= (int) $totals['severe'] ?></strong>
  </div>
  <div class="tile">
    <span class="tilelabel">Distinct patients</span>
    <strong><?= (int) $totals['patients'] ?></strong>
  </div>
</div>

<div class="twocol">
  <section class="panel">
    <h2>By mechanism</h2>
    <?php bar_rows($by_mechanism, 'No cases recorded yet.'); ?>
  </section>
  <section class="panel">
    <h2>By outcome</h2>
    <?php bar_rows($by_outcome, 'No outcomes recorded yet.'); ?>
  </section>
</div>

<section class="panel">
  <h2>Last six months</h2>
  <?php if (!$months): ?>
    <p class="muted">Nothing to show yet.</p>
  <?php else: ?>
    <table class="simple">
      <thead><tr><th>Month</th><th>Cases</th></tr></thead>
      <tbody>
      <?php foreach ($months as $month): ?>
        <tr>
          <td><?= e(DateTime::createFromFormat('!Y-m', $month['month'])->format('F Y')) ?></td>
          <td><?= (int) $month['total'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php if (can('create')): ?>
  <p><a class="btn" href="case_form.php">Add an injury case</a></p>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
