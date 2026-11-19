<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/auth.php';
$rid = $_SESSION['reset_id'] ?? 0;
$row = $rid ? q('SELECT * FROM password_resets WHERE id=? AND verified=1 AND used=0 AND expires_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)', [$rid])->fetch() : null;
if (!$row) { flash('Please verify a security code first.', 'err'); redirect('forgot-password.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p1 = $_POST['password'] ?? ''; $p2 = $_POST['confirm'] ?? '';
    if ($p1 !== $p2) $error = 'Passwords do not match.';
    elseif ($msg = valid_password($p1)) $error = $msg;
    else {
        q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($p1, PASSWORD_DEFAULT), $row['user_id']]);
        q('UPDATE password_resets SET used=1 WHERE user_id=?', [$row['user_id']]);
        $u = q('SELECT * FROM users WHERE id=?', [$row['user_id']])->fetch();
        login_user($u);
        audit('Password reset completed', $u['email']);
        flash('Password updated. Welcome back.');
        redirect('dashboard.php');
    }
}
auth_start('New password');
?>
<h2>Create New Password</h2>
<p class="sub">Ensure your new console credentials meet all compliance specifications for medical ops.</p>
<?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <label>New Complex Password
    <span class="pw"><input type="password" name="password" placeholder="•••••••••••••••••" required minlength="8">
    <button type="button" class="eye" data-toggle-pw aria-label="Show password"><?= icon('eye') ?></button></span></label>
  <label>Re-enter New Password
    <span class="pw"><input type="password" name="confirm" placeholder="•••••••••••••••••" required minlength="8">
    <button type="button" class="eye" data-toggle-pw aria-label="Show password"><?= icon('eye') ?></button></span></label>
  <button class="btn primary block" type="submit">Save Changes &amp; Enter Console</button>
</form>
<?php auth_end();
