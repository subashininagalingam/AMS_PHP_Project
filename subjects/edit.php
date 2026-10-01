<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'subjects';
$page_title = 'Edit Subject';

$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM subjects WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$subject = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$subject) {
    $_SESSION['flash_error'] = 'Subject not found.';
    header("Location: list.php");
    exit;
}

$errors = [];
$form = $subject;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['subject_name'] = trim($_POST['subject_name'] ?? '');
    $form['course_id'] = $_POST['course_id'] ?? '';
    $form['faculty_id'] = $_POST['faculty_id'] ?? '';

    if ($form['subject_name'] === '') $errors[] = 'Subject name is required.';
    if ($form['course_id'] === '') $errors[] = 'Please select a course/class.';

    if (empty($errors)) {
        // duplicate subject check within the same course/class, excluding this row
        $check = mysqli_prepare($conn, "SELECT id FROM subjects WHERE subject_name = ? AND course_id = ? AND id != ?");
        mysqli_stmt_bind_param($check, "sii", $form['subject_name'], $form['course_id'], $id);
        mysqli_stmt_execute($check);
        if (mysqli_stmt_get_result($check)->num_rows > 0) {
            $errors[] = 'This subject already exists for the selected course/class.';
        }
    }

    if (empty($errors)) {
        $faculty_id = $form['faculty_id'] !== '' ? $form['faculty_id'] : null;
        $stmt = mysqli_prepare($conn, "UPDATE subjects SET subject_name=?, course_id=?, faculty_id=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "siii", $form['subject_name'], $form['course_id'], $faculty_id, $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Subject updated successfully.';
            header("Location: list.php");
            exit;
        } else {
            // Log the real DB error for debugging; keep the user-facing message generic.
            error_log('Update subject failed (id=' . $id . '): ' . mysqli_stmt_error($stmt));
            if (mysqli_errno($conn) === 1062) {
                $errors[] = 'This subject already exists for the selected course/class.';
            } else {
                $errors[] = 'Could not update subject. Please try again.';
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
        <h2>Edit Subject</h2>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="post" action="edit.php?id=<?php echo $id; ?>" novalidate>
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
            <button type="submit" class="btn">Update Subject</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.required{color:#a33a2f}
</style>

<?php require_once $root . 'includes/footer.php'; ?>