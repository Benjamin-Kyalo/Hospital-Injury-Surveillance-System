<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_permission('delete');

// Deletion is a POST-only action. It needs a valid CSRF token and the
// "delete" permission, which is checked above before anything is changed.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cases.php');
}
check_csrf();

$id = (int) ($_POST['case_id'] ?? 0);

if ($id <= 0) {
    flash('error', 'No case was selected for deletion.');
    redirect('cases.php');
}

$stmt = $pdo->prepare('SELECT case_reference FROM injury_cases WHERE case_id = :id');
$stmt->execute([':id' => $id]);
$reference = $stmt->fetchColumn();

if (!$reference) {
    flash('error', 'That case could not be found. It may already have been deleted.');
    redirect('cases.php');
}

try {
    $stmt = $pdo->prepare('DELETE FROM injury_cases WHERE case_id = :id');
    $stmt->execute([':id' => $id]);
    flash('success', 'Case ' . $reference . ' has been deleted.');
} catch (PDOException $e) {
    error_log('Case delete failed: ' . $e->getMessage());
    flash('error', 'The case could not be deleted. Please try again.');
}

redirect('cases.php');
