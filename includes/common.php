<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('injury_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo 'The form has expired. Go back and try again.';
        exit;
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $item = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $item;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function can(string $permission): bool
{
    return in_array($permission, $_SESSION['permissions'] ?? [], true);
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please sign in to continue.');
        redirect('index.php');
    }
}

function require_permission(string $permission): void
{
    global $pdo;
    require_login();
    if (!can($permission)) {
        http_response_code(403);
        $page_title = 'Access denied';
        include __DIR__ . '/header.php';
        echo '<div class="notice error">You do not have permission to use that page. Ask the administrator if you think this is a mistake.</div>';
        include __DIR__ . '/footer.php';
        exit;
    }
}

// Reads a value from the app_settings table. Falls back safely on older databases
// that have not had the settings migration applied yet.
function get_setting(PDO $db, string $key, string $default = ''): string
{
    try {
        $stmt = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return ($value === false || $value === '') ? $default : (string) $value;
    } catch (PDOException $e) {
        return $default;
    }
}

function options(array $items, $selected = ''): string
{
    $html = '';
    foreach ($items as $value => $label) {
        $chosen = (string) $selected === (string) $value ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $chosen . '>' . e($label) . '</option>';
    }
    return $html;
}

function value_of(string $key, array $fallback = []): string
{
    return e($_POST[$key] ?? $fallback[$key] ?? '');
}

function valid_date(?string $date): bool
{
    if (!$date) {
        return false;
    }
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

// Shows the next value in a sequence without consuming it.
function peek_sequence(PDO $pdo, string $name): int
{
    if (!in_array($name, ['case_ref_seq', 'patient_code_seq'], true)) {
        throw new InvalidArgumentException('Unknown sequence.');
    }
    $stmt = $pdo->prepare("SELECT last_value, is_called FROM $name");
    $stmt->execute();
    $seq = $stmt->fetch();
    return ($seq && $seq['is_called']) ? (int) $seq['last_value'] + 1 : (int) ($seq['last_value'] ?? 0);
}

function preview_case_reference(PDO $pdo): string
{
    return sprintf('INJ-%s-%04d', date('Y'), peek_sequence($pdo, 'case_ref_seq'));
}

function preview_patient_code(PDO $pdo): string
{
    return sprintf('PT-%04d', peek_sequence($pdo, 'patient_code_seq'));
}

function query_value(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
}

function read_filters(): array
{
    $filters = [
        'q'         => query_value('q'),
        'from'      => query_value('from'),
        'to'        => query_value('to'),
        'mechanism' => query_value('mechanism'),
        'outcome'   => query_value('outcome'),
    ];

    if (!valid_date($filters['from'])) {
        $filters['from'] = '';
    }
    if (!valid_date($filters['to'])) {
        $filters['to'] = '';
    }
    return $filters;
}

// Shared by the case list, reports and CSV export so the filters behave the same.
function case_filter_sql(array $filters, array &$params): string
{
    $parts = [];

    if ($filters['q'] !== '') {
        $parts[] = '(case_reference ILIKE :q OR patient_code ILIKE :q OR patient_name ILIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }
    if ($filters['from'] !== '') {
        $parts[] = 'incident_date >= :from_date';
        $params[':from_date'] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $parts[] = 'incident_date <= :to_date';
        $params[':to_date'] = $filters['to'];
    }
    if ($filters['mechanism'] !== '') {
        $parts[] = 'mechanism = :mechanism';
        $params[':mechanism'] = $filters['mechanism'];
    }
    if ($filters['outcome'] !== '') {
        $parts[] = 'outcome = :outcome';
        $params[':outcome'] = $filters['outcome'];
    }

    return $parts ? 'WHERE ' . implode(' AND ', $parts) : '';
}

$mechanism_options = [
    ''                    => 'All mechanisms',
    'Road traffic crash'  => 'Road traffic crash',
    'Fall'                => 'Fall',
    'Assault'             => 'Assault',
    'Burn'                => 'Burn',
    'Drowning'            => 'Drowning',
    'Machinery'           => 'Machinery',
    'Sports / leisure'    => 'Sports / leisure',
    'Other'               => 'Other',
];

$injury_options = [
    'Fracture'           => 'Fracture',
    'Laceration'         => 'Laceration',
    'Contusion'          => 'Contusion',
    'Burn'               => 'Burn',
    'Sprain'             => 'Sprain',
    'Penetrating wound'  => 'Penetrating wound',
    'Other'              => 'Other',
];

$body_options = [
    'Head / neck'       => 'Head / neck',
    'Chest'             => 'Chest',
    'Abdomen / pelvis'  => 'Abdomen / pelvis',
    'Upper limb'        => 'Upper limb',
    'Lower limb'        => 'Lower limb',
    'Back / spine'      => 'Back / spine',
    'Multiple sites'    => 'Multiple sites',
    'Other'             => 'Other',
];

$class_options = [
    'Minor'    => 'Minor',
    'Moderate' => 'Moderate',
    'Severe'   => 'Severe',
];

$treatment_options = [
    ''                 => 'Not recorded',
    'First aid'        => 'First aid',
    'Wound care'       => 'Wound care',
    'Immobilisation'   => 'Immobilisation',
    'Medication'       => 'Medication',
    'Surgery'          => 'Surgery',
    'Observation'      => 'Observation',
    'Referred'         => 'Referred',
    'Other'            => 'Other',
];

$outcome_options = [
    ''                             => 'All outcomes',
    'Treated and discharged'       => 'Treated and discharged',
    'Admitted'                     => 'Admitted',
    'Referred to another facility' => 'Referred to another facility',
    'Left against advice'          => 'Left against advice',
    'Deceased'                     => 'Deceased',
    'Still in care'                => 'Still in care',
];

$sex_options = [
    'Not recorded' => 'Not recorded',
    'Male'         => 'Male',
    'Female'       => 'Female',
];

// Permissions that can be given to a role on the Users & roles page (users.php).
// Keep this list in step with the require_permission()/can() checks in the pages.
$permission_options = [
    'view'         => 'View cases and reports',
    'create'       => 'Create and edit cases',
    'export'       => 'Download CSV reports',
    'manage_users' => 'Manage accounts and roles',
    'delete'       => 'Delete cases, accounts and roles',
];
