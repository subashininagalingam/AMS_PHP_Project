<?php
$root = '../';
require_once $root . 'config/db.php';
require_once $root . 'includes/auth.php';
require_once $root . 'includes/admin_only.php';

$page = 'students';
$page_title = 'Add Student';

$errors = [];
$form = ['name' => '', 'register_no' => '', 'class_id' => '', 'phone' => '', 'email' => ''];

// Name: letters and spaces only
const NAME_PATTERN  = '/^[A-Za-z ]+$/';
// Phone: exactly 10 digits
const PHONE_PATTERN = '/^[0-9]{10}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim($_POST['name'] ?? '');
    $form['register_no'] = trim($_POST['register_no'] ?? '');
    $form['class_id'] = $_POST['class_id'] ?? '';
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');

    // ---- Name ----
    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    } elseif (!preg_match(NAME_PATTERN, $form['name'])) {
        $errors[] = 'Name can only contain letters and spaces.';
    }

    // ---- Register number ----
    if ($form['register_no'] === '') $errors[] = 'Register number is required.';

    // ---- Class ----
    if ($form['class_id'] === '') $errors[] = 'Please select a class.';

    // ---- Phone (optional, but if given must be exactly 10 digits) ----
    if ($form['phone'] !== '' && !preg_match(PHONE_PATTERN, $form['phone'])) {
        $errors[] = 'Phone number must be exactly 10 digits, numbers only.';
    }

    // ---- Email (optional, but if given must be valid) ----
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (empty($errors)) {
        // check duplicate register no
        $check = mysqli_prepare($conn, "SELECT id FROM students WHERE register_no = ?");
        mysqli_stmt_bind_param($check, "s", $form['register_no']);
        mysqli_stmt_execute($check);
        if (mysqli_stmt_get_result($check)->num_rows > 0) {
            $errors[] = 'A student with this register number already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO students (name, register_no, class_id, phone, email) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssiss", $form['name'], $form['register_no'], $form['class_id'], $form['phone'], $form['email']);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_success'] = 'Student added successfully.';
            header("Location: list.php");
            exit;
        } else {
            // Log the real DB error for debugging; keep the user-facing message generic.
            error_log('Add student insert failed: ' . mysqli_stmt_error($stmt));
            if (mysqli_errno($conn) === 1062) {
                $errors[] = 'That phone number or email is already used by another student.';
            } else {
                $errors[] = 'Could not save student. Please try again.';
            }
        }
    }
}

$courses = mysqli_query($conn, "SELECT id, course_name, section FROM courses ORDER BY course_name");

require_once $root . 'includes/header.php';
?>

<div class="panel" style="max-width:640px;">
    <div class="panel-header">
        <h2>Add Student</h2>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <?php if (mysqli_num_rows($courses) === 0): ?>
        <div class="alert alert-error">No classes exist yet. <a href="<?php echo $root; ?>courses/add.php">Add a course/class</a> first.</div>
        <?php mysqli_data_seek($courses, 0); ?>
    <?php endif; ?>

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
                <label for="register_no">Register Number <span class="required">*</span></label>
                <input type="text" id="register_no" name="register_no" required value="<?php echo htmlspecialchars($form['register_no']); ?>">
            </div>
            <div class="form-group">
                <label for="class_id">Class / Course <span class="required">*</span></label>
                <select id="class_id" name="class_id" required>
                    <option value="">-- Select class --</option>
                    <?php while ($c = mysqli_fetch_assoc($courses)): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($form['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone"
                       inputmode="numeric" pattern="[0-9]{10}" maxlength="10" title="10 digit number only"
                       value="<?php echo htmlspecialchars($form['phone']); ?>">
                <small class="field-hint">10 digit number only.</small>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form['email']); ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Save Student</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>


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