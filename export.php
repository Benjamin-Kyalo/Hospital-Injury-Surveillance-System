<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_permission('export');

$filters = read_filters();
$params = [];
$where = case_filter_sql($filters, $params);

$stmt = $pdo->prepare("
    SELECT case_reference, incident_date, incident_time, patient_code, age, sex,
           mechanism, injury_type, body_part, classification, treatment, outcome, notes
    FROM injury_cases
    $where
    ORDER BY incident_date DESC, case_id DESC
");
$stmt->execute($params);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="injury_cases_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'Reference', 'Incident date', 'Time', 'Patient code', 'Age', 'Sex',
    'Mechanism', 'Injury type', 'Body part', 'Severity',
    'Intervention', 'Outcome', 'Notes',
]);

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['case_reference'], $row['incident_date'], $row['incident_time'],
        $row['patient_code'], $row['age'], $row['sex'], $row['mechanism'],
        $row['injury_type'], $row['body_part'], $row['classification'],
        $row['treatment'], $row['outcome'], $row['notes'],
    ]);
}

fclose($output);
exit;
