<?php
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/db.php';
require_permission('create');

$page_title = 'Case form';
$id = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;
$case = [];

if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM injury_cases WHERE case_id = :id');
    $stmt->execute([':id' => $id]);
    $case = $stmt->fetch();
    if (!$case) {
        flash('error', 'That case could not be found.');
        redirect('cases.php');
    }
    if ($case['incident_time']) {
        $case['incident_time'] = substr($case['incident_time'], 0, 5);
    }
}

$reference_preview = $editing ? $case['case_reference'] : preview_case_reference($pdo);

$mechanism_form = ['' => 'Select mechanism'] + array_slice($mechanism_options, 1, null, true);
$injury_form    = ['' => 'Select injury type'] + $injury_options;
$body_form      = ['' => 'Select body part'] + $body_options;
$class_form     = ['' => 'Select severity'] + $class_options;
$outcome_form   = ['' => 'Select outcome'] + array_slice($outcome_options, 1, null, true);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $data = [
        'patient_code'  => trim((string) ($_POST['patient_code'] ?? '')),
        'patient_name'  => trim((string) ($_POST['patient_name'] ?? '')),
        'age'           => trim((string) ($_POST['age'] ?? '')),
        'sex'           => (string) ($_POST['sex'] ?? ''),
        'incident_date' => trim((string) ($_POST['incident_date'] ?? '')),
        'incident_time' => trim((string) ($_POST['incident_time'] ?? '')),
        'mechanism'     => (string) ($_POST['mechanism'] ?? ''),
        'injury_type'   => (string) ($_POST['injury_type'] ?? ''),
        'body_part'     => (string) ($_POST['body_part'] ?? ''),
        'classification'=> (string) ($_POST['classification'] ?? ''),
        'treatment'     => (string) ($_POST['treatment'] ?? ''),
        'outcome'       => (string) ($_POST['outcome'] ?? ''),
        'notes'         => trim((string) ($_POST['notes'] ?? '')),
    ];

    foreach (['patient_code', 'incident_date', 'mechanism', 'injury_type', 'body_part', 'classification', 'outcome'] as $field) {
        if ($data[$field] === '') {
            $errors[] = 'Please fill in ' . str_replace('_', ' ', $field) . '.';
        }
    }
    if (!array_key_exists($data['sex'], $sex_options)) {
        $errors[] = 'Please choose a sex.';
    }
    if (!valid_date($data['incident_date'])) {
        $errors[] = 'Enter a valid incident date.';
    }
    if ($data['age'] !== '' && (!ctype_digit($data['age']) || (int) $data['age'] > 120)) {
        $errors[] = 'Age must be a whole number between 0 and 120.';
    }
    if ($data['incident_time'] !== '') {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $data['incident_time'])) {
            $errors[] = 'Enter a valid incident time.';
        } else {
            $data['incident_time'] = substr($data['incident_time'], 0, 5);
            $_POST['incident_time'] = $data['incident_time'];
        }
    }
    if (mb_strlen($data['patient_code']) > 40) {
        $errors[] = 'Patient code is too long (40 characters maximum).';
    }
    if (mb_strlen($data['patient_name']) > 100) {
        $errors[] = 'Patient name is too long (100 characters maximum).';
    }
    if (mb_strlen($data['notes']) > 1000) {
        $errors[] = 'Notes are too long (1000 characters maximum).';
    }

    if (!$errors) {
        $params = [
            ':patient_code'  => $data['patient_code'],
            ':patient_name'  => $data['patient_name'] === '' ? null : $data['patient_name'],
            ':age'           => $data['age'] === '' ? null : (int) $data['age'],
            ':sex'           => $data['sex'],
            ':incident_date' => $data['incident_date'],
            ':incident_time' => $data['incident_time'] === '' ? null : $data['incident_time'],
            ':mechanism'     => $data['mechanism'],
            ':injury_type'   => $data['injury_type'],
            ':body_part'     => $data['body_part'],
            ':classification'=> $data['classification'],
            ':treatment'     => $data['treatment'] === '' ? null : $data['treatment'],
            ':outcome'       => $data['outcome'],
            ':notes'         => $data['notes'] === '' ? null : $data['notes'],
        ];

        try {
            if ($editing) {
                $sql = 'UPDATE injury_cases SET
                            patient_code = :patient_code, patient_name = :patient_name,
                            age = :age, sex = :sex,
                            incident_date = :incident_date, incident_time = :incident_time,
                            mechanism = :mechanism, injury_type = :injury_type,
                            body_part = :body_part, classification = :classification,
                            treatment = :treatment, outcome = :outcome, notes = :notes,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE case_id = :case_id';
                $params[':case_id'] = $id;
                $pdo->prepare($sql)->execute($params);
                $savedId = $id;
                $message = 'Changes to the case have been saved.';
            } else {
                $next = (int) $pdo->query("SELECT nextval('case_ref_seq')")->fetchColumn();
                $reference = sprintf('INJ-%s-%04d', date('Y'), $next);
                $sql = 'INSERT INTO injury_cases (
                            case_reference, patient_code, patient_name, age, sex,
                            incident_date, incident_time, mechanism, injury_type,
                            body_part, classification, treatment, outcome, notes, recorded_by
                        ) VALUES (
                            :reference, :patient_code, :patient_name, :age, :sex, :incident_date, :incident_time,
                            :mechanism, :injury_type, :body_part, :classification,
                            :treatment, :outcome, :notes, :recorded_by
                        )';
                $params[':reference'] = $reference;
                $params[':recorded_by'] = current_user()['user_id'];
                $pdo->prepare($sql)->execute($params);
                $savedId = (int) $pdo->lastInsertId('injury_cases_case_id_seq');
                $message = 'Case ' . $reference . ' has been saved.';
            }

            flash('success', $message);
            redirect('case_view.php?id=' . $savedId);
        } catch (PDOException $e) {
            error_log('Case save failed: ' . $e->getMessage());
            $errors[] = 'The case could not be saved. Please try again.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="pagehead">
  <h1><?= $editing ? 'Edit case ' . e($case['case_reference']) : 'New injury case' ?></h1>
  <p class="muted">Fields marked with * are required. A case can be found later by its reference, patient code or patient name.</p>
</div>

<?php if ($errors): ?>
  <div class="notice error">
    <strong>Please check the form:</strong>
    <ul>
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="case_form.php<?= $editing ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>

  <section class="panel">
    <h2>Case reference</h2>
    <div class="formgrid">
      <div class="field">
        <label for="case_reference">Reference number</label>
        <input type="text" id="case_reference" value="<?= e($reference_preview) ?>" readonly>
        <small><?= $editing ? 'This number stays the same when you edit the case.' : 'Filled in automatically when the case is saved. Nothing to type here.' ?></small>
      </div>
    </div>
  </section>

  <section class="panel">
    <h2>Patient and incident</h2>
    <div class="formgrid">
      <div class="field">
        <label for="patient_code">Patient code *</label>
        <input type="text" id="patient_code" name="patient_code" maxlength="40" required
               value="<?= value_of('patient_code', $case) ?>">
        <small>Example: PT-1042</small>
      </div>
      <div class="field">
        <label for="patient_name">Patient name</label>
        <input type="text" id="patient_name" name="patient_name" maxlength="100"
               value="<?= value_of('patient_name', $case) ?>">
        <small>Optional. Include it if you want the case searchable by name.</small>
      </div>
      <div class="field">
        <label for="age">Age</label>
        <input type="number" id="age" name="age" min="0" max="120"
               value="<?= value_of('age', $case) ?>">
      </div>
      <div class="field">
        <label for="sex">Sex *</label>
        <select id="sex" name="sex" required><?= options($sex_options, $_POST['sex'] ?? $case['sex'] ?? 'Not recorded') ?></select>
      </div>
      <div class="field">
        <label for="incident_date">Incident date *</label>
        <input type="date" id="incident_date" name="incident_date" required
               value="<?= value_of('incident_date', $case) ?: date('Y-m-d') ?>">
      </div>
      <div class="field">
        <label for="incident_time">Incident time</label>
        <input type="time" id="incident_time" name="incident_time"
               value="<?= value_of('incident_time', $case) ?>">
      </div>
    </div>
  </section>

  <section class="panel">
    <h2>Injury details</h2>
    <div class="formgrid">
      <div class="field">
        <label for="mechanism">Mechanism / cause *</label>
        <select id="mechanism" name="mechanism" required><?= options($mechanism_form, $_POST['mechanism'] ?? $case['mechanism'] ?? '') ?></select>
      </div>
      <div class="field">
        <label for="injury_type">Injury type *</label>
        <select id="injury_type" name="injury_type" required><?= options($injury_form, $_POST['injury_type'] ?? $case['injury_type'] ?? '') ?></select>
      </div>
      <div class="field">
        <label for="body_part">Body part *</label>
        <select id="body_part" name="body_part" required><?= options($body_form, $_POST['body_part'] ?? $case['body_part'] ?? '') ?></select>
      </div>
      <div class="field">
        <label for="classification">Severity *</label>
        <select id="classification" name="classification" required><?= options($class_form, $_POST['classification'] ?? $case['classification'] ?? '') ?></select>
      </div>
    </div>
  </section>

  <section class="panel">
    <h2>Treatment and outcome</h2>
    <div class="formgrid">
      <div class="field">
        <label for="treatment">Intervention</label>
        <select id="treatment" name="treatment"><?= options($treatment_options, $_POST['treatment'] ?? $case['treatment'] ?? '') ?></select>
      </div>
      <div class="field">
        <label for="outcome">Outcome *</label>
        <select id="outcome" name="outcome" required><?= options($outcome_form, $_POST['outcome'] ?? $case['outcome'] ?? '') ?></select>
      </div>
      <div class="field wide">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="3" maxlength="1000"><?= value_of('notes', $case) ?></textarea>
        <small>Optional. Keep it short and factual.</small>
      </div>
    </div>
  </section>

  <p>
    <button type="submit" class="btn primary"><?= $editing ? 'Save changes' : 'Save case' ?></button>
    <a class="btn" href="<?= $editing ? 'case_view.php?id=' . (int) $id : 'cases.php' ?>">Cancel</a>
  </p>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
