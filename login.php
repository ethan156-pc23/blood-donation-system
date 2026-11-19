<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/auth.php';
if (user()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    if ($wait = throttled('login')) {
        $error = "Too many attempts. Try again in {$wait}s.";
    } else {
        $u = q('SELECT * FROM users WHERE email=?', [$email])->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            if ($u['status'] !== 'Active') {
                $error = $u['status'] === 'Pending' ? 'Your account is awaiting administrator approval.' : 'This account is suspended.';
            } else {
                throttle_clear('login');
                login_user($u);
                audit('Authorized login: ' . $u['name'], 'HQ Console');
                redirect('dashboard.php');
            }
        } else {
            throttle_fail('login');
            audit('Failed login attempt', $email);
            $error = 'Invalid email or password.';
        }
    }
}
auth_start('Log in');
?>
<h2>Log in to BloodDonation</h2>
<?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
  <?= csrf_field() ?>
  <label>Operational Email
    <input type="email" name="email" placeholder="e.g. nurse.thorne@hemocare.org" value="<?= e($_POST['email'] ?? '') ?>" required autofocus></label>
  <label>Console Password
    <input type="password" name="password" placeholder="••••••••••••••" required></label>
  <div class="right"><a class="link" href="forgot-password.php">Forgot Password?</a></div>
  <button class="btn primary block" type="submit">Log In</button>
  <p class="center"><a class="link" href="register.php">Sign In?</a></p>
</form>
<?php auth_end();
