<?php
$root = '';
require_once 'config/db.php';
require_once 'includes/auth.php';

$page = 'dashboard';
$page_title = 'Dashboard';

$is_admin = ($_SESSION['user_role'] === 'admin');
$faculty_id = $_SESSION['faculty_id'] ?? 0;
$threshold = 75;
$today = date('Y-m-d');

if ($is_admin) {

    // =====================================================
    // ADMIN DASHBOARD — full system view
    // =====================================================
    $total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM students"))['c'];
    $total_faculty  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM faculty"))['c'];
    $total_courses  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM courses"))['c'];
    $total_subjects = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM subjects"))['c'];

    // Count each STUDENT once today, not once per subject: if a student
    // was marked Present in any one of their subjects today, they count
    // as Present overall. Only students with no Present at all (across
    // every subject marked today) count as Absent.
    $today_stmt = mysqli_prepare($conn, "
        SELECT student_id, MAX(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS was_present
        FROM attendance
        WHERE attendance_date = ?
        GROUP BY student_id
    ");
    mysqli_stmt_bind_param($today_stmt, "s", $today);
    mysqli_stmt_execute($today_stmt);
    $today_result = mysqli_stmt_get_result($today_stmt);

    $today_present = 0;
    $today_absent = 0;
    while ($row = mysqli_fetch_assoc($today_result)) {
        if ((int)$row['was_present'] === 1) {
            $today_present++;
        } else {
            $today_absent++;
        }
    }
    $today_total_marked = $today_present + $today_absent;
    $today_not_marked = max(0, $total_students - $today_total_marked);

    $low_sql = "
        SELECT s.id
        FROM students s
        JOIN attendance a ON a.student_id = s.id
        GROUP BY s.id
        HAVING COUNT(a.id) > 0
           AND (SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100 < $threshold
    ";
    $low_result = mysqli_query($conn, $low_sql);
    $low_count = mysqli_num_rows($low_result);

    $low_snapshot_sql = "
        SELECT s.name, s.register_no, c.course_name,
               SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
               COUNT(a.id) AS total_count
        FROM students s
        JOIN courses c ON c.id = s.class_id
        JOIN attendance a ON a.student_id = s.id
        GROUP BY s.id, s.name, s.register_no, c.course_name
        HAVING COUNT(a.id) > 0
           AND (SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100 < $threshold
        ORDER BY (SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)) ASC
        LIMIT 5
    ";
    $low_snapshot = mysqli_query($conn, $low_snapshot_sql);

} else {

    // =====================================================
    // FACULTY DASHBOARD — scoped to their own subjects only
    // =====================================================
    $total_subjects = mysqli_fetch_assoc(
        (function () use ($conn, $faculty_id) {
            $s = mysqli_prepare($conn, "SELECT COUNT(*) c FROM subjects WHERE faculty_id = ?");
            mysqli_stmt_bind_param($s, "i", $faculty_id);
            mysqli_stmt_execute($s);
            return mysqli_stmt_get_result($s);
        })()
    )['c'];

    $total_students_stmt = mysqli_prepare($conn, "
        SELECT COUNT(DISTINCT st.id) c
        FROM students st
        JOIN subjects sub ON sub.course_id = st.class_id
        WHERE sub.faculty_id = ?
    ");
    mysqli_stmt_bind_param($total_students_stmt, "i", $faculty_id);
    mysqli_stmt_execute($total_students_stmt);
    $total_students = mysqli_stmt_get_result($total_students_stmt)->fetch_assoc()['c'];

    // Same distinct-student logic as the admin dashboard, but only counting
    // attendance from subjects this faculty member teaches.
    $today_stmt = mysqli_prepare($conn, "
        SELECT a.student_id, MAX(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS was_present
        FROM attendance a
        JOIN subjects sub ON sub.id = a.subject_id
        WHERE a.attendance_date = ? AND sub.faculty_id = ?
        GROUP BY a.student_id
    ");
    mysqli_stmt_bind_param($today_stmt, "si", $today, $faculty_id);
    mysqli_stmt_execute($today_stmt);
    $today_result = mysqli_stmt_get_result($today_stmt);

    $today_present = 0;
    $today_absent = 0;
    while ($row = mysqli_fetch_assoc($today_result)) {
        if ((int)$row['was_present'] === 1) {
            $today_present++;
        } else {
            $today_absent++;
        }
    }
    $today_total_marked = $today_present + $today_absent;
    $today_not_marked = max(0, $total_students - $today_total_marked);

    $low_stmt = mysqli_prepare($conn, "
        SELECT s.id
        FROM students s
        JOIN attendance a ON a.student_id = s.id
        JOIN subjects sub ON sub.id = a.subject_id
        WHERE sub.faculty_id = ?
        GROUP BY s.id
        HAVING COUNT(a.id) > 0
           AND (SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100 < $threshold
    ");
    mysqli_stmt_bind_param($low_stmt, "i", $faculty_id);
    mysqli_stmt_execute($low_stmt);
    $low_result = mysqli_stmt_get_result($low_stmt);
    $low_count = mysqli_num_rows($low_result);

    $low_snapshot_stmt = mysqli_prepare($conn, "
        SELECT s.name, s.register_no, c.course_name,
               SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
               COUNT(a.id) AS total_count
        FROM students s
        JOIN courses c ON c.id = s.class_id
        JOIN attendance a ON a.student_id = s.id
        JOIN subjects sub ON sub.id = a.subject_id
        WHERE sub.faculty_id = ?
        GROUP BY s.id, s.name, s.register_no, c.course_name
        HAVING COUNT(a.id) > 0
           AND (SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100 < $threshold
        ORDER BY (SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)) ASC
        LIMIT 5
    ");
    mysqli_stmt_bind_param($low_snapshot_stmt, "i", $faculty_id);
    mysqli_stmt_execute($low_snapshot_stmt);
    $low_snapshot = mysqli_stmt_get_result($low_snapshot_stmt);
}

require_once 'includes/header.php';
?>

<style>
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;align-items:stretch;}
.stat-grid .stat-box{display:flex;flex-direction:column;height:100%;}
</style>

<div class="stat-grid">

    <div class="stat-box">
        <span class="stat-number"><?php echo $total_students; ?></span>
        <span class="stat-label"><?php echo $is_admin ? 'Total Students' : 'Students You Teach'; ?></span>
    </div>

    <?php if ($is_admin): ?>
    <div class="stat-box">
        <span class="stat-number"><?php echo $total_faculty; ?></span>
        <span class="stat-label">Total Faculty</span>
    </div>

    <div class="stat-box">
        <span class="stat-number"><?php echo $total_courses; ?></span>
        <span class="stat-label">Total Courses / Classes</span>
    </div>
    <?php endif; ?>

    <div class="stat-box">
        <span class="stat-number"><?php echo $total_subjects; ?></span>
        <span class="stat-label"><?php echo $is_admin ? 'Total Subjects' : 'Your Assigned Subjects'; ?></span>
    </div>

    <div class="stat-box">
        <span class="stat-number"><?php echo $today_present; ?></span>
        <span class="stat-label">Present Today</span>
        <a href="reports/reports.php?type=daily&date=<?php echo urlencode($today); ?>" class="btn btn-secondary btn-sm" style="margin-top:10px;display:inline-block;">View More</a>
    </div>

    <div class="stat-box alert-box">
        <span class="stat-number"><?php echo $low_count; ?></span>
        <span class="stat-label">Students Below <?php echo $threshold; ?>% Attendance<?php echo $is_admin ? '' : ' (your subjects)'; ?></span>
    </div>

</div>


<div class="panel">

    <div class="panel-header">
        <h2>Students with Low Attendance<?php echo $is_admin ? '' : ' (Your Subjects)'; ?></h2>
        <a href="reports/low_attendance.php" class="btn btn-secondary btn-sm">View Full Report</a>
    </div>

    <?php if (mysqli_num_rows($low_snapshot) === 0): ?>
        <div class="empty-state">No students are currently below the <?php echo $threshold; ?>% threshold. 🎉</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Student</th><th>Register No.</th><th>Class</th><th>Attendance %</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($low_snapshot)):
                        $pct = round(($row['present_count'] / $row['total_count']) * 100, 1);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['register_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                        <td><span class="badge badge-absent"><?php echo $pct; ?>%</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>


<div class="panel">

    <div class="panel-header">
        <h2>Quick Actions</h2>
    </div>

    <div class="form-actions">
        <a href="attendance/mark.php" class="btn">Mark Today's Attendance</a>
        <?php if ($is_admin): ?>
        <a href="students/add.php" class="btn btn-secondary">Add Student</a>
        <?php endif; ?>
        <a href="reports/reports.php" class="btn btn-secondary">View Reports</a>
    </div>

</div>


<?php require_once 'includes/footer.php'; ?>