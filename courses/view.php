<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'courses';
$page_title = 'Course Details';

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

$students_stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE class_id = ? ORDER BY name");
mysqli_stmt_bind_param($students_stmt, "i", $id);
mysqli_stmt_execute($students_stmt);
$students = mysqli_stmt_get_result($students_stmt);

$subjects_stmt = mysqli_prepare($conn, "
    SELECT sub.*, f.name AS faculty_name
    FROM subjects sub
    LEFT JOIN faculty f ON f.id = sub.faculty_id
    WHERE sub.course_id = ?
    ORDER BY sub.subject_name
");
mysqli_stmt_bind_param($subjects_stmt, "i", $id);
mysqli_stmt_execute($subjects_stmt);
$subjects = mysqli_stmt_get_result($subjects_stmt);

require_once $root . 'includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2><?php echo htmlspecialchars($course['course_name']); ?> — <?php echo htmlspecialchars($course['semester_year']); ?><?php echo $course['section'] ? ' (' . htmlspecialchars($course['section']) . ')' : ''; ?></h2>
        <a href="list.php" class="btn btn-secondary btn-sm">Back to Courses</a>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Students in this Class (<?php echo mysqli_num_rows($students); ?>)</h2></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Register No.</th><th>Phone</th><th>Email</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($students) === 0): ?>
            <tr><td colspan="4" class="empty-state">No students in this class yet.</td></tr>
        <?php else: while ($s = mysqli_fetch_assoc($students)): ?>
            <tr>
                <td><?php echo htmlspecialchars($s['name']); ?></td>
                <td><?php echo htmlspecialchars($s['register_no']); ?></td>
                <td><?php echo htmlspecialchars($s['phone'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($s['email'] ?: '—'); ?></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Subjects in this Class (<?php echo mysqli_num_rows($subjects); ?>)</h2></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Subject</th><th>Faculty</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($subjects) === 0): ?>
            <tr><td colspan="2" class="empty-state">No subjects added for this class yet.</td></tr>
        <?php else: while ($sub = mysqli_fetch_assoc($subjects)): ?>
            <tr>
                <td><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                <td><?php echo htmlspecialchars($sub['faculty_name'] ?: 'Unassigned'); ?></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once $root . 'includes/footer.php'; ?>
