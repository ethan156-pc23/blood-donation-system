<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $code = issue_reset_code($email);
        if (DEV_MODE && $code) flash("DEV MODE – your security code is $code", 'info');
        redirect('verify-code.php');
    }
    flash('Enter a valid email address.', 'err');
}
auth_start('Reset access');
?>
<h2>Reset Console Access</h2>
<p class="sub">Enter your verifiable clinical operations email address. If authorized, we'll send a 6-digit confirmation code.</p>
<form method="post">
  <?= csrf_field() ?>
  <label>Verifiable Work Email
    <input type="email" name="email" placeholder="e.g. nurse.thorne@hemocare.org" required autofocus></label>
  <button class="btn primary block" type="submit">Request Reset Verification Code</button>
  <p class="center"><a class="link muted-link" href="login.php"><?= icon('back') ?> Back to Log In</a></p>
</form>
<?php auth_end();
