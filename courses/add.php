<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'courses';
$page_title = 'Add Course / Class';

$errors = [];
$form = ['course_name' => '', 'semester_year' => '', 'section' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['course_name'] = trim($_POST['course_name'] ?? '');
    $form['semester_year'] = trim($_POST['semester_year'] ?? '');
    $form['section'] = trim($_POST['section'] ?? '');

    if ($form['course_name'] === '') $errors[] = 'Course name is required.';
    if ($form['semester_year'] === '') $errors[] = 'Semester/Year is required.';

    if (empty($errors)) {
        // check duplicate course (same name, semester/year and section)
        $check = mysqli_prepare($conn, "SELECT id FROM courses WHERE course_name = ? AND semester_year = ? AND section = ?");
        mysqli_stmt_bind_param($check, "sss", $form['course_name'], $form['semester_year'], $form['section']);
        mysqli_stmt_execute($check);
        if (mysqli_stmt_get_result($check)->num_rows > 0) {
            $errors[] = 'This course/class already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO courses (course_name, semester_year, section) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $form['course_name'], $form['semester_year'], $form['section']);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Course/Class added successfully.';
            header("Location: list.php");
            exit;
        } else {
            // Log the real DB error for debugging; keep the user-facing message generic.
            error_log('Add course insert failed: ' . mysqli_stmt_error($stmt));
            if (mysqli_errno($conn) === 1062) {
                $errors[] = 'This course/class already exists.';
            } else {
                $errors[] = 'Could not save course/class. Please try again.';
            }
        }
    }
}

require_once $root . 'includes/header.php';
?>

<div class="panel" style="max-width:560px;">
    <div class="panel-header">
        <h2>Add Course / Class</h2>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="post" action="add.php" novalidate>
        <div class="form-group">
            <label for="course_name">Course Name <span class="required">*</span></label>
            <input type="text" id="course_name" name="course_name" required placeholder="e.g. B.Sc Computer Science" value="<?php echo htmlspecialchars($form['course_name']); ?>">
        </div>
        <div class="form-group">
            <label for="semester_year">Semester / Year <span class="required">*</span></label>
            <input type="text" id="semester_year" name="semester_year" required placeholder="e.g. 3rd Year" value="<?php echo htmlspecialchars($form['semester_year']); ?>">
        </div>
        <div class="form-group">
            <label for="section">Section (optional)</label>
            <input type="text" id="section" name="section" placeholder="e.g. A" value="<?php echo htmlspecialchars($form['section']); ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Save Course / Class</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once $root . 'includes/footer.php'; ?>