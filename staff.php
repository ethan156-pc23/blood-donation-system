<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/auth.php';
$u = require_login();

const ROLES = ['Clinical Chief', 'Phlebotomist', 'Lab Technician', 'Triage Nurse', 'System Administrator'];
const ACCESS = ['Admin Ops', 'Donation Log', 'Serology Lab', 'Pre-screening'];
const FACILITIES = ['HQ Center + Mobile', 'HQ Central Center', 'HQ Central Lab', 'Mobile Team B'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin($u);
    $a = $_POST['action'] ?? '';
    if ($a === 'invite') {
        $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($_POST['role'] ?? '', ROLES, true) || !in_array($_POST['access'] ?? '', ACCESS, true) || !in_array($_POST['facility'] ?? '', FACILITIES, true))
            flash('Complete all invitation fields with a valid email.', 'err');
        elseif (q('SELECT 1 FROM users WHERE email=?', [$email])->fetch()) flash('That email already has an account.', 'err');
        else {
            $tmp = 'Tmp' . bin2hex(random_bytes(4)) . '9';
            q('INSERT INTO users (name,email,password_hash,role,facility,access_level,status) VALUES (?,?,?,?,?,?,"Active")',
                [$name, $email, password_hash($tmp, PASSWORD_DEFAULT), $_POST['role'], $_POST['facility'], $_POST['access']]);
            @mail($email, 'BloodDonation staff invitation', "You have been invited to the BloodDonation console.\nEmail: $email\nTemporary password: $tmp\nUse 'Forgot Password' to set your own.", 'From: ' . MAIL_FROM);
            audit('Staff invitation sent', $email);
            flash("Invitation created for $name." . (DEV_MODE ? " Temporary password (shown once): $tmp" : ''), 'info');
        }
    } elseif ($a === 'state') {
        $id = (int)$_POST['id']; $to = $_POST['to'] ?? '';
        if ($id === (int)$u['id']) flash('You cannot change your own account state.', 'err');
        elseif (in_array($to, ['Active', 'Suspended'], true)) { q('UPDATE users SET status=? WHERE id=?', [$to, $id]); audit('Staff state changed', "User #$id → $to"); flash('Account updated.'); }
    }
    redirect('staff.php');
}

$fr = in_array($_GET['role'] ?? '', ROLES, true) ? $_GET['role'] : '';
$ff = in_array($_GET['facility'] ?? '', FACILITIES, true) ? $_GET['facility'] : '';
$w = ['1=1']; $args = [];
if ($fr) { $w[] = 'role=?'; $args[] = $fr; }
if ($ff) { $w[] = 'facility=?'; $args[] = $ff; }
$rows = q('SELECT * FROM users WHERE ' . implode(' AND ', $w) . ' ORDER BY FIELD(status,"Pending","Active","Suspended"), name', $args)->fetchAll();
$sum = q('SELECT SUM(access_level="Admin Ops") adm, SUM(access_level IN ("Serology Lab","Donation Log")) lab, COUNT(*) tot FROM users WHERE status="Active"')->fetch();

app_start('Staff & Role Management', 'Provision security clearances, assign lab or triage operations, and track clinical logs.', 'staff');
?>
<form class="card filters" method="get">
  <select name="role" data-autosubmit><option value="">Role: All Permissions</option><?php foreach (ROLES as $r): ?><option <?= $fr === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select>
  <select name="facility" data-autosubmit><option value="">Facility: All</option><?php foreach (FACILITIES as $r): ?><option <?= $ff === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select>
  <span class="spacer"></span>
  <?php if (is_admin($u)): ?><button type="button" class="btn primary" data-open="inviteModal">+ Send Staff Invitation</button><?php endif; ?>
</form>

<div class="split">
  <div class="card table-card"><div class="scroll"><table>
    <thead><tr><th>Staff Member</th><th>Operational Role</th><th>Clearance Facility</th><th>Access Level</th><th class="r">State</th></tr></thead><tbody>
    <?php foreach ($rows as $s): $cls = ['Active' => 'ok', 'Pending' => 'warn', 'Suspended' => 'bad'][$s['status']]; ?>
      <tr><td><div class="person"><?= avatar($s) ?><b><?= e($s['name']) ?></b></div></td>
        <td><?= e($s['role']) ?></td><td class="muted"><?= e($s['facility']) ?></td><td><b class="note bad"><?= e($s['access_level']) ?></b></td>
        <td class="r nowrap"><span class="badge <?= $cls ?>"><?= e($s['status']) ?></span>
          <?php if (is_admin($u) && $s['id'] != $u['id']): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="state"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="to" value="<?= $s['status'] === 'Active' ? 'Suspended' : 'Active' ?>">
            <button class="btn sm"><?= $s['status'] === 'Active' ? 'Suspend' : ($s['status'] === 'Pending' ? 'Approve' : 'Reactivate') ?></button></form><?php endif; ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">No staff match.</td></tr><?php endif; ?>
  </tbody></table></div></div>
  <aside class="card"><h2>Operational Security Summary</h2>
    <p class="muted mt">Admin Cleared</p><h3 class="big"><?= (int)$sum['adm'] ?> Users</h3>
    <p class="muted">Lab / Serology Write Access</p><h3 class="big"><?= (int)$sum['lab'] ?> Users</h3>
    <p class="muted">HIPAA Compliant Log Access</p><h3 class="big"><?= (int)$sum['tot'] ?> Users</h3>
    <hr><b class="note warn">System Access Compliance Note</b>
    <p class="muted">Clearance states must match institutional licenses. Bi-weekly validation audits are enforced.</p></aside>
</div>

<?php if (is_admin($u)): modal_start('inviteModal', 'Send Staff Invitation'); ?>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="invite">
  <label class="full">Full Name<input name="name" required></label>
  <label class="full">Work Email<input type="email" name="email" required></label>
  <label>Operational Role<select name="role"><?php foreach (ROLES as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></label>
  <label>Access Level<select name="access"><?php foreach (ACCESS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></label>
  <label class="full">Clearance Facility<select name="facility"><?php foreach (FACILITIES as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></label>
  <div class="full actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn primary">Send Invitation</button></div>
</form>
<?php modal_end(); endif; app_end();
