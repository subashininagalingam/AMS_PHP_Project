<?php
session_start();
require_once 'config/db.php';

// If already logged in, go straight to dashboard
if (isset($_SESSION['user_role'])) {
    header("Location: dashboard.php");
    exit;
}

// CSRF token for this login form
if (empty($_SESSION['login_token'])) {
    $_SESSION['login_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token    = $_POST['login_token'] ?? '';

    if (!hash_equals($_SESSION['login_token'], $token)) {
        $error = 'Your session expired. Reload the page and sign in again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username or email and your password.';
    } else {
        $authenticated = false;

        // ---- 1. Try Admin login (by username) ----
        $stmt = mysqli_prepare($conn, "SELECT id, username, password FROM admin WHERE username = ?");
        if (!$stmt) {
            error_log("Login prepare failed: " . mysqli_error($conn));
            die("Sign-in is unavailable right now. Try again later.");
        }
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_name'] = $admin['username'];
            // Kept for any legacy reference elsewhere in the app
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $authenticated = true;
        }

        // ---- 2. Try Faculty login (by email), only if Admin login failed ----
        if (!$authenticated) {
            $stmt2 = mysqli_prepare($conn, "SELECT id, name, email, password FROM faculty WHERE email = ?");
            if (!$stmt2) {
                error_log("Login prepare failed: " . mysqli_error($conn));
                die("Sign-in is unavailable right now. Try again later.");
            }
            mysqli_stmt_bind_param($stmt2, "s", $username);
            mysqli_stmt_execute($stmt2);
            $result2 = mysqli_stmt_get_result($stmt2);
            $faculty = mysqli_fetch_assoc($result2);

            if ($faculty && !empty($faculty['password']) && password_verify($password, $faculty['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_role'] = 'faculty';
                $_SESSION['user_id'] = $faculty['id'];
                $_SESSION['user_name'] = $faculty['name'];
                $_SESSION['faculty_id'] = $faculty['id'];
                $authenticated = true;
            }
        }

        if ($authenticated) {
            unset($_SESSION['login_token']);
            header("Location: dashboard.php");
            exit;
        }

        // Deliberately generic: never reveal whether the account exists
        // or whether a password has been set for it.
        $error = 'That sign-in didn\'t work. Check your details and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Sign in | Attendance Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&display=swap" rel="stylesheet">
<style>
:root{
  /* palette taken from the dashboard */
  --forest:#26342b;        /* sidebar green */
  --forest-soft:#314337;
  --cream:#f7f4ee;         /* page background */
  --paper:#ffffff;         /* cards */
  --clay:#a1522a;          /* AMS badge / accent */
  --clay-soft:#c6813f;
  --text:#2a2622;
  --muted:#6f6659;
  --line:#e2dccf;
  --alert:#7d2b24;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:"IBM Plex Sans",system-ui,-apple-system,"Segoe UI",sans-serif;
  color:var(--text);
  background:var(--cream);
  -webkit-font-smoothing:antialiased;
}

.page{
  min-height:100dvh;
  display:grid;
  grid-template-columns:1.1fr 1fr;
}

/* ---------- Left: the register ---------- */
.register{
  position:relative;
  background:var(--forest);
  background-image:repeating-linear-gradient(to bottom,transparent 0 47px,rgba(255,255,255,.07) 47px 48px);
  color:#fff;
  padding:56px 56px 48px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  overflow:hidden;
}
.register::after{
  content:"";
  position:absolute;
  inset:0 auto 0 0;
  width:1px;
  background:rgba(255,255,255,.12);
  margin-left:88px;
}
.mark-brand{
  position:relative;z-index:1;
  display:flex;align-items:center;gap:14px;
}
.badge{
  width:44px;height:44px;flex:none;
  display:grid;place-items:center;
  background:var(--clay);
  border-radius:6px;
  font-size:13px;font-weight:600;letter-spacing:.04em;
}
.mark-brand span{
  font-family:"Source Serif 4",Georgia,serif;
  font-size:27px;line-height:1.2;
}
.mark-brand small{
  display:block;
  font-family:"IBM Plex Sans",sans-serif;
  font-size:13px;color:rgba(255,255,255,.55);
}
.register h2{
  position:relative;z-index:1;
  font-family:"Source Serif 4",Georgia,serif;
  font-weight:400;
  font-size:clamp(28px,3.2vw,42px);
  line-height:1.2;
  margin:40px 0 14px;
  max-width:15ch;
}
.register p{
  position:relative;z-index:1;
  margin:0;max-width:44ch;
  color:rgba(255,255,255,.6);
  font-size:15px;line-height:1.65;
}

/* attendance grid motif */
.grid{
  position:relative;z-index:1;
  display:grid;
  grid-template-columns:repeat(10,1fr);
  gap:10px;max-width:420px;margin-top:44px;
}
.cell{
  aspect-ratio:1;
  border-radius:3px;
  border:1px solid rgba(255,255,255,.2);
  opacity:0;
  animation:ink .5s ease forwards;
}
.cell.present{background:var(--clay);border-color:var(--clay)}
@keyframes ink{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .cell{animation:none;opacity:1} }
.legend{
  position:relative;z-index:1;
  display:flex;gap:22px;margin-top:22px;
  font-size:12.5px;color:rgba(255,255,255,.55);
}
.legend i{
  display:inline-block;width:10px;height:10px;border-radius:2px;
  margin-right:7px;vertical-align:-1px;
}
.legend .p{background:var(--clay)}
.legend .a{border:1px solid rgba(255,255,255,.32)}

/* ---------- Right: sign-in ---------- */
.signin{
  background:var(--cream);
  display:flex;align-items:center;justify-content:center;
  padding:48px 32px;
}
.card{
  width:100%;max-width:400px;
  background:var(--paper);
  border:1px solid var(--line);
  border-left:3px solid var(--clay);
  border-radius:8px;
  padding:36px 34px 30px;
}
.card h1{
  font-family:"Source Serif 4",Georgia,serif;
  font-weight:600;font-size:27px;margin:0 0 6px;
}
.lede{margin:0 0 28px;color:var(--muted);font-size:14.5px;line-height:1.6}

.field{margin-bottom:18px}
.field label{
  display:block;font-size:13.5px;font-weight:500;
  margin-bottom:7px;color:var(--text);
}
.field input{
  width:100%;font:inherit;font-size:15px;
  padding:12px 13px;
  border:1px solid var(--line);
  border-radius:6px;
  background:#fdfcfa;color:var(--text);
  transition:border-color .15s ease,box-shadow .15s ease;
}
.field input::placeholder{color:#a79d8e}
.field input:focus{
  outline:none;
  border-color:var(--clay);
  box-shadow:0 0 0 3px rgba(161,82,42,.14);
  background:#fff;
}
/* hide the browser's built-in reveal / clear icons */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear{display:none}
input[type="password"]::-webkit-credentials-auto-fill-button{display:none!important;visibility:hidden}

.pw{position:relative;display:block}
.pw input{padding-right:66px}
.pw button{
  position:absolute;right:6px;top:50%;transform:translateY(-50%);
  border:0;background:none;color:var(--muted);
  font:inherit;font-size:13px;line-height:1;
  padding:7px 9px;border-radius:5px;cursor:pointer;
}
.pw button:hover{color:var(--clay)}
.pw button:focus-visible{outline:2px solid var(--clay);outline-offset:1px}

.btn{
  width:100%;margin-top:8px;
  font:inherit;font-size:15px;font-weight:500;
  padding:13px 16px;color:#fff;background:var(--forest);
  border:0;border-radius:6px;cursor:pointer;
  transition:background .15s ease;
}
.btn:hover{background:var(--forest-soft)}
.btn:focus-visible{outline:2px solid var(--clay);outline-offset:2px}

.alert{
  display:flex;gap:10px;
  border:1px solid rgba(125,43,36,.25);
  background:#fbf2f0;color:var(--alert);
  border-radius:6px;padding:11px 13px;
  font-size:13.5px;line-height:1.5;margin-bottom:22px;
}

.foot{
  margin-top:24px;padding-top:18px;
  border-top:1px solid var(--line);
  font-size:13px;color:var(--muted);line-height:1.6;
}

@media (max-width:900px){
  .page{grid-template-columns:1fr}
  .register{padding:28px 24px 26px}
  .register::after{display:none}
  .register h2{margin:22px 0 10px;font-size:26px}
  .grid,.legend{display:none}
  .signin{padding:30px 22px 54px;align-items:flex-start}
  .card{padding:30px 24px 26px}
}
</style>
</head>
<body>
<div class="page">

  <aside class="register">
    <div class="mark-brand">
      <div class="badge">AMS</div>
      <span>Attendance<small>Management System</small></span>
    </div>
    <div>
      <h2>A clear record of every class.</h2>
      <p>Mark the roll, correct it when something changes, and pull the register for any date or subject.</p>
      <div class="grid" aria-hidden="true">
        <?php
          // Decorative roll-call pattern - no real records are shown here.
          $pattern = '1111011111101111111011111110111111011111101111111011';
          for ($i = 0; $i < 50; $i++) {
              $c = $pattern[$i] === '1' ? 'cell present' : 'cell';
              echo '<span class="' . $c . '" style="animation-delay:' . (40 * $i) . 'ms"></span>';
          }
        ?>
      </div>
      <div class="legend">
        <span><i class="p"></i>Present</span>
        <span><i class="a"></i>Absent</span>
      </div>
    </div>
    <p style="font-size:12.5px;color:rgba(255,255,255,.4)">Authorised staff only.</p>
  </aside>

  <main class="signin">
    <div class="card">
      <h1>Sign in</h1>
      <p class="lede">Administrators sign in with a username. Faculty sign in with the email address on file.</p>

      <?php if ($error): ?>
        <div class="alert" role="alert"><span aria-hidden="true">!</span><span><?php echo htmlspecialchars($error); ?></span></div>
      <?php endif; ?>

      <form method="post" action="login.php" novalidate autocomplete="on">
        <input type="hidden" name="login_token" value="<?php echo htmlspecialchars($_SESSION['login_token']); ?>">

        <div class="field">
          <label for="username">Username or email</label>
          <input type="text" id="username" name="username" required autofocus
                 autocapitalize="none" autocomplete="username" spellcheck="false"
                 placeholder="you@college.edu">
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="pw">
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button type="button" id="toggle-pw" aria-controls="password" aria-pressed="false">Show</button>
          </div>
        </div>

        <button type="submit" class="btn">Sign in</button>
      </form>

      <p class="foot">No password yet, or forgotten it? Ask your administrator to set one for your account.</p>
    </div>
  </main>

</div>

<script>
(function () {
  var btn = document.getElementById('toggle-pw');
  var pw  = document.getElementById('password');
  btn.addEventListener('click', function () {
    var shown = pw.type === 'text';
    pw.type = shown ? 'password' : 'text';
    btn.textContent = shown ? 'Show' : 'Hide';
    btn.setAttribute('aria-pressed', String(!shown));
    pw.focus();
  });
})();
</script>
</body>
</html>