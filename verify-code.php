<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/auth.php';
if (empty($_SESSION['reset_email'])) redirect('forgot-password.php');
$email = $_SESSION['reset_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'resend') {
        if (time() - ($_SESSION['reset_sent_at'] ?? 0) >= 60) {
            $code = issue_reset_code($email);
            flash(DEV_MODE && $code ? "DEV MODE – new security code is $code" : 'A new code was sent if the account exists.', 'info');
        } else flash('Please wait before requesting another code.', 'err');
        redirect('verify-code.php');
    }
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    $row = q('SELECT pr.* FROM password_resets pr JOIN users u ON u.id=pr.user_id
              WHERE u.email=? AND pr.used=0 ORDER BY pr.id DESC LIMIT 1', [$email])->fetch();
    if (!$row || strtotime($row['expires_at']) < time()) $error = 'That code has expired. Request a new one.';
    elseif ($row['attempts'] >= 5) $error = 'Too many wrong attempts. Request a new code.';
    elseif (strlen($code) !== 6 || !password_verify($code, $row['code_hash'])) {
        q('UPDATE password_resets SET attempts=attempts+1 WHERE id=?', [$row['id']]);
        $error = 'Incorrect security code.';
    } else {
        q('UPDATE password_resets SET verified=1 WHERE id=?', [$row['id']]);
        $_SESSION['reset_id'] = $row['id'];
        redirect('reset-password.php');
    }
}
$left = max(0, 60 - (time() - ($_SESSION['reset_sent_at'] ?? 0)));
auth_start('Security code');
?>
<h2>Enter Security Code</h2>
<p class="sub">We sent a 6-digit security code to your registered email address. This code remains active for 10 minutes.</p>
<?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
<form method="post" id="codeForm">
  <?= csrf_field() ?>
  <input type="hidden" name="code" id="codeValue">
  <div class="otp" id="otp">
    <?php for ($i = 0; $i < 6; $i++): ?><input inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Digit <?= $i + 1 ?>"><?php endfor; ?>
  </div>
  <button class="btn primary block" type="submit">Confirm &amp; Verify Identity</button>
</form>
<form method="post" class="center resend">
  <?= csrf_field() ?><input type="hidden" name="action" value="resend">
  <p class="muted">Didn't receive the email notification?</p>
  <button class="link-btn" id="resendBtn" data-left="<?= $left ?>" <?= $left ? 'disabled' : '' ?>>Resend verification code<?= $left ? " ({$left}s)" : '' ?></button>
</form>
<?php auth_end();
