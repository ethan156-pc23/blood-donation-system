<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();

const CENTERS = ['HQ Central' => 38, 'Campus Mobile Drive' => 40, 'Metro Hub Drive' => 50];   // slot capacity per day
const SCREEN = ['Pre-Screened' => 'ok', 'Pending Lab' => 'warn', 'Approved' => 'ok', 'Deferred' => 'bad'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'checkin') {
        $r = q('SELECT a.*, d.name FROM appointments a JOIN donors d ON d.id=a.donor_id WHERE a.id=?', [(int)$_POST['id']])->fetch();
        if (!$r) flash('Appointment not found.', 'err');
        elseif ($r['screen_status'] === 'Deferred') flash($r['name'] . ' is deferred and cannot be checked in.', 'err');
        else { q('UPDATE appointments SET checked_in=1 WHERE id=?', [$r['id']]); audit('Donor checked in', $r['name']); flash($r['name'] . ' checked in.'); }
    } elseif ($a === 'create') {
        $donor = (int)($_POST['donor_id'] ?? 0);
        $date = $_POST['appt_date'] ?? ''; $time = $_POST['slot_time'] ?? '';
        $center = $_POST['center'] ?? ''; $loc = trim($_POST['location'] ?? '');
        $st = $_POST['screen_status'] ?? 'Pending Lab';
        if (!$donor || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time) || !isset(CENTERS[$center]) || !isset(SCREEN[$st]) || $loc === '')
            flash('Please complete all appointment fields.', 'err');
        elseif (q('SELECT 1 FROM appointments WHERE donor_id=? AND appt_date=?', [$donor, $date])->fetch()) flash('That donor already has an appointment on that date.', 'err');
        elseif ((int)q('SELECT COUNT(*) FROM appointments WHERE appt_date=? AND center=?', [$date, $center])->fetchColumn() >= CENTERS[$center]) flash("$center is fully booked on that date.", 'err');
        else {
            q('INSERT INTO appointments (donor_id,appt_date,slot_time,center,location,screen_status) VALUES (?,?,?,?,?,?)', [$donor, $date, "$time:00", $center, $loc, $st]);
            audit('Appointment created', "$center $date $time"); flash('Appointment created.');
        }
    }
    redirect('appointments.php?' . http_build_query(array_intersect_key($_GET, ['view' => 1, 'center' => 1])));
}

$view = in_array($_GET['view'] ?? '', ['week', 'month'], true) ? $_GET['view'] : 'today';
$fc = isset(CENTERS[$_GET['center'] ?? '']) ? $_GET['center'] : '';
$range = match ($view) {
    'week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
    'month' => [date('Y-m-01'), date('Y-m-t')],
    default => [date('Y-m-d'), date('Y-m-d')],
};
$w = 'a.appt_date BETWEEN ? AND ?'; $args = $range;
if ($fc) { $w .= ' AND a.center=?'; $args[] = $fc; }
$rows = q("SELECT a.*, d.name, d.blood_type, d.avatar FROM appointments a JOIN donors d ON d.id=a.donor_id WHERE $w ORDER BY a.appt_date, a.slot_time", $args)->fetchAll();

$counts = q('SELECT center, COUNT(*) n FROM appointments WHERE appt_date BETWEEN ? AND ? GROUP BY center', $range)->fetchAll(PDO::FETCH_KEY_PAIR);
$days = ['today' => 1, 'week' => 7, 'month' => (int)date('t')][$view];
$eligible = q('SELECT id, name, blood_type FROM donors WHERE status="Eligible" ORDER BY name')->fetchAll();
function tab(string $v, string $label, string $cur): string {
    return '<a class="btn ' . ($v === $cur ? 'tab-on' : '') . '" href="appointments.php?' . e(http_build_query(array_merge($_GET, ['view' => $v]))) . '">' . $label . '</a>';
}

app_start('Appointments & Scheduling Calendar', 'Coordinate active queues, donor slots, location capacities, and mobile drives.', 'appointments');
?>
<div class="card filters">
  <?= tab('today', "Today's Agenda", $view) . tab('week', 'Weekly Timeline', $view) . tab('month', 'Month Overview', $view) ?>
  <span class="spacer"></span>
  <form method="get"><input type="hidden" name="view" value="<?= e($view) ?>">
    <select name="center" data-autosubmit aria-label="Center"><option value="">Center: All</option>
      <?php foreach (CENTERS as $c => $_): ?><option <?= $fc === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></form>
  <button class="btn primary" data-open="apptModal">+ Create Appointment</button>
</div>

<div class="grid3">
<?php foreach (CENTERS as $c => $cap): $n = (int)($counts[$c] ?? 0); $pct = min(100, round($n / ($cap * $days) * 100));
      $cls = $pct >= 80 ? 'critical' : ($pct >= 40 ? 'moderate' : 'stable'); ?>
  <div class="card"><div class="between"><b><?= e($c === 'HQ Central' ? 'HQ Central Queue' : $c) ?></b><span class="cap <?= $cls ?>"><?= $pct ?>% capacity</span></div>
    <h3 class="big"><?= $n ?> slots filled</h3><div class="bar <?= $cls ?>"><i style="width:<?= $pct ?>%"></i></div></div>
<?php endforeach; ?>
</div>

<div class="card table-card"><div class="scroll"><table>
  <thead><tr><th>Time Slot</th><th>Donor Name</th><th>Blood Type</th><th>Location</th><th>Screen Status</th><th class="r">Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><b><?= e(date('h:i A', strtotime($r['slot_time']))) ?></b><?= $view !== 'today' ? '<small>' . e(date('D, M j', strtotime($r['appt_date']))) . '</small>' : '' ?></td>
      <td><div class="person"><?= avatar($r) ?><b><?= e($r['name']) ?></b></div></td>
      <td><span class="bt <?= $r['blood_type'] === 'O-' ? 'rare' : '' ?>"><?= e($r['blood_type']) ?></span></td>
      <td class="muted"><?= e($r['location']) ?></td>
      <td><span class="badge <?= SCREEN[$r['screen_status']] ?>"><?= e($r['screen_status']) ?></span></td>
      <td class="r"><?php if ($r['checked_in']): ?><span class="badge ok">Checked in</span><?php else: ?>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm">Check-in</button></form><?php endif; ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No appointments in this period.</td></tr><?php endif; ?>
</tbody></table></div></div>

<?php modal_start('apptModal', 'Create Appointment'); ?>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <label class="full">Donor<select name="donor_id" required><option value="">Select eligible donor…</option>
    <?php foreach ($eligible as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name'] . ' (' . $d['blood_type'] . ')') ?></option><?php endforeach; ?></select></label>
  <label>Date<input type="date" name="appt_date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required></label>
  <label>Time<input type="time" name="slot_time" value="09:00" required></label>
  <label>Center<select name="center"><?php foreach (CENTERS as $c => $_): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
  <label>Screen Status<select name="screen_status"><?php foreach (SCREEN as $s => $_): ?><option <?= $s === 'Pending Lab' ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></label>
  <label class="full">Room / Location<input name="location" value="HQ Central Room A" required></label>
  <div class="full actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn primary">Create</button></div>
</form>
<?php modal_end(); app_end();
