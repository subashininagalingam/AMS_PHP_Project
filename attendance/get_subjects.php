<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';

header('Content-Type: application/json');

$course_id = (int)($_GET['course_id'] ?? 0);
$subjects = [];

$is_admin = ($_SESSION['user_role'] === 'admin');
$faculty_id = $_SESSION['faculty_id'] ?? 0;

if ($course_id > 0) {
    if ($is_admin) {
        $stmt = mysqli_prepare($conn, "SELECT id, subject_name FROM subjects WHERE course_id = ? ORDER BY subject_name");
        mysqli_stmt_bind_param($stmt, "i", $course_id);
    } else {
        // Faculty only ever see subjects assigned to them
        $stmt = mysqli_prepare($conn, "SELECT id, subject_name FROM subjects WHERE course_id = ? AND faculty_id = ? ORDER BY subject_name");
        mysqli_stmt_bind_param($stmt, "ii", $course_id, $faculty_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $subjects[] = $row;
    }
}

echo json_encode($subjects);
