<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/layout.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    $type = $_POST['blood_type'] ?? '';
    $units = (int)($_POST['units'] ?? 0);
    $have = in_array($type, BLOOD_TYPES, true) ? (int)q('SELECT units FROM blood_inventory WHERE blood_type=?', [$type])->fetchColumn() : -1;
    if ($have < 0 || $units < 1) flash('Choose a blood type and a unit count of at least 1.', 'err');
    elseif ($a === 'transfer' || $a === 'discrepancy') {
        if ($units > $have) flash("Only $have units of $type are in stock.", 'err');
        else {
            db()->beginTransaction();
            try {
                q('UPDATE blood_inventory SET units = units - ? WHERE blood_type=?', [$units, $type]);
                q('UPDATE storage_locations SET units = GREATEST(0, units - ?) WHERE id = (SELECT id FROM (SELECT MIN(id) id FROM storage_locations) t)', [$units]);
                if ($a === 'transfer') {
                    $dest = trim($_POST['detail'] ?? '') ?: 'external facility';
                    q('INSERT INTO inventory_movements (description,type,blood_type,units) VALUES (?,?,?,?)', ["Transferred $units Units of $type to $dest", 'Dispatch', $type, $units]);
                    audit('Stock transfer', "$units × $type → $dest");
                } else {
                    $why = trim($_POST['detail'] ?? '') ?: 'discrepancy';
                    q('INSERT INTO inventory_movements (description,type,blood_type,units) VALUES (?,?,?,?)', ["Discarded $units Units of $type ($why)", 'Waste', $type, $units]);
                    audit('Inventory discrepancy logged', "$units × $type – $why");
                }
                db()->commit(); flash($a === 'transfer' ? "Transferred $units units of $type." : "Logged $units discarded units of $type.");
            } catch (Throwable $e) { db()->rollBack(); flash('Could not update inventory.', 'err'); }
        }
    } elseif ($a === 'intake') {
        q('UPDATE blood_inventory SET units = units + ? WHERE blood_type=?', [$units, $type]);
        q('UPDATE storage_locations SET units = units + ? WHERE id = (SELECT id FROM (SELECT MIN(id) id FROM storage_locations) t)', [$units]);
        $src = trim($_POST['detail'] ?? '') ?: 'HQ Central';
        q('INSERT INTO inventory_movements (description,type,blood_type,units) VALUES (?,?,?,?)', ["Collected $units Units of $type from $src", 'Intake', $type, $units]);
        audit('Stock intake', "$units × $type from $src"); flash("Added $units units of $type.");
    }
    redirect('inventory.php');
}

$inv = q('SELECT blood_type, units FROM blood_inventory ORDER BY FIELD(blood_type,"O+","O-","A+","A-","B+","B-","AB+","AB-")')->fetchAll();
$locs = q('SELECT * FROM storage_locations ORDER BY id')->fetchAll();
$moves = q('SELECT * FROM inventory_movements ORDER BY created_at DESC, id DESC LIMIT 8')->fetchAll();
$mc = ['Dispatch' => 'critical', 'Intake' => 'stable', 'Waste' => 'critical'];

function stock_form(string $action, string $detailLabel, string $btn, string $ph): void { ?>
  <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $action ?>">
    <label>Blood Type<select name="blood_type"><?php foreach (BLOOD_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></label>
    <label>Units<input type="number" name="units" min="1" value="1" required></label>
    <label class="full"><?= e($detailLabel) ?><input name="detail" placeholder="<?= e($ph) ?>"></label>
    <div class="full actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn primary"><?= e($btn) ?></button></div>
  </form><?php }

app_start('Blood Inventory Management', 'Real-time antigen stock logs, safety reserve floor metrics, and dispatch operations.', 'inventory');
?>
<div class="card">
  <div class="card-head"><div><h2>HQ Central Blood Bank Inventory</h2><p class="muted">Units in reserve by antigen group. Targeted floor: <?= e(setting('inventory_floor')) ?> units.</p></div>
    <div class="btns"><button class="btn" data-open="intakeModal">Log Intake</button><button class="btn" data-open="discModal">Discrepancy Log</button><button class="btn primary" data-open="transferModal">Transfer Stock</button></div></div>
  <div class="inv-grid g4">
    <?php foreach ($inv as $r): [$lab, $cls] = stock_status((int)$r['units']); ?>
    <div class="inv"><div class="inv-top"><b><?= e($r['blood_type']) ?></b><span class="tag <?= $cls ?>"><?= $lab ?></span></div>
      <div class="inv-n"><b><?= (int)$r['units'] ?></b> Units</div>
      <div class="bar <?= $cls ?>"><i style="width:<?= min(100, round($r['units'] / 180 * 100)) ?>%"></i></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid2 even">
  <div class="card"><div class="card-head"><h2>Distribution by Storage Location</h2></div>
    <?php foreach ($locs as $l): ?>
    <div class="row-item"><div><b><?= e($l['name']) ?></b><small>Verified temperature: <?= e($l['temperature']) ?></small></div>
      <span class="grow"></span><b><?= number_format($l['units']) ?> units</b><span class="tag stable"><?= (int)$l['capacity_pct'] ?>%</span></div>
    <?php endforeach; ?></div>
  <div class="card"><div class="card-head"><h2>Recent Movements &amp; Dispatches</h2></div>
    <?php foreach ($moves as $m): ?>
    <div class="row-item"><div><b class="plain"><?= e($m['description']) ?></b><small><?= e(time_ago($m['created_at'])) ?></small></div>
      <span class="grow"></span><span class="tag <?= $mc[$m['type']] ?>"><?= e($m['type']) ?></span></div>
    <?php endforeach; if (!$moves) echo '<p class="muted">No movements recorded.</p>'; ?></div>
</div>

<?php modal_start('transferModal', 'Transfer Stock'); stock_form('transfer', 'Destination', 'Transfer', 'e.g. City Hospital'); modal_end();
      modal_start('discModal', 'Log Inventory Discrepancy'); stock_form('discrepancy', 'Reason', 'Log Discard', 'e.g. Expired / Testing Flag'); modal_end();
      modal_start('intakeModal', 'Log Intake'); stock_form('intake', 'Source', 'Add Stock', 'e.g. Campus Drive'); modal_end();
app_end();
