<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'subjects';
$page_title = 'Add Subject';

$errors = [];
$form = ['subject_name' => '', 'course_id' => '', 'faculty_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['subject_name'] = trim($_POST['subject_name'] ?? '');
    $form['course_id'] = $_POST['course_id'] ?? '';
    $form['faculty_id'] = $_POST['faculty_id'] ?? '';

    if ($form['subject_name'] === '') $errors[] = 'Subject name is required.';
    if ($form['course_id'] === '') $errors[] = 'Please select a course/class.';

    if (empty($errors)) {
        // check duplicate subject within the same course/class
        $check = mysqli_prepare($conn, "SELECT id FROM subjects WHERE subject_name = ? AND course_id = ?");
        mysqli_stmt_bind_param($check, "si", $form['subject_name'], $form['course_id']);
        mysqli_stmt_execute($check);
        if (mysqli_stmt_get_result($check)->num_rows > 0) {
            $errors[] = 'This subject already exists for the selected course/class.';
        }
    }

    if (empty($errors)) {
        $faculty_id = $form['faculty_id'] !== '' ? $form['faculty_id'] : null;
        $stmt = mysqli_prepare($conn, "INSERT INTO subjects (subject_name, course_id, faculty_id) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sii", $form['subject_name'], $form['course_id'], $faculty_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Subject added successfully.';
            header("Location: list.php");
            exit;
        } else {
            // Log the real DB error for debugging; keep the user-facing message generic.
            error_log('Add subject insert failed: ' . mysqli_stmt_error($stmt));
            if (mysqli_errno($conn) === 1062) {
                $errors[] = 'This subject already exists for the selected course/class.';
            } else {
                $errors[] = 'Could not save subject. Please try again.';
            }
        }
    }
}

$courses = mysqli_query($conn, "SELECT id, course_name, section FROM courses ORDER BY course_name");
$faculty = mysqli_query($conn, "SELECT id, name FROM faculty ORDER BY name");

require_once $root . 'includes/header.php';
?>

<div class="panel" style="max-width:560px;">
    <div class="panel-header">
        <h2>Add Subject</h2>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <?php if (mysqli_num_rows($courses) === 0): ?>
        <div class="alert alert-error">No classes exist yet. <a href="<?php echo $root; ?>courses/add.php">Add a course/class</a> first.</div>
        <?php mysqli_data_seek($courses, 0); ?>
    <?php endif; ?>

    <form method="post" action="add.php" novalidate>
        <div class="form-group">
            <label for="subject_name">Subject Name <span class="required">*</span></label>
            <input type="text" id="subject_name" name="subject_name" required value="<?php echo htmlspecialchars($form['subject_name']); ?>">
        </div>
        <div class="form-group">
            <label for="course_id">Course / Class <span class="required">*</span></label>
            <select id="course_id" name="course_id" required>
                <option value="">-- Select course/class --</option>
                <?php while ($c = mysqli_fetch_assoc($courses)): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($form['course_id'] == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['course_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="faculty_id">Assign Faculty (optional)</label>
            <select id="faculty_id" name="faculty_id">
                <option value="">-- Unassigned --</option>
                <?php while ($f = mysqli_fetch_assoc($faculty)): ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo ($form['faculty_id'] == $f['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Save Subject</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>


<?php require_once $root . 'includes/footer.php'; ?>