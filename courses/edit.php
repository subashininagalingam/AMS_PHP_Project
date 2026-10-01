<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'courses';
$page_title = 'Edit Course / Class';

$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM courses WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$course = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$course) {
    $_SESSION['flash_error'] = 'Course/Class not found.';
    header("Location: list.php");
    exit;
}

$errors = [];
$form = $course;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['course_name'] = trim($_POST['course_name'] ?? '');
    $form['semester_year'] = trim($_POST['semester_year'] ?? '');
    $form['section'] = trim($_POST['section'] ?? '');

    if ($form['course_name'] === '') $errors[] = 'Course name is required.';
    if ($form['semester_year'] === '') $errors[] = 'Semester/Year is required.';

    if (empty($errors)) {
        // check duplicate course (same name, semester/year and section) excluding this row
        $check = mysqli_prepare($conn, "SELECT id FROM courses WHERE course_name = ? AND semester_year = ? AND section = ? AND id != ?");
        mysqli_stmt_bind_param($check, "sssi", $form['course_name'], $form['semester_year'], $form['section'], $id);
        mysqli_stmt_execute($check);
        if (mysqli_stmt_get_result($check)->num_rows > 0) {
            $errors[] = 'This course/class already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE courses SET course_name=?, semester_year=?, section=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $form['course_name'], $form['semester_year'], $form['section'], $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Course/Class updated successfully.';
            header("Location: list.php");
            exit;
        } else {
            // Log the real DB error for debugging; keep the user-facing message generic.
            error_log('Update course failed (id=' . $id . '): ' . mysqli_stmt_error($stmt));
            if (mysqli_errno($conn) === 1062) {
                $errors[] = 'This course/class already exists.';
            } else {
                $errors[] = 'Could not update course/class. Please try again.';
            }
        }
    }
}

require_once $root . 'includes/header.php';
?>

<div class="panel" style="max-width:560px;">
    <div class="panel-header">
        <h2>Edit Course / Class</h2>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="post" action="edit.php?id=<?php echo $id; ?>" novalidate>
        <div class="form-group">
            <label for="course_name">Course Name <span class="required">*</span></label>
            <input type="text" id="course_name" name="course_name" required value="<?php echo htmlspecialchars($form['course_name']); ?>">
        </div>
        <div class="form-group">
            <label for="semester_year">Semester / Year <span class="required">*</span></label>
            <input type="text" id="semester_year" name="semester_year" required value="<?php echo htmlspecialchars($form['semester_year']); ?>">
        </div>
        <div class="form-group">
            <label for="section">Section (optional)</label>
            <input type="text" id="section" name="section" value="<?php echo htmlspecialchars($form['section']); ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Update Course / Class</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.required{color:#a33a2f}
</style>

<?php require_once $root . 'includes/footer.php'; ?>