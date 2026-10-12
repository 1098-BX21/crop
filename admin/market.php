<?php
require_once __DIR__ . '/../config/config.php';
requireAdmin();
$pageTitle = 'ราคาตลาด';
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    requireCsrf('market.php');
    $id = (int) ($_POST['market_id'] ?? 0);
    $cropId = (int) ($_POST['crop_id'] ?? 0);
    $price = (float) ($_POST['market_price'] ?? 0);
    $date = $_POST['record_date'] ?: date('Y-m-d');
    if ($cropId === 0 || $price <= 0) {
        flash('error', 'กรุณากรอกข้อมูลให้ถูกต้อง');
    } elseif ($id > 0) {
        $pdo->prepare('UPDATE market_data SET crop_id=?, market_price=?, record_date=? WHERE market_id=?')->execute([$cropId, $price, $date, $id]);
        flash('success', 'แก้ไขราคาสำเร็จ');
    } else {
        $pdo->prepare('INSERT INTO market_data (crop_id, market_price, record_date) VALUES (?,?,?)')->execute([$cropId, $price, $date]);
        flash('success', 'เพิ่มราคาสำเร็จ');
    }
    redirect('market.php');
}

if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM market_data WHERE market_id=?')->execute([(int) $_GET['delete']]);
    flash('success', 'ลบราคาสำเร็จ');
    redirect('market.php');
}

$crops = $pdo->query('SELECT crop_id, crop_name FROM crops ORDER BY crop_name')->fetchAll();

[$perPage, $offset, $currentPage] = getPagination(15);
$total = (int) $pdo->query('SELECT COUNT(*) FROM market_data')->fetchColumn();
$stmt = $pdo->prepare('SELECT m.*, c.crop_name FROM market_data m JOIN crops c ON c.crop_id=m.crop_id ORDER BY m.record_date DESC, m.market_id DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$prices = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar_admin.php'; ?>
  <main class="main-content">
    <?php require __DIR__ . '/../includes/topbar.php'; ?>
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-primary-app" data-bs-toggle="modal" data-bs-target="#marketModal" onclick="openMarketModal()"><i class="bi bi-plus-circle me-1"></i> เพิ่มราคา</button>
    </div>
    <div class="app-card p-3">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>พืช</th><th>ราคา (บาท/กก.)</th><th>วันที่</th><th class="text-end">จัดการ</th></tr></thead>
          <tbody>
            <?php foreach ($prices as $p): ?>
              <tr>
                <td class="fw-semibold"><?= e($p['crop_name']) ?></td>
                <td><span class="badge badge-soft-green"><?= number_format($p['market_price'], 2) ?></span></td>
                <td class="small"><?= formatDateThai($p['record_date']) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-app" data-bs-toggle="modal" data-bs-target="#marketModal" onclick='openMarketModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                  <a class="btn btn-sm btn-outline-danger" href="?delete=<?= $p['market_id'] ?>" onclick="return confirm('ยืนยันการลบราคานี้?')"><i class="bi bi-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= renderPagination($total, $perPage, $currentPage) ?>
    </div>
  </main>
</div>

<div class="modal fade" id="marketModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:16px;">
      <form method="post">
        <?= csrfField() ?>
        <div class="modal-header"><h5 class="modal-title" id="marketModalTitle">เพิ่มราคา</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="market_id" id="m_market_id" value="">
          <div class="mb-3"><label class="form-label">พืช</label>
            <select name="crop_id" id="m_crop_id" class="form-select" required>
              <?php foreach ($crops as $c): ?><option value="<?= $c['crop_id'] ?>"><?= e($c['crop_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">ราคา (บาท/กก.)</label><input type="number" step="0.01" name="market_price" id="m_market_price" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">วันที่</label><input type="date" name="record_date" id="m_record_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary-app w-100">บันทึกราคา</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function openMarketModal(data) {
  document.getElementById('marketModalTitle').innerText = data ? 'แก้ไขราคา' : 'เพิ่มราคา';
  document.getElementById('m_market_id').value = data ? data.market_id : '';
  document.getElementById('m_market_price').value = data ? data.market_price : '';
  document.getElementById('m_record_date').value = data ? data.record_date : '<?= date('Y-m-d') ?>';
  if (data) document.getElementById('m_crop_id').value = data.crop_id;
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
