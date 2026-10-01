<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'courses';
$page_title = 'Courses / Classes';

$sql = "
    SELECT c.*,
        (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count,
        (SELECT COUNT(*) FROM subjects sub WHERE sub.course_id = c.id) AS subject_count
    FROM courses c
    ORDER BY c.course_name ASC
";
$result = mysqli_query($conn, $sql);

require_once $root . 'includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2>All Courses / Classes</h2>
        <a href="add.php" class="btn">+ Add Course / Class</a>
    </div>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Semester / Year</th>
                <th>Section</th>
                <th>Students</th>
                <th>Subjects</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="6" class="empty-state">No courses/classes added yet.</td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['semester_year']); ?></td>
                    <td><?php echo htmlspecialchars($row['section'] ?: '—'); ?></td>
                    <td><?php echo $row['student_count']; ?></td>
                    <td><?php echo $row['subject_count']; ?></td>
                    <td class="actions-cell">
                        <a href="view.php?id=<?php echo $row['id']; ?>">View</a>
                        <a href="edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                        <a href="delete.php?id=<?php echo $row['id']; ?>" data-confirm="Delete this course/class? This also removes its students and subjects.">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once $root . 'includes/footer.php'; ?>
