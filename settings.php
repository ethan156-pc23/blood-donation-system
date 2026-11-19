<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin($u);
    foreach (['two_factor', 'session_timeout', 'ip_whitelist'] as $k)
        q('UPDATE settings SET svalue=? WHERE skey=?', [isset($_POST[$k]) ? '1' : '0', $k]);
    foreach (['org_name' => 120, 'fda_license' => 40] as $k => $len) {
        $v = trim($_POST[$k] ?? '');
        if ($v !== '') q('UPDATE settings SET svalue=? WHERE skey=?', [mb_substr($v, 0, $len), $k]);
    }
    audit('Security policy updated', $u['name']);
    flash('Security settings saved.');
    redirect('settings.php');
}
$log = q('SELECT * FROM audit_log ORDER BY id DESC LIMIT 8')->fetchAll();
$admin = is_admin($u);
$toggles = [
    'two_factor' => ['Two-Factor Enforcement', 'Mandate SMS or TOTP authenticator keys for all clinicians.'],
    'session_timeout' => ['Session Timeout Limit', 'Disconnect operational consoles after 15 minutes of inactivity.'],
    'ip_whitelist' => ['Strict IP Whitelisting', 'Prevent logins outside the clinical network range.'],
];
app_start('Security & System Settings', 'Provision identity policies, mandate medical auth tokens, and audit login states.', 'settings');
?>
<div class="split rev">
  <form class="card" method="post"><?= csrf_field() ?>
    <h2>Authentication Policy Settings</h2>
    <?php foreach ($toggles as $k => [$t, $d]): ?>
    <div class="toggle-row"><div><b><?= e($t) ?></b><small><?= e($d) ?></small></div>
      <label class="switch"><input type="checkbox" name="<?= $k ?>" <?= setting($k) === '1' ? 'checked' : '' ?> <?= $admin ? '' : 'disabled' ?>><i></i></label></div>
    <?php endforeach; ?>
    <hr><h2>Clinical Organization Metadata</h2>
    <div class="form-grid">
      <label>Registered Name<input name="org_name" value="<?= e(setting('org_name')) ?>" <?= $admin ? '' : 'readonly' ?>></label>
      <label>FDA License ID<input name="fda_license" value="<?= e(setting('fda_license')) ?>" <?= $admin ? '' : 'readonly' ?>></label>
    </div>
    <div class="actions"><?= $admin ? '<button class="btn primary">Save Settings</button>' : '<span class="muted">Admin Ops clearance required to change settings.</span>' ?></div>
  </form>
  <aside class="card"><h2>Compliance Security Audit Log</h2>
    <?php foreach ($log as $l): ?>
    <div class="log"><div class="between"><b><?= e($l['title']) ?></b><small><?= e(time_ago($l['created_at'])) ?></small></div><small><?= e($l['detail']) ?></small></div>
    <?php endforeach; ?></aside>
</div>
<p><a class="btn danger" href="logout.php">LOGOUT</a></p>
<?php app_end();
