<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();
require_admin($u);

$counts = q('SELECT
    COUNT(*) AS staff_total,
    SUM(status="Active") AS staff_active,
    SUM(status="Pending") AS staff_pending,
    SUM(status="Suspended") AS staff_suspended
    FROM users')->fetch();
$donors = (int)q('SELECT COUNT(*) FROM donors')->fetchColumn();
$admins = (int)q('SELECT COUNT(*) FROM users WHERE status="Active" AND access_level="Admin Ops"')->fetchColumn();
$log = q('SELECT title, detail, created_at FROM audit_log ORDER BY id DESC LIMIT 8')->fetchAll();

app_start('Admin Console', 'Manage account access and review system activity.', 'admin');
?>
<div class="grid3">
  <div class="card stat"><span class="sico blue"><?= icon('users') ?></span><div><b><?= (int)$counts['staff_active'] ?></b><p>Active Staff Accounts</p><small><?= (int)$counts['staff_total'] ?> total accounts</small></div></div>
  <div class="card stat"><span class="sico amber"><?= icon('alert') ?></span><div><b><?= (int)$counts['staff_pending'] ?></b><p>Pending Approvals</p><small><?= (int)$counts['staff_suspended'] ?> suspended accounts</small></div></div>
  <div class="card stat"><span class="sico rose"><?= icon('shield') ?></span><div><b><?= $admins ?></b><p>Active Admins</p><small><?= number_format($donors) ?> registered donors</small></div></div>
</div>

<div class="grid2">
  <div class="card">
    <div class="card-head"><div><h2>Administration</h2><p class="muted">Manage staff access and system policies.</p></div></div>
    <div class="row-item"><span class="sico blue sm"><?= icon('users') ?></span>
      <div><b>Staff &amp; Roles</b><small>Approve accounts, invite staff, or change account status.</small></div>
      <a class="btn sm" href="staff.php">Open</a></div>
    <div class="row-item"><span class="sico rose sm"><?= icon('settings') ?></span>
      <div><b>System Settings</b><small>Review security policies and organization details.</small></div>
      <a class="btn sm" href="settings.php">Open</a></div>
  </div>
  <div class="card">
    <div class="card-head"><div><h2>Recent Audit Activity</h2><p class="muted">Latest recorded system events.</p></div></div>
    <?php foreach ($log as $item): ?>
    <div class="log"><div class="between"><b><?= e($item['title']) ?></b><small><?= e(time_ago($item['created_at'])) ?></small></div>
      <small><?= e($item['detail']) ?></small></div>
    <?php endforeach; if (!$log): ?><p class="muted">No audit activity recorded.</p><?php endif; ?>
  </div>
</div>
<?php app_end();
