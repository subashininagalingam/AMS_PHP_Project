<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';

$page = 'attendance';
$page_title = 'Mark Attendance';

$is_admin = ($_SESSION['user_role'] === 'admin');
$faculty_id = $_SESSION['faculty_id'] ?? 0;

$course_id = $_GET['course_id'] ?? ($_POST['course_id'] ?? '');
$subject_id = $_GET['subject_id'] ?? ($_POST['subject_id'] ?? '');
$attendance_date = $_GET['attendance_date'] ?? ($_POST['attendance_date'] ?? date('Y-m-d'));

$save_message = '';
$save_error = '';

/**
 * Faculty may only ever touch subjects assigned to them. This check runs
 * regardless of what course_id/subject_id was submitted in the form, so it
 * can't be bypassed by editing the URL or POST data.
 */
function subject_belongs_to_faculty($conn, $subject_id, $faculty_id) {
    $stmt = mysqli_prepare($conn, "SELECT id FROM subjects WHERE id = ? AND faculty_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $subject_id, $faculty_id);
    mysqli_stmt_execute($stmt);
    return (bool) mysqli_stmt_get_result($stmt)->fetch_assoc();
}

// ---- Handle Save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $statuses = $_POST['status'] ?? []; // [student_id => 'Present'/'Absent']

    if ($course_id === '' || $subject_id === '' || $attendance_date === '') {
        $save_error = 'Class, subject and date are all required.';
    } elseif (!$is_admin && !subject_belongs_to_faculty($conn, (int)$subject_id, $faculty_id)) {
        $save_error = 'You are not assigned to that subject.';
        $course_id = '';
        $subject_id = '';
    } elseif (empty($statuses)) {
        $save_error = 'No students to mark for this class.';
    } else {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO attendance (student_id, subject_id, attendance_date, status, faculty_id)
            VALUES (?, ?, ?, ?, (SELECT faculty_id FROM subjects WHERE id = ?))
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $ok = true;
        foreach ($statuses as $student_id => $status) {
            $student_id = (int)$student_id;
            if (!in_array($status, ['Present', 'Absent'], true)) continue;
            mysqli_stmt_bind_param($stmt, "iissi", $student_id, $subject_id, $attendance_date, $status, $subject_id);
            if (!mysqli_stmt_execute($stmt)) {
                // Log the real DB error for debugging; keep the user-facing message generic.
                error_log('Mark attendance insert failed (student_id=' . $student_id . '): ' . mysqli_stmt_error($stmt));
                $ok = false;
            }
        }
        if ($ok) {
            $_SESSION['flash_success'] = 'Attendance saved for ' . date('d M Y', strtotime($attendance_date)) . '.';
            header("Location: " . $root . "reports/reports.php?course_id=" . urlencode($course_id) . "&subject_id=" . urlencode($subject_id) . "&attendance_date=" . urlencode($attendance_date));
            exit;
        } else {
            $save_error = 'Some attendance records could not be saved.';
        }
    }
}

// ---- Course list: admin sees everything, faculty only sees classes where they teach a subject ----
if ($is_admin) {
    $courses = mysqli_query($conn, "SELECT id, course_name, section FROM courses ORDER BY course_name");
} else {
    $courses_stmt = mysqli_prepare($conn, "
        SELECT DISTINCT c.id, c.course_name, c.section
        FROM courses c
        JOIN subjects sub ON sub.course_id = c.id
        WHERE sub.faculty_id = ?
        ORDER BY c.course_name
    ");
    mysqli_stmt_bind_param($courses_stmt, "i", $faculty_id);
    mysqli_stmt_execute($courses_stmt);
    $courses = mysqli_stmt_get_result($courses_stmt);
}

// ---- On direct GET load with a subject already selected, re-verify faculty ownership ----
if (!$is_admin && $subject_id !== '' && !subject_belongs_to_faculty($conn, (int)$subject_id, $faculty_id)) {
    $save_error = 'You are not assigned to that subject.';
    $course_id = '';
    $subject_id = '';
}

$subjects_for_course = [];
if ($course_id !== '') {
    if ($is_admin) {
        $stmt = mysqli_prepare($conn, "SELECT id, subject_name FROM subjects WHERE course_id = ? ORDER BY subject_name");
        mysqli_stmt_bind_param($stmt, "i", $course_id);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, subject_name FROM subjects WHERE course_id = ? AND faculty_id = ? ORDER BY subject_name");
        mysqli_stmt_bind_param($stmt, "ii", $course_id, $faculty_id);
    }
    mysqli_stmt_execute($stmt);
    $subjects_for_course = mysqli_stmt_get_result($stmt);
}

$students = null;
$existing_status = [];

if ($course_id !== '' && $subject_id !== '' && $attendance_date !== '') {
    $stmt = mysqli_prepare($conn, "SELECT id, name, register_no FROM students WHERE class_id = ? ORDER BY name");
    mysqli_stmt_bind_param($stmt, "i", $course_id);
    mysqli_stmt_execute($stmt);
    $students = mysqli_stmt_get_result($stmt);

    $stmt2 = mysqli_prepare($conn, "SELECT student_id, status FROM attendance WHERE subject_id = ? AND attendance_date = ?");
    mysqli_stmt_bind_param($stmt2, "is", $subject_id, $attendance_date);
    mysqli_stmt_execute($stmt2);
    $existing_result = mysqli_stmt_get_result($stmt2);
    while ($row = mysqli_fetch_assoc($existing_result)) {
        $existing_status[$row['student_id']] = $row['status'];
    }
}

require_once $root . 'includes/header.php';
?>

<?php if ($save_error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($save_error); ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Select Class, Subject &amp; Date</h2></div>

    <?php if (!$is_admin && mysqli_num_rows($courses) === 0): ?>
        <div class="empty-state">You have no subjects assigned yet. Contact the administrator.</div>
    <?php else: ?>
    <form method="get" action="mark.php" id="attendance-filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label for="course_id">Class / Course <span class="required">*</span></label>
                <select id="course_id" name="course_id" required>
                    <option value="">-- Select class --</option>
                    <?php while ($c = mysqli_fetch_assoc($courses)): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($course_id == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject_id">Subject <span class="required">*</span></label>
                <select id="subject_id" name="subject_id" required <?php echo $course_id === '' ? 'disabled' : ''; ?>>
                    <?php if ($course_id === ''): ?>
                        <option value="">-- Select class first --</option>
                    <?php else: ?>
                        <option value="">-- Select subject --</option>
                        <?php while ($s = mysqli_fetch_assoc($subjects_for_course)): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($subject_id == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="attendance_date">Date <span class="required">*</span></label>
                <input type="date" id="attendance_date" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Load Students</button>
            </div>
        </div>
    </form>
    <script>
    (function () {
        var form = document.getElementById('attendance-filter-form');
        var courseSelect = document.getElementById('course_id');
        var subjectSelect = document.getElementById('subject_id');

        // Changing the class re-submits right away so the subject list
        // refreshes for that class. Subject and date still need "Load Students".
        courseSelect.addEventListener('change', function () {
            subjectSelect.value = '';
            form.submit();
        });
    })();
    </script>
    <?php endif; ?>
</div>

<?php if ($students !== null): ?>
<div class="panel" id="student-list-area">
    <div class="panel-header">
        <h2>Mark Attendance — <?php echo mysqli_num_rows($students); ?> student(s)</h2>
    </div>

    <?php if (mysqli_num_rows($students) === 0): ?>
        <div class="empty-state">No students found in this class. Add students first.</div>
    <?php else: ?>
    <form method="post" action="mark.php">
        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_id); ?>">
        <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
        <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>">

        <div class="mark-all-row">
            <button type="button" class="btn btn-secondary btn-sm" id="mark-all-present">Mark All Present</button>
            <button type="button" class="btn btn-secondary btn-sm" id="mark-all-absent">Mark All Absent</button>
        </div>

        <div class="attendance-list">
            <?php while ($st = mysqli_fetch_assoc($students)):
                $current = $existing_status[$st['id']] ?? 'Present';
            ?>
            <div class="attendance-row">
                <div class="student-info">
                    <strong><?php echo htmlspecialchars($st['name']); ?></strong>
                    <span><?php echo htmlspecialchars($st['register_no']); ?></span>
                </div>
                <div class="status-toggle">
                    <label class="opt-present">
                        <input type="radio" name="status[<?php echo $st['id']; ?>]" value="Present" <?php echo $current === 'Present' ? 'checked' : ''; ?>>
                        <span>Present</span>
                    </label>
                    <label class="opt-absent">
                        <input type="radio" name="status[<?php echo $st['id']; ?>]" value="Absent" <?php echo $current === 'Absent' ? 'checked' : ''; ?>>
                        <span>Absent</span>
                    </label>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="form-actions">
            <button type="submit" name="save_attendance" value="1" class="btn">Save Attendance</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once $root . 'includes/footer.php'; ?>