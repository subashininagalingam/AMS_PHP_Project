<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';

$page = 'low_attendance';
$page_title = 'Low Attendance Report';

$is_admin = ($_SESSION['user_role'] === 'admin');
$faculty_id = $_SESSION['faculty_id'] ?? 0;

$threshold = isset($_GET['threshold']) && $_GET['threshold'] !== '' ? (float)$_GET['threshold'] : 75;
$class_id = $_GET['class_id'] ?? '';

// Faculty only get a class filter for classes where they actually teach a subject
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

$sql = "
    SELECT s.id, s.name, s.register_no, c.course_name, c.section,
           SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present_count,
           COUNT(a.id) AS total
    FROM students s
    JOIN courses c ON c.id = s.class_id
    JOIN attendance a ON a.student_id = s.id
";

if (!$is_admin) {
    $sql .= " JOIN subjects sub ON sub.id = a.subject_id ";
}

$params = [];
$types = "";
$where = [];

if (!$is_admin) {
    $where[] = "sub.faculty_id = ?";
    $params[] = $faculty_id;
    $types .= "i";
}

if ($class_id !== '') {
    $where[] = "s.class_id = ?";
    $params[] = $class_id;
    $types .= "i";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
    GROUP BY s.id, s.name, s.register_no, c.course_name, c.section
    HAVING COUNT(a.id) > 0
       AND (
           SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)
       ) * 100 < ?
    ORDER BY (
        SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) / COUNT(a.id)
    ) ASC
";

$params[] = $threshold;
$types .= "d";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("SQL Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

require_once $root . 'includes/header.php';
?>

<div class="tab-row">
    <a href="reports.php?type=daily">Daily</a>
    <a href="reports.php?type=student_wise">Student-wise</a>
    <a href="reports.php?type=subject_wise">Subject-wise</a>
    <?php if ($is_admin): ?>
    <a href="reports.php?type=class_wise">Class-wise</a>
    <a href="reports.php?type=monthly">Monthly</a>
    <?php endif; ?>
    <a href="low_attendance.php" class="active">Low Attendance</a>
</div>

<div class="panel">
    <form method="get" action="low_attendance.php" class="filter-row">
        <div class="form-group">
            <label for="threshold">Threshold (%)</label>
            <input type="number" id="threshold" name="threshold" min="1" max="100" step="1" value="<?php echo htmlspecialchars($threshold); ?>">
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
        <div class="form-group"><button type="submit" class="btn">Apply</button></div>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Students Below <?php echo htmlspecialchars($threshold); ?>% Attendance<?php echo $is_admin ? '' : ' (Your Subjects)'; ?> (<?php echo mysqli_num_rows($rows); ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($rows) === 0): ?>
        <div class="empty-state">No students fall below this threshold. 🎉</div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Student</th><th>Register No.</th><th>Class</th><th>Present</th><th>Total Marked</th><th>Percentage</th></tr></thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($rows)):
            $pct = round(($row['present_count'] / $row['total']) * 100, 1);
        ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['register_no']); ?></td>
                <td><?php echo htmlspecialchars($row['course_name'] . ($row['section'] ? ' - ' . $row['section'] : '')); ?></td>
                <td><?php echo $row['present_count']; ?></td>
                <td><?php echo $row['total']; ?></td>
                <td>
                    <span class="badge badge-absent"><?php echo $pct; ?>%</span>
                    <div class="progress-bar" style="margin-top:6px;max-width:120px;">
                        <div class="progress-bar-fill low" style="width:<?php echo $pct; ?>%;"></div>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once $root . 'includes/footer.php'; ?>
