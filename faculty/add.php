<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'faculty';
$page_title = 'Add Faculty';

$errors = [];
$form = ['name' => '', 'department' => '', 'phone' => '', 'email' => '', 'password' => ''];

// Name: letters and spaces only (also allows common Tamil-name marks like . and ')
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

    // ---- Password ----
    if ($form['password'] === '') {
        $errors[] = 'A login password is required so this faculty member can sign in.';
    } elseif (strlen($form['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (empty($errors) && $form['email'] !== '') {
        $check = mysqli_prepare($conn, "SELECT id FROM faculty WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $form['email']);
        mysqli_stmt_execute($check);
        if (mysqli_stmt_get_result($check)->fetch_assoc()) {
            $errors[] = 'That email is already used by another faculty member.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($form['password'], PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO faculty (name, department, phone, email, password) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssss", $form['name'], $form['department'], $form['phone'], $form['email'], $hash);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Faculty added successfully.';
            header("Location: list.php");
            exit;
        } else {
            $errors[] = 'Could not save faculty member. Please try again.';
        }
    }
}

require_once $root . 'includes/header.php';
?>

<div class="panel" style="max-width:640px;">
    <div class="panel-header">
        <h2>Add Faculty</h2>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="post" action="add.php" novalidate>
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
                <label for="password">Login Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Save Faculty</button>
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
    // Block non-letter/space keys in the name field as they're typed.
    var nameInput = document.getElementById('name');
    nameInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^A-Za-z ]/g, '');
    });

    // Block non-digit keys in the phone field and cap length at 10.
    var phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    });
})();
</script>

<?php require_once $root . 'includes/footer.php'; ?>