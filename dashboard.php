<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();

$donors = (int)q('SELECT COUNT(*) FROM donors')->fetchColumn();
$today = q('SELECT COUNT(*) total, SUM(checked_in) done FROM appointments WHERE appt_date = CURDATE()')->fetch();
$inv = q('SELECT blood_type, units FROM blood_inventory ORDER BY FIELD(blood_type,"O+","O-","A+","A-","B+","B-","AB+","AB-")')->fetchAll();
$weeks = q('SELECT week_no, units FROM weekly_collections ORDER BY week_no')->fetchAll();
$drives = q('SELECT * FROM drives WHERE status IN ("Active","Upcoming") ORDER BY drive_date LIMIT 4')->fetchAll();
$critical = array_filter($inv, fn($r) => stock_status((int)$r['units'])[1] === 'critical');
$maxWeek = max(array_column($weeks, 'units') ?: [1]);

app_start('Clinical Operations Dashboard', 'HQ Central Blood bank inventory, active donor queues, and regional logistics alerts.', 'dashboard');
?>
<?php if ($critical): ?>
<div class="alert-banner"><?= icon('alert') ?><span><b>CRITICAL SHORTAGE:</b> Type
  <?= e(implode(', ', array_map(fn($r) => $r['blood_type'], $critical))) ?> supply at HQ Central is critically low
  (<?= e(implode(', ', array_map(fn($r) => $r['blood_type'] . ': ' . $r['units'] . ' units', $critical))) ?>). Regional hospitals alert issued.</span></div>
<?php endif; ?>

<div class="grid3">
  <div class="card stat"><span class="sico blue"><?= icon('users') ?></span><div><b><?= number_format($donors) ?></b><p>Total Registered Donors</p><small>+<?= e(setting('donors_this_week', '0')) ?> this week</small></div></div>
  <div class="card stat"><span class="sico amber"><?= icon('calendar') ?></span><div><b><?= (int)$today['total'] ?> Active</b><p>Appointments Today</p><small><?= (int)$today['done'] ?> completed so far</small></div></div>
  <div class="card stat"><span class="sico rose"><?= icon('drop') ?></span><div><b><?= number_format((int)setting('ytd_units')) ?> Units</b><p>Units Donated (YTD)</p><small><?= e(setting('pass_rate')) ?>% testing pass rate</small></div></div>
</div>

<div class="card">
  <div class="card-head"><div><h2>HQ Central Blood Bank Inventory</h2><p class="muted">Units in reserve by antigen group. Targeted floor: <?= e(setting('inventory_floor')) ?> units.</p></div>
    <div class="legend"><span class="d crit"></span>Critical (0-24h) <span class="d mod"></span>Moderate (24-72h) <span class="d stab"></span>Stable (72h+)</div></div>
  <div class="inv-grid">
    <?php foreach ($inv as $r): [$lab, $cls] = stock_status((int)$r['units']); ?>
    <div class="inv"><div class="inv-top"><b><?= e($r['blood_type']) ?></b><span class="tag <?= $cls ?>"><?= $lab ?></span></div>
      <div class="inv-n"><b><?= (int)$r['units'] ?></b> Units</div>
      <div class="bar <?= $cls ?>"><i style="width:<?= min(100, round($r['units'] / 180 * 100)) ?>%"></i></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid2">
  <div class="card"><div class="card-head"><h2>Weekly Units Collected Trend</h2><span class="muted">Past 6 Weeks</span></div>
    <div class="chart">
      <?php foreach ($weeks as $w): ?>
      <div class="col"><span><?= (int)$w['units'] ?></span><i style="height:<?= round($w['units'] / $maxWeek * 150) ?>px"></i><em>Wk <?= (int)$w['week_no'] ?></em></div>
      <?php endforeach; ?>
    </div></div>
  <div class="card"><div class="card-head"><h2>Upcoming Mobile Blood Drives</h2></div>
    <?php foreach ($drives as $d): ?>
    <div class="row-item"><span class="sico rose sm"><?= icon('truck') ?></span>
      <div><b><?= e($d['name']) ?></b><small><?= e(date('M j', strtotime($d['drive_date']))) ?>, <?= e(date('H:i', strtotime($d['start_time']))) ?> - <?= e(date('H:i', strtotime($d['end_time']))) ?></small></div>
      <span class="chip"><?= (int)$d['registered'] ?> registered</span></div>
    <?php endforeach; if (!$drives): ?><p class="muted">No upcoming drives scheduled.</p><?php endif; ?>
  </div>
</div>
<?php app_end();
