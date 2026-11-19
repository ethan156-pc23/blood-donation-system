<?php
// Shared page shells: app_start()/app_end() for the console, auth_start()/auth_end() for login screens.
function head_tags(string $title): string {
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' · ' . APP_NAME . '</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="assets/css/style.css"></head>';
}
function brand(bool $sub = false): string {
    return '<div class="brand"><span class="logo">' . icon('heart') . '</span><span><b>BLOOD DONATION</b>'
        . ($sub ? '<small>CLINICAL OPS</small>' : '') . '</span></div>';
}

function auth_start(string $title): void {
    echo head_tags($title) . '<body class="auth"><main class="auth-card">' . brand() . flash_html();
}
function auth_end(): void { echo '</main><script src="assets/js/app.js"></script></body></html>'; }

function app_start(string $title, string $sub, string $active): void {
    $u = user();
    $nav = [
        'dashboard' => ['Operations Overview', 'grid'], 'donors' => ['Donor Management', 'users'],
        'appointments' => ['Appointments', 'calendar'], 'inventory' => ['Blood Inventory', 'database'],
        'staff' => ['Staff & Roles', 'shield'], 'drives' => ['Mobile Drives', 'truck'],
        'settings' => ['System Settings', 'settings'],
    ];
    if ($u && is_admin($u)) $nav = ['admin' => ['Admin Console', 'shield']] + $nav;
    $crit = (int)q('SELECT COUNT(*) FROM blood_inventory WHERE units < ?', [(int)setting('critical_below', '25')])->fetchColumn();
    echo head_tags($title) . '<body><div class="shell">';
    echo '<aside class="sidebar" id="sidebar">' . brand(true) . '<nav>';
    foreach ($nav as $k => [$label, $ic]) {
        echo '<a href="' . $k . '.php" class="' . ($active === $k ? 'active' : '') . '">' . icon($ic) . '<span>' . e($label) . '</span></a>';
    }
    echo '</nav><details class="usermenu"><summary><span>' . e($u['name']) . '</span>' . icon('chev') . '</summary>'
        . '<a href="logout.php">Log out</a></details></aside>';
    echo '<div class="overlay" id="overlay"></div><div class="main"><header class="topbar">'
        . '<button class="menu-btn" id="menuBtn" aria-label="Open menu">' . icon('menu') . '</button>'
        . '<div class="ttl"><h1>' . e($title) . '</h1><p>' . e($sub) . '</p></div>'
        . '<div class="tools"><span class="pill ok"><i></i>HQ Blood Center: ' . ($crit >= 3 ? 'Alert' : 'Stable') . '</span>'
        . '<a class="iconbtn' . ($crit ? ' dot' : '') . '" href="dashboard.php" aria-label="Alerts">' . icon('bell') . '</a></div></header>'
        . '<section class="content">' . flash_html();
}
function app_end(): void { echo '</section></div></div><script src="assets/js/app.js"></script></body></html>'; }

// Modal helper: modal_start('id','Title') ... modal_end()
function modal_start(string $id, string $title): void {
    echo '<div class="modal" id="' . e($id) . '" hidden><div class="modal-box"><div class="modal-head"><h3>' . e($title)
        . '</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>';
}
function modal_end(): void { echo '</div></div>'; }
