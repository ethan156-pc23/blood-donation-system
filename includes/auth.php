<?php
function valid_password(string $p): ?string {
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z0-9]/', $p))
        return 'Password needs at least one letter or number.';
    return null;
}

// Throttle repeated attempts per session (5 tries -> 60 second lock).
function throttled(string $key): int {
    $t = $_SESSION['thr'][$key] ?? ['n' => 0, 'until' => 0];
    return $t['until'] > time() ? $t['until'] - time() : 0;
}
function throttle_fail(string $key): void {
    $t = $_SESSION['thr'][$key] ?? ['n' => 0, 'until' => 0];
    $t['n']++;
    if ($t['n'] >= 5) { $t['until'] = time() + 60; $t['n'] = 0; }
    $_SESSION['thr'][$key] = $t;
}
function throttle_clear(string $key): void { unset($_SESSION['thr'][$key]); }

function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
    $_SESSION['last'] = time();
    unset($_SESSION['reset_email'], $_SESSION['reset_id']);
}

// Creates a 6-digit code valid for 10 minutes. Returns the code (or null if the email is unknown).
function issue_reset_code(string $email): ?string {
    $u = q('SELECT id,name FROM users WHERE email=? AND status="Active"', [$email])->fetch();
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_sent_at'] = time();
    if (!$u) return null;                          // same response either way: no account enumeration
    q('UPDATE password_resets SET used=1 WHERE user_id=?', [$u['id']]);
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    q('INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE))',
        [$u['id'], password_hash($code, PASSWORD_DEFAULT)]);
    @mail($email, 'Your BloodDonation security code', "Hello {$u['name']},\n\nYour 6-digit security code is: $code\nIt expires in 10 minutes.\n",
        'From: ' . MAIL_FROM);
    audit('Password reset requested', $email);
    return $code;
}
