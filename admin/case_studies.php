<?php
require_once __DIR__ . '/../config/config.php';
requireAdmin();
$pageTitle = 'จัดการกรณีศึกษา';
$pdo = getDBConnection();

// ---- อนุมัติ / ยกเลิกอนุมัติ ----
if (isset($_GET['approve'])) {
    $pdo->prepare('UPDATE recommendation_feedback SET admin_approved = 1 WHERE feedback_id = ?')->execute([(int) $_GET['approve']]);
    logActivity(currentUser()['user_id'], 'approve_case_study', 'feedback#' . (int) $_GET['approve']);
    flash('success', 'อนุมัติให้แสดงบนเว็บแล้ว');
    redirect('case_studies.php');
}
if (isset($_GET['unapprove'])) {
    $pdo->prepare('UPDATE recommendation_feedback SET admin_approved = 0 WHERE feedback_id = ?')->execute([(int) $_GET['unapprove']]);
    flash('success', 'ซ่อนออกจากหน้าเว็บแล้ว');
    redirect('case_studies.php');
}
if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM recommendation_feedback WHERE feedback_id = ?')->execute([(int) $_GET['delete']]);
    logActivity(currentUser()['user_id'], 'delete_case_study', 'feedback#' . (int) $_GET['delete']);
    flash('success', 'ลบรายการแล้ว');
    redirect('case_studies.php');
}

// ---- ตัวกรอง ----
$filter = $_GET['filter'] ?? 'pending'; // pending | approved | all
$where = 'WHERE fb.is_public = 1'; // เฉพาะที่เกษตรกรยินยอมให้เผยแพร่เท่านั้นถึงจะต้องตรวจสอบ
if ($filter === 'pending') $where .= ' AND fb.admin_approved = 0';
if ($filter === 'approved') $where .= ' AND fb.admin_approved = 1';

$items = $pdo->query("
    SELECT fb.*, r.recommended_crop, r.reason, u.full_name,
           DATEDIFF(gp.harvest_date, gp.start_date) + 1 AS plan_days,
           (SELECT MAX(s.day_to) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id) AS plan_total_days,
           gp.yield_amount AS plan_yield,
           (SELECT COUNT(*) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id AND s.is_done = 1) AS plan_done,
           (SELECT COUNT(*) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id AND s.is_skipped = 1) AS plan_skipped,
           (SELECT COUNT(*) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id) AS plan_stage_count,
           (SELECT GROUP_CONCAT(CONCAT(s.stage_name, ': ', s.skip_reason) SEPARATOR ' | ') FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id AND s.is_skipped = 1) AS plan_skip_list
    FROM recommendation_feedback fb
    JOIN ai_recommendations r ON r.recommend_id = fb.recommend_id
    JOIN users u ON u.user_id = fb.user_id
    LEFT JOIN grow_plans gp ON gp.plan_id = (
        SELECT g2.plan_id FROM grow_plans g2 WHERE g2.recommend_id = fb.recommend_id AND g2.user_id = fb.user_id AND g2.status = 'harvested'
        ORDER BY g2.plan_id DESC LIMIT 1)
    $where
    ORDER BY fb.created_at DESC
")->fetchAll();

$counts = $pdo->query("
    SELECT
      SUM(CASE WHEN is_public=1 AND admin_approved=0 THEN 1 ELSE 0 END) AS pending,
      SUM(CASE WHEN is_public=1 AND admin_approved=1 THEN 1 ELSE 0 END) AS approved
    FROM recommendation_feedback
")->fetch();

require __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar_admin.php'; ?>
  <main class="main-content">
    <?php require __DIR__ . '/../includes/topbar.php'; ?>

    <div class="ai-box mb-4">
      <h6 class="fw-bold mb-1"><i class="bi bi-patch-check text-success"></i> จัดการกรณีศึกษาที่แสดงบนเว็บ</h6>
      <p class="small text-muted mb-0">
        เกษตรกรกด "ยินยอมเผยแพร่" เองในหน้าให้ผลตอบกลับ แต่จะ<strong>ยังไม่ขึ้นหน้าเว็บจริง</strong>จนกว่าผู้ดูแลระบบจะกดอนุมัติที่นี่ก่อน
        เพื่อตรวจสอบความเหมาะสมของเนื้อหา — ข้อมูลที่แสดงจะไม่ระบุชื่อ/ที่อยู่เกษตรกรเสมอ
      </p>
    </div>

    <div class="d-flex gap-2 mb-4">
      <a href="?filter=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary-app' : 'btn-outline-app' ?>">
        รออนุมัติ <span class="badge bg-light text-dark ms-1"><?= (int) $counts['pending'] ?></span>
      </a>
      <a href="?filter=approved" class="btn btn-sm <?= $filter === 'approved' ? 'btn-primary-app' : 'btn-outline-app' ?>">
        อนุมัติแล้ว (แสดงบนเว็บ) <span class="badge bg-light text-dark ms-1"><?= (int) $counts['approved'] ?></span>
      </a>
      <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary-app' : 'btn-outline-app' ?>">ทั้งหมดที่ยินยอมเผยแพร่</a>
    </div>

    <?php if (!$items): ?>
      <div class="app-card p-4 text-center text-muted">ไม่มีรายการในหมวดนี้</div>
    <?php endif; ?>

    <?php foreach ($items as $it): ?>
      <div class="app-card p-4 mb-3">
        <div class="row">
          <div class="col-md-8">
            <div class="d-flex align-items-center gap-2 mb-1">
              <h6 class="fw-bold mb-0">🌾 <?= e($it['recommended_crop']) ?></h6>
              <?php if ($it['admin_approved']): ?>
                <span class="badge badge-soft-green">แสดงบนเว็บอยู่</span>
              <?php else: ?>
                <span class="badge badge-soft-amber">รออนุมัติ</span>
              <?php endif; ?>
              <?php if ($it['followed']): ?><span class="badge badge-soft-blue">ทำตามคำแนะนำ</span><?php endif; ?>
            </div>
            <p class="small text-muted mb-2">
              โดย <?= e($it['full_name']) ?> (เห็นเฉพาะ Admin — หน้าเว็บสาธารณะจะไม่แสดงชื่อนี้)
              · <?= formatDateThai($it['created_at']) ?>
              <?php if ($it['outcome_rating']): ?> · <?= str_repeat('⭐', (int) $it['outcome_rating']) ?><?php endif; ?>
            </p>
            <p class="mb-2"><?= nl2br(e($it['outcome_description'] ?: '(ไม่ได้กรอกรายละเอียด)')) ?></p>
            <?php if ($it['yield_amount'] || $it['plan_yield']): ?><p class="small text-muted mb-0">ผลผลิต: <?= number_format((float) ($it['yield_amount'] ?: $it['plan_yield']), 2) ?> กก.</p><?php endif; ?>
            <?php if ($it['plan_days']): ?><p class="small text-success mb-0"><i class="bi bi-calendar2-check"></i> มีแผนการปลูกจริงรองรับ: ปลูก <?= (int) $it['plan_days'] ?> วัน<?= $it['plan_total_days'] ? ' (แผนประมาณ ' . (int) $it['plan_total_days'] . ' วัน)' : '' ?> — ข้อมูลนี้มาจากการบันทึกเก็บเกี่ยวในระบบ ไม่ใช่การกรอกเอง</p>
              <?php if ($it['plan_done'] + $it['plan_skipped'] > 0): ?><p class="small text-muted mb-0"><i class="bi bi-list-check"></i> ทำตามแผน <?= (int) $it['plan_done'] ?>/<?= (int) $it['plan_stage_count'] ?> ระยะ<?= $it['plan_skipped'] ? ' · ไม่ได้ทำตาม ' . (int) $it['plan_skipped'] . ' ระยะ' : '' ?></p><?php endif; ?>
              <?php if ($it['plan_skip_list']): ?><p class="small text-muted mb-0"><i class="bi bi-skip-forward"></i> เหตุผลที่ข้าม (เห็นเฉพาะผู้ดูแล): <?= e($it['plan_skip_list']) ?></p><?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <?php if ($it['result_image']): ?>
              <img src="<?= BASE_URL ?>/uploads/feedback/<?= e($it['result_image']) ?>" class="rounded-3 w-100 mb-2" style="height:140px; object-fit:cover;" alt="ผลลัพธ์">
            <?php endif; ?>
            <div class="d-flex gap-2">
              <?php if (!$it['admin_approved']): ?>
                <a href="?approve=<?= $it['feedback_id'] ?>&filter=<?= e($filter) ?>" class="btn btn-sm btn-primary-app flex-fill"><i class="bi bi-check-lg"></i> อนุมัติ</a>
              <?php else: ?>
                <a href="?unapprove=<?= $it['feedback_id'] ?>&filter=<?= e($filter) ?>" class="btn btn-sm btn-outline-app flex-fill"><i class="bi bi-eye-slash"></i> ซ่อน</a>
              <?php endif; ?>
              <a href="?delete=<?= $it['feedback_id'] ?>&filter=<?= e($filter) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันการลบรายการนี้? ลบแล้วกู้คืนไม่ได้')"><i class="bi bi-trash"></i></a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
