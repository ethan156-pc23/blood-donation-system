<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'create') {
        $name = trim($_POST['name'] ?? ''); $date = $_POST['drive_date'] ?? '';
        $st = $_POST['start_time'] ?? ''; $en = $_POST['end_time'] ?? '';
        $staff = trim($_POST['staffing'] ?? ''); $target = max(1, (int)($_POST['target'] ?? 50));
        if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $en) || $en <= $st)
            flash('Enter a name, a date, and an end time later than the start time.', 'err');
        else {
            q('INSERT INTO drives (name,drive_date,start_time,end_time,staffing,target,status) VALUES (?,?,?,?,?,?,"Upcoming")', [$name, $date, "$st:00", "$en:00", $staff ?: 'TBD', $target]);
            audit('Mobile drive created', $name); flash("Drive \"$name\" scheduled.");
        }
    } elseif ($a === 'status' && in_array($_POST['to'] ?? '', ['Active', 'Completed'], true)) {
        q('UPDATE drives SET status=? WHERE id=?', [$_POST['to'], (int)$_POST['id']]);
        audit('Drive status changed', 'Drive #' . (int)$_POST['id'] . ' → ' . $_POST['to']); flash('Drive updated.');
    } elseif ($a === 'register') {
        q('UPDATE drives SET registered = registered + 1 WHERE id=? AND status <> "Completed"', [(int)$_POST['id']]); flash('Donor registration added.');
    }
    redirect('drives.php' . (($_POST['tab'] ?? '') === 'archive' ? '?tab=archive' : ''));
}

$arch = ($_GET['tab'] ?? '') === 'archive';
$drives = q('SELECT * FROM drives WHERE status ' . ($arch ? '= "Completed" ORDER BY drive_date DESC' : 'IN ("Active","Upcoming") ORDER BY drive_date'))->fetchAll();
app_start('Blood Drive Management', 'Deploy mobile collection vehicles, coordinate personnel, and monitor regional turnouts.', 'drives');
?>
<div class="card filters">
  <a class="btn <?= !$arch ? 'tab-on' : '' ?>" href="drives.php">Active &amp; Upcoming Drives</a>
  <a class="btn <?= $arch ? 'tab-on' : '' ?>" href="drives.php?tab=archive">Archived Histories</a>
  <span class="spacer"></span><button class="btn primary" data-open="driveModal">+ Create Mobile Drive</button>
</div>

<div class="grid2 even">
<?php foreach ($drives as $d): $pct = min(100, round($d['registered'] / max(1, $d['target']) * 100)); ?>
  <div class="card drive">
    <div class="between"><h3><?= e($d['name']) ?></h3>
      <span class="tag <?= $d['status'] === 'Active' ? 'stable' : 'neutral' ?>"><?= $d['status'] === 'Active' ? 'Active Now' : ($d['status'] === 'Upcoming' ? 'Upcoming' : 'Completed ' . e(date('M j', strtotime($d['drive_date'])))) ?></span></div>
    <p class="muted"><?= e(date('M j', strtotime($d['drive_date']))) ?>, <?= e(date('H:i', strtotime($d['start_time']))) ?> - <?= e(date('H:i', strtotime($d['end_time']))) ?></p>
    <p class="muted">Staffing: <?= e($d['staffing']) ?></p>
    <div class="between sm"><span class="muted">Turnout / Registration</span><b><?= (int)$d['registered'] ?>/<?= (int)$d['target'] ?> <?= $d['status'] === 'Completed' ? 'Collected' : 'Donors' ?></b></div>
    <div class="bar critical"><i style="width:<?= $pct ?>%"></i></div>
    <?php if ($d['status'] !== 'Completed'): ?>
    <div class="btns top"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="register"><button class="btn sm">+ Register Donor</button></form>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="status">
        <input type="hidden" name="to" value="<?= $d['status'] === 'Active' ? 'Completed' : 'Active' ?>"><button class="btn sm"><?= $d['status'] === 'Active' ? 'Mark Completed' : 'Start Drive' ?></button></form></div>
    <?php endif; ?>
  </div>
<?php endforeach; if (!$drives): ?><div class="card empty">No drives in this view.</div><?php endif; ?>
</div>

<div class="card"><div class="card-head"><h2>Mobile Drive Deployment Logistics Map</h2></div>
  <div class="map"><?= icon('truck') ?><span>Vehicles are monitored via satellite telemetry (HQ Active Dispatch Node)</span></div></div>

<?php modal_start('driveModal', 'Create Mobile Drive'); ?>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <label class="full">Drive Name / Location<input name="name" required placeholder="e.g. State University Gym"></label>
  <label>Date<input type="date" name="drive_date" min="<?= date('Y-m-d') ?>" required></label>
  <label>Target Donors<input type="number" name="target" min="1" value="50"></label>
  <label>Start<input type="time" name="start_time" value="09:00" required></label>
  <label>End<input type="time" name="end_time" value="16:00" required></label>
  <label class="full">Staffing<input name="staffing" placeholder="e.g. 4 Phlebotomists, 1 Nurse"></label>
  <div class="full actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn primary">Create Drive</button></div>
</form>
<?php modal_end(); app_end();
