<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/auth.php';
if (user()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter your full name and a valid email.';
    elseif ($p1 !== $p2) $error = 'Passwords do not match.';
    elseif ($msg = valid_password($p1)) $error = $msg;
    elseif (q('SELECT 1 FROM users WHERE email=?', [$email])->fetch()) $error = 'An account with that email already exists.';
    else {
        // New accounts stay "Pending" until an Admin Ops user activates them in Staff & Roles.
        q('INSERT INTO users (name,email,password_hash,status) VALUES (?,?,?,"Pending")', [$name, $email, password_hash($p1, PASSWORD_DEFAULT)]);
        audit('New staff registration', $email);
        flash('Account created. An administrator must approve it before you can log in.');
        redirect('login.php');
    }
}
auth_start('Sign in');
?>
<h2>Sign in to BloodDonation</h2>
<p class="sub">Clinical &amp; blood bank administrative operations console</p>
<?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <label>Full Name <input type="text" name="name" placeholder="e.g. Nurse Thorne" value="<?= e($_POST['name'] ?? '') ?>" required></label>
  <label>Email <input type="email" name="email" placeholder="e.g. nurse.thorne@hemocare.org" value="<?= e($_POST['email'] ?? '') ?>" required></label>
  <label>Password <input type="password" name="password" placeholder="••••••••••••••" required></label>
  <label>Confirm Password <input type="password" name="confirm" placeholder="••••••••••••••" required></label>
  <button class="btn primary block" type="submit">Sign In</button>
  <p class="center"><a class="link" href="login.php">Back to Log In</a></p>
</form>
<?php auth_end();
