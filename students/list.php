<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'students';
$page_title = 'Students';

$sql = "
    SELECT s.*, c.course_name, c.section
    FROM students s
    LEFT JOIN courses c ON c.id = s.class_id
    ORDER BY s.name ASC
";
$result = mysqli_query($conn, $sql);

require_once $root . 'includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2>All Students</h2>
        <a href="add.php" class="btn">+ Add Student</a>
    </div>

    <div class="form-group" style="max-width:320px;">
        <input type="text" placeholder="Search by name, register no. or class..." data-table-search="students-table">
    </div>

    <div class="table-wrap">
    <table id="students-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Register No.</th>
                <th>Class</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="6" class="empty-state">No students added yet.</td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['register_no']); ?></td>
                    <td><?php echo htmlspecialchars(($row['course_name'] ?? '—') . ($row['section'] ? ' - ' . $row['section'] : '')); ?></td>
                    <td><?php echo htmlspecialchars($row['phone'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['email'] ?: '—'); ?></td>
                    <td class="actions-cell">
                        <a href="edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                        <a href="delete.php?id=<?php echo $row['id']; ?>" data-confirm="Delete this student? This will also remove their attendance records.">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once $root . 'includes/footer.php'; ?>
