<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';

$page = 'reports';

$is_admin = ($_SESSION['user_role'] === 'admin');
$faculty_id = $_SESSION['faculty_id'] ?? 0;

$type = $_GET['type'] ?? 'daily';
// Class-wise and Monthly reports summarise a student across ALL subjects,
// not just one faculty's own subjects, so they stay Admin-only.
$valid_types = $is_admin
    ? ['daily', 'student_wise', 'subject_wise', 'class_wise', 'monthly']
    : ['daily', 'student_wise', 'subject_wise'];
if (!in_array($type, $valid_types, true)) $type = 'daily';

$titles = [
    'daily' => 'Daily Attendance Report',
    'student_wise' => 'Student-wise Attendance Report',
    'subject_wise' => 'Subject-wise Attendance Report',
    'class_wise' => 'Class-wise Attendance Report',
    'monthly' => 'Monthly Attendance Report',
];
$page_title = $titles[$type];

// ---------------------------------------------------------------
// Common lookups used by several filters.
// Same role-based pattern as low_attendance.php: Admin sees every
// class; Faculty only see classes where they actually teach a
// subject, so the "Class" dropdown never offers a class they have
// no data for.
// ---------------------------------------------------------------
if ($is_admin) {
    $courses = mysqli_query($conn, "SELECT id, course_name, section FROM courses ORDER BY course_name");
    $courses_list = mysqli_fetch_all($courses, MYSQLI_ASSOC);
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
    $courses_list = mysqli_fetch_all(mysqli_stmt_get_result($courses_stmt), MYSQLI_ASSOC);
}

if ($is_admin) {
    $subjects_all = mysqli_query($conn, "
        SELECT sub.id, sub.subject_name, c.course_name, c.section
        FROM subjects sub LEFT JOIN courses c ON c.id = sub.course_id
        ORDER BY sub.subject_name
    ");
    $subjects_list = mysqli_fetch_all($subjects_all, MYSQLI_ASSOC);

    $students_all = mysqli_query($conn, "SELECT id, name, register_no FROM students ORDER BY name");
    $students_list = mysqli_fetch_all($students_all, MYSQLI_ASSOC);
} else {
    // Faculty only ever see their own subjects, and only students in classes
    // where they teach at least one subject.
    $subjects_stmt = mysqli_prepare($conn, "
        SELECT sub.id, sub.subject_name, c.course_name, c.section
        FROM subjects sub LEFT JOIN courses c ON c.id = sub.course_id
        WHERE sub.faculty_id = ?
        ORDER BY sub.subject_name
    ");
    mysqli_stmt_bind_param($subjects_stmt, "i", $faculty_id);
    mysqli_stmt_execute($subjects_stmt);
    $subjects_list = mysqli_fetch_all(mysqli_stmt_get_result($subjects_stmt), MYSQLI_ASSOC);

    $students_stmt = mysqli_prepare($conn, "
        SELECT DISTINCT st.id, st.name, st.register_no
        FROM students st
        JOIN subjects sub ON sub.course_id = st.class_id
        WHERE sub.faculty_id = ?
        ORDER BY st.name
    ");
    mysqli_stmt_bind_param($students_stmt, "i", $faculty_id);
    mysqli_stmt_execute($students_stmt);
    $students_list = mysqli_fetch_all(mysqli_stmt_get_result($students_stmt), MYSQLI_ASSOC);
}

require_once $root . 'includes/header.php';
?>

<div class="tab-row">
    <a href="reports.php?type=daily" class="<?php echo $type === 'daily' ? 'active' : ''; ?>">Daily</a>
    <a href="reports.php?type=student_wise" class="<?php echo $type === 'student_wise' ? 'active' : ''; ?>">Student-wise</a>
    <a href="reports.php?type=subject_wise" class="<?php echo $type === 'subject_wise' ? 'active' : ''; ?>">Subject-wise</a>
    <?php if ($is_admin): ?>
    <a href="reports.php?type=class_wise" class="<?php echo $type === 'class_wise' ? 'active' : ''; ?>">Class-wise</a>
    <a href="reports.php?type=monthly" class="<?php echo $type === 'monthly' ? 'active' : ''; ?>">Monthly</a>
    <?php endif; ?>
    <a href="low_attendance.php">Low Attendance</a>
</div>

<?php
// =========================================================
// DAILY REPORT
// =========================================================
if ($type === 'daily'):
    $date = $_GET['date'] ?? date('Y-m-d');
    $filter_class = $_GET['class_id'] ?? '';

    $sql = "
        SELECT s.name, s.register_no, c.course_name, c.section, sub.subject_name, a.status
        FROM attendance a
        JOIN students s ON s.id = a.student_id
        JOIN subjects sub ON sub.id = a.subject_id
        JOIN courses c ON c.id = s.class_id
        WHERE a.attendance_date = ?
    ";
    $params = [$date];
    $types = "s";
    if ($filter_class !== '') {
        $sql .= " AND s.class_id = ?";
        $params[] = $filter_class;
        $types .= "i";
    }
    if (!$is_admin) {
        $sql .= " AND sub.faculty_id = ?";
        $params[] = $faculty_id;
        $types .= "i";
    }
    $sql .= " ORDER BY c.course_name, sub.subject_name, s.name";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt);
?>
    <div class="panel">
        <form method="get" action="reports.php" class="filter-row">
            <input type="hidden" name="type" value="daily">
            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="class_id">Class (optional)</label>
                <select id="class_id" name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filter_class == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="btn">Filter</button></div>
        </form>
    </div>

    <div class="panel">
        <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Register No.</th><th>Class</th><th>Subject</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($rows) === 0): ?>
                <tr><td colspan="5" class="empty-state">No attendance records found for this date.</td></tr>
            <?php else: while ($r = mysqli_fetch_assoc($rows)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                    <td><?php echo htmlspecialchars($r['register_no']); ?></td>
                    <td><?php echo htmlspecialchars($r['course_name'] . ($r['section'] ? ' - ' . $r['section'] : '')); ?></td>
                    <td><?php echo htmlspecialchars($r['subject_name']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo $r['status']; ?></span></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php
// =========================================================
// STUDENT-WISE REPORT
// =========================================================
elseif ($type === 'student_wise'):
    $student_id = $_GET['student_id'] ?? '';
    $summary = null; $details = null; $student = null;

    if ($student_id !== '') {
        // Faculty may only pull up students who are in one of their classes
        $allowed_student = true;
        if (!$is_admin) {
            $allowed_student = false;
            foreach ($students_list as $s) {
                if ($s['id'] == $student_id) { $allowed_student = true; break; }
            }
        }

        if ($allowed_student) {
            $stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $student_id);
            mysqli_stmt_execute($stmt);
            $student = mysqli_stmt_get_result($stmt)->fetch_assoc();

            $summary_sql = "
                SELECT sub.subject_name,
                       SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
                       SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) AS absent_count,
                       COUNT(*) AS total
                FROM attendance a
                JOIN subjects sub ON sub.id = a.subject_id
                WHERE a.student_id = ?
            ";
            $details_sql = "
                SELECT a.attendance_date, sub.subject_name, a.status
                FROM attendance a JOIN subjects sub ON sub.id = a.subject_id
                WHERE a.student_id = ?
            ";
            if (!$is_admin) {
                $summary_sql .= " AND sub.faculty_id = " . (int)$faculty_id;
                $details_sql .= " AND sub.faculty_id = " . (int)$faculty_id;
            }
            $summary_sql .= " GROUP BY sub.id ORDER BY sub.subject_name";
            $details_sql .= " ORDER BY a.attendance_date DESC";

            $stmt2 = mysqli_prepare($conn, $summary_sql);
            mysqli_stmt_bind_param($stmt2, "i", $student_id);
            mysqli_stmt_execute($stmt2);
            $summary = mysqli_stmt_get_result($stmt2);

            $stmt3 = mysqli_prepare($conn, $details_sql);
            mysqli_stmt_bind_param($stmt3, "i", $student_id);
            mysqli_stmt_execute($stmt3);
            $details = mysqli_stmt_get_result($stmt3);
        } else {
            $student_id = '';
        }
    }
?>
    <div class="panel">
        <form method="get" action="reports.php" class="filter-row">
            <input type="hidden" name="type" value="student_wise">
            <div class="form-group">
                <label for="student_id">Student</label>
                <select id="student_id" name="student_id" required>
                    <option value="">-- Select student --</option>
                    <?php foreach ($students_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $student_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name'] . ' (' . $s['register_no'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="btn">View</button></div>
        </form>
    </div>

    <?php if ($student): ?>
    <div class="panel">
        <div class="panel-header"><h2>Subject-wise Summary — <?php echo htmlspecialchars($student['name']); ?></h2></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Subject</th><th>Present</th><th>Absent</th><th>Total</th><th>Percentage</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($summary) === 0): ?>
                <tr><td colspan="5" class="empty-state">No attendance recorded yet.</td></tr>
            <?php else: while ($row = mysqli_fetch_assoc($summary)):
                $pct = $row['total'] > 0 ? round(($row['present_count'] / $row['total']) * 100, 1) : 0;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                    <td><?php echo $row['present_count']; ?></td>
                    <td><?php echo $row['absent_count']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><span class="badge <?php echo $pct < 75 ? 'badge-absent' : 'badge-present'; ?>"><?php echo $pct; ?>%</span></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Day-by-Day Record</h2></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Subject</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($details) === 0): ?>
                <tr><td colspan="3" class="empty-state">No records found.</td></tr>
            <?php else: while ($row = mysqli_fetch_assoc($details)): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['attendance_date'])); ?></td>
                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
<?php
// =========================================================
// SUBJECT-WISE REPORT
// =========================================================
elseif ($type === 'subject_wise'):
    $subject_id = $_GET['subject_id'] ?? '';
    $rows = null; $subject_name = '';

    if ($subject_id !== '') {
        // subjects_list is already scoped to the faculty's own subjects (or
        // all subjects for admin), so a match here confirms ownership.
        $owns_subject = false;
        foreach ($subjects_list as $s) {
            if ($s['id'] == $subject_id) { $subject_name = $s['subject_name']; $owns_subject = true; }
        }
        if (!$owns_subject) $subject_id = '';
    }

    if ($subject_id !== '') {
        $stmt = mysqli_prepare($conn, "
            SELECT st.name, st.register_no,
                   SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
                   COUNT(a.id) AS total
            FROM students st
            JOIN attendance a ON a.student_id = st.id AND a.subject_id = ?
            GROUP BY st.id
            ORDER BY st.name
        ");
        mysqli_stmt_bind_param($stmt, "i", $subject_id);
        mysqli_stmt_execute($stmt);
        $rows = mysqli_stmt_get_result($stmt);
    }
?>
    <div class="panel">
        <form method="get" action="reports.php" class="filter-row">
            <input type="hidden" name="type" value="subject_wise">
            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">-- Select subject --</option>
                    <?php foreach ($subjects_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $subject_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['subject_name'] . ' (' . $s['course_name'] . ($s['section'] ? ' - ' . $s['section'] : '') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="btn">View</button></div>
        </form>
    </div>

    <?php if ($rows !== null): ?>
    <div class="panel">
        <div class="panel-header"><h2><?php echo htmlspecialchars($subject_name); ?> — Attendance Summary</h2></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Register No.</th><th>Present</th><th>Total Classes</th><th>Percentage</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($rows) === 0): ?>
                <tr><td colspan="5" class="empty-state">No attendance recorded for this subject yet.</td></tr>
            <?php else: while ($row = mysqli_fetch_assoc($rows)):
                $pct = $row['total'] > 0 ? round(($row['present_count'] / $row['total']) * 100, 1) : 0;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['register_no']); ?></td>
                    <td><?php echo $row['present_count']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><span class="badge <?php echo $pct < 75 ? 'badge-absent' : 'badge-present'; ?>"><?php echo $pct; ?>%</span></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
<?php
// =========================================================
// CLASS-WISE REPORT
// =========================================================
elseif ($type === 'class_wise'):
    $class_id = $_GET['class_id'] ?? '';
    $rows = null; $class_name = '';

    if ($class_id !== '') {
        foreach ($courses_list as $c) {
            if ($c['id'] == $class_id) $class_name = $c['course_name'] . ($c['section'] ? ' - ' . $c['section'] : '');
        }
        $stmt = mysqli_prepare($conn, "
            SELECT st.name, st.register_no,
                   SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
                   COUNT(a.id) AS total
            FROM students st
            LEFT JOIN attendance a ON a.student_id = st.id
            WHERE st.class_id = ?
            GROUP BY st.id
            ORDER BY st.name
        ");
        mysqli_stmt_bind_param($stmt, "i", $class_id);
        mysqli_stmt_execute($stmt);
        $rows = mysqli_stmt_get_result($stmt);
    }
?>
    <div class="panel">
        <form method="get" action="reports.php" class="filter-row">
            <input type="hidden" name="type" value="class_wise">
            <div class="form-group">
                <label for="class_id">Class</label>
                <select id="class_id" name="class_id" required>
                    <option value="">-- Select class --</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="btn">View</button></div>
        </form>
    </div>

    <?php if ($rows !== null): ?>
    <div class="panel">
        <div class="panel-header"><h2><?php echo htmlspecialchars($class_name); ?> — Overall Attendance</h2></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Register No.</th><th>Present</th><th>Total Marked</th><th>Percentage</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($rows) === 0): ?>
                <tr><td colspan="5" class="empty-state">No students in this class yet.</td></tr>
            <?php else: while ($row = mysqli_fetch_assoc($rows)):
                $pct = $row['total'] > 0 ? round(($row['present_count'] / $row['total']) * 100, 1) : null;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['register_no']); ?></td>
                    <td><?php echo $row['present_count']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php if ($pct === null): ?><span class="text-muted">No data</span><?php else: ?>
                        <span class="badge <?php echo $pct < 75 ? 'badge-absent' : 'badge-present'; ?>"><?php echo $pct; ?>%</span>
                    <?php endif; ?></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
<?php
// =========================================================
// MONTHLY REPORT
// =========================================================
elseif ($type === 'monthly'):
    $month = $_GET['month'] ?? date('Y-m');
    $class_id = $_GET['class_id'] ?? '';

    $sql = "
        SELECT st.name, st.register_no, c.course_name, c.section,
               SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
               COUNT(a.id) AS total
        FROM students st
        JOIN courses c ON c.id = st.class_id
        LEFT JOIN attendance a ON a.student_id = st.id AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
        WHERE 1=1
    ";
    $params = [$month]; $types = "s";
    if ($class_id !== '') {
        $sql .= " AND st.class_id = ?";
        $params[] = $class_id; $types .= "i";
    }
    $sql .= " GROUP BY st.id ORDER BY c.course_name, st.name";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt);
?>
    <div class="panel">
        <form method="get" action="reports.php" class="filter-row">
            <input type="hidden" name="type" value="monthly">
            <div class="form-group">
                <label for="month">Month</label>
                <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($month); ?>" max="<?php echo date('Y-m'); ?>">
            </div>
            <div class="form-group">
                <label for="class_id">Class (optional)</label>
                <select id="class_id" name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="btn">Filter</button></div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-header"><h2><?php echo date('F Y', strtotime($month . '-01')); ?> — Monthly Summary</h2></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Register No.</th><th>Class</th><th>Present</th><th>Total Marked</th><th>Percentage</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($rows) === 0): ?>
                <tr><td colspan="6" class="empty-state">No students found.</td></tr>
            <?php else: while ($row = mysqli_fetch_assoc($rows)):
                $pct = $row['total'] > 0 ? round(($row['present_count'] / $row['total']) * 100, 1) : null;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['register_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['course_name'] . ($row['section'] ? ' - ' . $row['section'] : '')); ?></td>
                    <td><?php echo $row['present_count']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php if ($pct === null): ?><span class="text-muted">No data</span><?php else: ?>
                        <span class="badge <?php echo $pct < 75 ? 'badge-absent' : 'badge-present'; ?>"><?php echo $pct; ?>%</span>
                    <?php endif; ?></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once $root . 'includes/footer.php'; ?>