<?php
require_once __DIR__ . '/../config/config.php';
requireAdmin();
$pageTitle = 'ฐานข้อมูลโรคพืช';
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    requireCsrf('diseases.php');
    $id = (int) ($_POST['disease_id'] ?? 0);
    $name = trim($_POST['disease_name'] ?? '');
    $cropType = trim($_POST['affected_crop_type'] ?? '');
    $symptoms = trim($_POST['symptoms'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    if ($name === '') {
        flash('error', 'กรุณากรอกชื่อโรค');
    } elseif ($id > 0) {
        $pdo->prepare('UPDATE plant_diseases SET disease_name=?, affected_crop_type=?, symptoms=?, treatment=? WHERE disease_id=?')
            ->execute([$name, $cropType, $symptoms, $treatment, $id]);
        flash('success', 'แก้ไขข้อมูลโรคพืชสำเร็จ');
    } else {
        $pdo->prepare('INSERT INTO plant_diseases (disease_name, affected_crop_type, symptoms, treatment) VALUES (?,?,?,?)')
            ->execute([$name, $cropType, $symptoms, $treatment]);
        flash('success', 'เพิ่มโรคพืชสำเร็จ');
    }
    redirect('diseases.php');
}

if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM plant_diseases WHERE disease_id=?')->execute([(int) $_GET['delete']]);
    flash('success', 'ลบข้อมูลโรคพืชสำเร็จ');
    redirect('diseases.php');
}

$diseases = $pdo->query('SELECT * FROM plant_diseases ORDER BY disease_name')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar_admin.php'; ?>
  <main class="main-content">
    <?php require __DIR__ . '/../includes/topbar.php'; ?>
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-primary-app" data-bs-toggle="modal" data-bs-target="#disModal" onclick="openDisModal()"><i class="bi bi-plus-circle me-1"></i> เพิ่มโรคพืช</button>
    </div>
    <div class="app-card p-3">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>ชื่อโรค</th><th>พืชที่กระทบ</th><th>อาการ</th><th class="text-end">จัดการ</th></tr></thead>
          <tbody>
            <?php foreach ($diseases as $d): ?>
              <tr>
                <td class="fw-semibold"><?= e($d['disease_name']) ?></td>
                <td><span class="badge badge-soft-amber"><?= e($d['affected_crop_type'] ?: '-') ?></span></td>
                <td class="small text-muted"><?= e(mb_substr($d['symptoms'] ?? '', 0, 60)) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-app" data-bs-toggle="modal" data-bs-target="#disModal" onclick='openDisModal(<?= json_encode($d, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                  <a class="btn btn-sm btn-outline-danger" href="?delete=<?= $d['disease_id'] ?>" onclick="return confirm('ยืนยันการลบ?')"><i class="bi bi-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="disModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;">
      <form method="post">
        <?= csrfField() ?>
        <div class="modal-header"><h5 class="modal-title" id="disModalTitle">เพิ่มโรคพืช</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="disease_id" id="d_disease_id" value="">
          <div class="row">
            <div class="col-md-7 mb-3"><label class="form-label">ชื่อโรค</label><input type="text" name="disease_name" id="d_disease_name" class="form-control" required></div>
            <div class="col-md-5 mb-3"><label class="form-label">พืชที่กระทบ</label><input type="text" name="affected_crop_type" id="d_affected_crop_type" class="form-control" placeholder="เช่น พืชไร่"></div>
          </div>
          <div class="mb-3"><label class="form-label">อาการ</label><textarea name="symptoms" id="d_symptoms" class="form-control" rows="2"></textarea></div>
          <div class="mb-3"><label class="form-label">การรักษา/ป้องกัน</label><textarea name="treatment" id="d_treatment" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary-app w-100">บันทึก</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function openDisModal(data) {
  document.getElementById('disModalTitle').innerText = data ? 'แก้ไขข้อมูลโรคพืช' : 'เพิ่มโรคพืช';
  document.getElementById('d_disease_id').value = data ? data.disease_id : '';
  document.getElementById('d_disease_name').value = data ? data.disease_name : '';
  document.getElementById('d_affected_crop_type').value = data ? (data.affected_crop_type || '') : '';
  document.getElementById('d_symptoms').value = data ? (data.symptoms || '') : '';
  document.getElementById('d_treatment').value = data ? (data.treatment || '') : '';
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
