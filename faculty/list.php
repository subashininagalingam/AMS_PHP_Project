<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'faculty';
$page_title = 'Faculty';

$sql = "
    SELECT f.*, GROUP_CONCAT(s.subject_name SEPARATOR ', ') AS subjects
    FROM faculty f
    LEFT JOIN subjects s ON s.faculty_id = f.id
    GROUP BY f.id
    ORDER BY f.name ASC
";
$result = mysqli_query($conn, $sql);

require_once $root . 'includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2>All Faculty</h2>
        <a href="add.php" class="btn">+ Add Faculty</a>
    </div>

    <div class="form-group" style="max-width:320px;">
        <input type="text" placeholder="Search by name or department..." data-table-search="faculty-table">
    </div>

    <div class="table-wrap">
    <table id="faculty-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Department</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Assigned Subjects</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="6" class="empty-state">No faculty added yet.</td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['department'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['phone'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['email'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['subjects'] ?: '—'); ?></td>
                    <td class="actions-cell">
                        <a href="edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                        <a href="delete.php?id=<?php echo $row['id']; ?>" data-confirm="Delete this faculty member?">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once $root . 'includes/footer.php'; ?>
