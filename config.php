<?php
// ---------- Configuration (edit for your server) ----------
const DB_HOST = 'localhost';
const DB_NAME = 'blood_donation_app';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'BloodDonation';
const DEV_MODE = true;            // true: show reset codes on screen (no mail server needed). Set false in production.
const MAIL_FROM = 'no-reply@hemocare.org';

date_default_timezone_set('Asia/Manila');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
if (session_status() === PHP_SESSION_NONE) session_start();

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Check config.php and import database.sql.');
        }
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }

// ---------- CSRF ----------
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Invalid or expired form token. Go back and refresh the page.');
    }
}

// ---------- Flash messages ----------
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$msg, $type]; }
function flash_html(): string {
    if (empty($_SESSION['flash'])) return '';
    [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']);
    return '<div class="flash ' . e($t) . '" role="status">' . e($m) . '</div>';
}

// ---------- Settings / audit ----------
function setting(string $k, string $default = ''): string {
    static $cache = null;
    if ($cache === null) $cache = q('SELECT skey, svalue FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $cache[$k] ?? $default;
}
function audit(string $title, string $detail = ''): void {
    q('INSERT INTO audit_log (title, detail) VALUES (?,?)', [$title, $detail . ($detail ? ' • ' : '') . 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-')]);
}

// ---------- Auth ----------
function user(): ?array {
    static $u = false;
    if ($u === false) $u = isset($_SESSION['uid']) ? (q('SELECT * FROM users WHERE id=? AND status="Active"', [$_SESSION['uid']])->fetch() ?: null) : null;
    return $u;
}
function require_login(): array {
    if (setting('session_timeout') === '1' && isset($_SESSION['last']) && time() - $_SESSION['last'] > 900) {
        session_unset(); session_destroy(); session_start();
        flash('Session expired after 15 minutes of inactivity.', 'err'); redirect('login.php');
    }
    $_SESSION['last'] = time();
    $u = user();
    if (!$u) redirect('login.php');
    check_csrf();
    return $u;
}
function is_admin(array $u): bool { return $u['access_level'] === 'Admin Ops'; }
function require_admin(array $u): void {
    if (!is_admin($u)) { flash('Admin Ops clearance is required for that action.', 'err'); redirect('dashboard.php'); }
}

// ---------- Display helpers ----------
function time_ago(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' hour' . (floor($d / 3600) > 1 ? 's' : '') . ' ago';
    return floor($d / 86400) . ' day' . (floor($d / 86400) > 1 ? 's' : '') . ' ago';
}
function stock_status(int $units): array {
    if ($units < (int)setting('critical_below', '25')) return ['CRITICAL', 'critical'];
    if ($units < (int)setting('moderate_below', '50')) return ['MODERATE', 'moderate'];
    return ['STABLE', 'stable'];
}
function avatar(?array $row, string $name = ''): string {
    $name = $name ?: ($row['name'] ?? '?');
    if (!empty($row['avatar']) && is_file(__DIR__ . '/assets/img/avatars/' . basename($row['avatar'])))
        return '<img class="avatar" src="assets/img/avatars/' . e(basename($row['avatar'])) . '" alt="">';
    $ini = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)), 0, 2) as $w) $ini .= mb_strtoupper(mb_substr($w, 0, 1));
    return '<span class="avatar ini" aria-hidden="true">' . e($ini) . '</span>';
}
const BLOOD_TYPES = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

function icon(string $n): string {
    static $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'truck' => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
        'drop' => '<path d="M12 2.7s7 7.3 7 12.3a7 7 0 0 1-14 0c0-5 7-12.3 7-12.3z"/>',
        'heart' => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/><path d="M3 12h4l2-3 3 6 2-3h4"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'back' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'chev' => '<path d="m6 9 6 6 6-6"/>',
        'filter' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}
