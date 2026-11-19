<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();

const STATUSES = ['Eligible', 'Deferred', 'Suspended'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['blood_type'] ?? '';
    $status = $_POST['status'] ?? 'Eligible';
    $last = ($_POST['last_donation'] ?? '') ?: null;
    if (in_array($a, ['add', 'edit'], true)) {
        if ($name === '' || !in_array($type, BLOOD_TYPES, true) || !in_array($status, STATUSES, true)) {
            flash('Name, a valid blood type and status are required.', 'err'); redirect('donors.php');
        }
    }
    if ($a === 'add') {
        do { $mid = sprintf('HMC-%04d-%02d', random_int(1000, 9999), random_int(10, 99)); }
        while (q('SELECT 1 FROM donors WHERE medical_id=?', [$mid])->fetch());
        q('INSERT INTO donors (medical_id,name,blood_type,phone,status,last_donation,last_center,eligibility_note) VALUES (?,?,?,?,?,?,?,?)',
            [$mid, $name, $type, trim($_POST['phone'] ?? ''), $status, $last, trim($_POST['last_center'] ?? '') ?: null,
             trim($_POST['eligibility_note'] ?? '') ?: ($status === 'Eligible' ? 'Next eligible: Immediately' : '')]);
        audit('Donor registered', "$name ($mid)");
        flash("Donor $name registered with ID $mid.");
    } elseif ($a === 'edit') {
        q('UPDATE donors SET name=?, blood_type=?, phone=?, status=?, last_donation=?, last_center=?, eligibility_note=? WHERE id=?',
            [$name, $type, trim($_POST['phone'] ?? ''), $status, $last, trim($_POST['last_center'] ?? '') ?: null,
             trim($_POST['eligibility_note'] ?? ''), (int)$_POST['id']]);
        audit('Donor log updated', $name);
        flash('Donor log updated.');
    } elseif ($a === 'queue') {
        $d = q('SELECT * FROM donors WHERE id=?', [(int)$_POST['id']])->fetch();
        if (!$d) flash('Donor not found.', 'err');
        elseif ($d['status'] !== 'Eligible') flash($d['name'] . ' is ' . strtolower($d['status']) . ' and cannot be queued.', 'err');
        elseif (q('SELECT 1 FROM appointments WHERE donor_id=? AND appt_date=CURDATE()', [$d['id']])->fetch()) flash($d['name'] . ' is already queued today.', 'err');
        else {
            $slot = date('H:i:00', ceil(time() / 900) * 900);   // next quarter hour
            q('INSERT INTO appointments (donor_id,appt_date,slot_time,center,location,screen_status) VALUES (?,CURDATE(),?,"HQ Central","HQ Central Room A","Pending Lab")', [$d['id'], $slot]);
            audit('Donor queued', $d['name']);
            flash($d['name'] . ' added to today\'s queue at ' . date('g:i A', strtotime($slot)) . '.');
        }
    }
    redirect('donors.php');
}

// ---- list with filters + pagination ----
$s = trim($_GET['s'] ?? '');
$ft = in_array($_GET['type'] ?? '', BLOOD_TYPES, true) ? $_GET['type'] : '';
$fs = in_array($_GET['status'] ?? '', STATUSES, true) ? $_GET['status'] : '';
$where = ['1=1']; $args = [];
if ($s !== '') { $where[] = '(name LIKE ? OR medical_id LIKE ? OR phone LIKE ? OR blood_type = ?)'; array_push($args, "%$s%", "%$s%", "%$s%", strtoupper($s)); }
if ($ft) { $where[] = 'blood_type=?'; $args[] = $ft; }
if ($fs) { $where[] = 'status=?'; $args[] = $fs; }
$W = implode(' AND ', $where);
$per = 10;
$total = (int)q("SELECT COUNT(*) FROM donors WHERE $W", $args)->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($pages, max(1, (int)($_GET['p'] ?? 1)));
$off = ($page - 1) * $per;
$rows = q("SELECT * FROM donors WHERE $W ORDER BY name LIMIT $per OFFSET $off", $args)->fetchAll();
$all = (int)q('SELECT COUNT(*) FROM donors')->fetchColumn();
function pg(int $p): string { return 'donors.php?' . http_build_query(array_merge($_GET, ['p' => $p])); }

app_start('Donor Database', 'Verify medical eligibility profiles, donor frequency stats, and active status logs.', 'donors');
?>
<form class="card filters" method="get">
  <label class="search"><?= icon('search') ?><input type="search" name="s" value="<?= e($s) ?>" placeholder="Search donors by name, medical ID, phone, or blood antigen type..."></label>
  <select name="type" data-autosubmit aria-label="Blood type"><option value="">Blood Type: All</option>
    <?php foreach (BLOOD_TYPES as $t): ?><option <?= $ft === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
  <select name="status" data-autosubmit aria-label="Status"><option value="">Status: All</option>
    <?php foreach (STATUSES as $t): ?><option <?= $fs === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
  <button type="button" class="btn primary" data-open="addModal">+ Add Donor</button>
</form>

<div class="card table-card"><div class="scroll"><table>
  <thead><tr><th>Donor Profile</th><th>Blood Type</th><th>Status</th><th>Last Donation</th><th>Eligibility Metrics</th><th class="r">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $d): $cls = ['Eligible' => 'ok', 'Deferred' => 'warn', 'Suspended' => 'bad'][$d['status']]; ?>
    <tr>
      <td><div class="person"><?= avatar($d) ?><div><b><?= e($d['name']) ?></b><small>ID: <?= e($d['medical_id']) ?></small></div></div></td>
      <td><span class="bt <?= $d['blood_type'] === 'O-' ? 'rare' : '' ?>"><?= e($d['blood_type']) ?></span></td>
      <td><span class="badge <?= $cls ?>"><?= e($d['status']) ?></span></td>
      <td><b class="n"><?= $d['last_donation'] ? e(date('M d, Y', strtotime($d['last_donation']))) : '—' ?></b><small><?= e($d['last_center'] ?? '') ?></small></td>
      <td><b class="note <?= $cls ?>"><?= e($d['eligibility_note'] ?: '—') ?></b><small>Iron &amp; blood volume logs verified</small></td>
      <td class="r nowrap">
        <button class="btn sm" data-open="editModal" data-fill="<?= e(json_encode($d)) ?>">Edit Log</button>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="queue"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn sm primary">Queue</button></form>
      </td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No donors match your filters.</td></tr><?php endif; ?>
  </tbody></table></div>
  <div class="table-foot"><span>Showing <?= $total ? $off + 1 : 0 ?> - <?= min($off + $per, $total) ?> of <?= number_format($total) ?> <?= $total === $all ? 'registered clinical donor profiles' : 'matching profiles' ?></span>
    <div><a class="btn sm <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= pg($page - 1) ?>">Previous</a>
         <a class="btn sm <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= pg($page + 1) ?>">Next Page</a></div></div>
</div>

<?php foreach (['addModal' => ['Register New Donor', 'add'], 'editModal' => ['Edit Donor Log', 'edit']] as $id => [$title, $act]):
  modal_start($id, $title); ?>
  <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="id">
    <label class="full">Full Name<input name="name" required></label>
    <label>Blood Type<select name="blood_type" required><?php foreach (BLOOD_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></label>
    <label>Status<select name="status"><?php foreach (STATUSES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></label>
    <label>Phone<input name="phone"></label>
    <label>Last Donation<input type="date" name="last_donation"></label>
    <label class="full">Last Donation Center<input name="last_center" placeholder="e.g. HQ Central Center"></label>
    <label class="full">Eligibility Note<input name="eligibility_note" placeholder="e.g. Low Hemoglobin. Rel: Nov 10"></label>
    <div class="full actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn primary">Save</button></div>
  </form>
<?php modal_end(); endforeach; ?>
<?php app_end();
