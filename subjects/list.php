<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'subjects';
$page_title = 'Subjects';

$sql = "
    SELECT sub.*, c.course_name, c.section, f.name AS faculty_name
    FROM subjects sub
    LEFT JOIN courses c ON c.id = sub.course_id
    LEFT JOIN faculty f ON f.id = sub.faculty_id
    ORDER BY sub.subject_name ASC
";
$result = mysqli_query($conn, $sql);

require_once $root . 'includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2>All Subjects</h2>
        <a href="add.php" class="btn">+ Add Subject</a>
    </div>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Subject Name</th>
                <th>Course / Class</th>
                <th>Faculty</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="4" class="empty-state">No subjects added yet.</td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars(($row['course_name'] ?? '—') . ($row['section'] ? ' - ' . $row['section'] : '')); ?></td>
                    <td><?php echo htmlspecialchars($row['faculty_name'] ?: 'Unassigned'); ?></td>
                    <td class="actions-cell">
                        <a href="edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                        <a href="delete.php?id=<?php echo $row['id']; ?>" data-confirm="Delete this subject? This also removes its attendance records.">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once $root . 'includes/footer.php'; ?>
