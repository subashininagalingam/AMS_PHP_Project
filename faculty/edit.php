<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'faculty';
$page_title = 'Edit Faculty';

$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM faculty WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$faculty = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$faculty) {
    $_SESSION['flash_error'] = 'Faculty member not found.';
    header("Location: list.php");
    exit;
}

$errors = [];
$form = $faculty;
$form['password'] = ''; // never re-populate the password field

// Name: letters and spaces only
const NAME_PATTERN  = '/^[A-Za-z ]+$/';
// Phone: exactly 10 digits
const PHONE_PATTERN = '/^[0-9]{10}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim($_POST['name'] ?? '');
    $form['department'] = trim($_POST['department'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['password'] = $_POST['password'] ?? '';

    // ---- Name ----
    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    } elseif (!preg_match(NAME_PATTERN, $form['name'])) {
        $errors[] = 'Name can only contain letters and spaces.';
    }

    // ---- Phone (optional field, but if given must be exactly 10 digits) ----
    if ($form['phone'] !== '' && !preg_match(PHONE_PATTERN, $form['phone'])) {
        $errors[] = 'Phone number must be exactly 10 digits, numbers only.';
    }

    // ---- Email ----
    if ($form['email'] === '') {
        $errors[] = 'Email is required — faculty log in with their email.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    // ---- Password (optional on edit — only validated if the admin is changing it) ----
    if ($form['password'] !== '' && strlen($form['password']) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    }

    if (empty($errors) && $form['email'] !== '') {
        // duplicate email check, excluding this faculty member's own row
        $check = mysqli_prepare($conn, "SELECT id FROM faculty WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($check, "si", $form['email'], $id);
        mysqli_stmt_execute($check);
        if (mysqli_stmt_get_result($check)->fetch_assoc()) {
            $errors[] = 'That email is already used by another faculty member.';
        }
    }

    if (empty($errors)) {
        if ($form['password'] !== '') {
            $hash = password_hash($form['password'], PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE faculty SET name=?, department=?, phone=?, email=?, password=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssssi", $form['name'], $form['department'], $form['phone'], $form['email'], $hash, $id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE faculty SET name=?, department=?, phone=?, email=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssssi", $form['name'], $form['department'], $form['phone'], $form['email'], $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Faculty updated successfully.';
            header("Location: list.php");
            exit;
        } else {
            // Log the real DB error for debugging; keep the user-facing message generic.
            error_log('Update faculty failed (id=' . $id . '): ' . mysqli_stmt_error($stmt));
            if (mysqli_errno($conn) === 1062) {
                $errors[] = 'That email is already used by another faculty member.';
            } else {
                $errors[] = 'Could not update faculty member. Please try again.';
            }
        }
    }
}

require_once $root . 'includes/header.php';
?>

<div class="panel" style="max-width:640px;">
    <div class="panel-header">
        <h2>Edit Faculty</h2>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="post" action="edit.php?id=<?php echo $id; ?>" novalidate>
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" required
                       pattern="[A-Za-z ]+" title="Letters and spaces only"
                       value="<?php echo htmlspecialchars($form['name']); ?>">
                <small class="field-hint">Letters and spaces only.</small>
            </div>
            <div class="form-group">
                <label for="department">Department</label>
                <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($form['department']); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone"
                       inputmode="numeric" pattern="[0-9]{10}" maxlength="10" title="10 digit number only"
                       value="<?php echo htmlspecialchars($form['phone']); ?>">
                <small class="field-hint">10 digit number only.</small>
            </div>
            <div class="form-group">
                <label for="email">Email (used as login) <span class="required">*</span></label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($form['email']); ?>">
            </div>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" minlength="6" placeholder="Leave blank to keep current password">
                <small class="field-hint">Leave blank to keep the current password.</small>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Update Faculty</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.required{color:#a33a2f}
.field-hint{display:block;margin-top:5px;font-size:12.5px;color:#6f6659}
.form-group input:invalid:not(:placeholder-shown){border-color:#a33a2f}
</style>

<script>
(function () {
    var nameInput = document.getElementById('name');
    nameInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^A-Za-z ]/g, '');
    });

    var phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    });
})();
</script>

<?php require_once $root . 'includes/footer.php'; ?>