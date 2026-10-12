<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'กรณีศึกษาจากเกษตรกร';
$pdo = getDBConnection();

// สำคัญ: ดึงเฉพาะฟิลด์ที่ไม่ระบุตัวตน ไม่ JOIN ตาราง users/farms เพื่อความเป็นส่วนตัว
// แสดงเฉพาะรายการที่เกษตรกรยินยอม (is_public=1) และผู้ดูแลระบบอนุมัติแล้ว (admin_approved=1)
$filterCrop = trim($_GET['crop'] ?? '');

$sql = "
    SELECT fb.feedback_id, fb.followed, fb.outcome_rating, fb.outcome_description,
           COALESCE(fb.yield_amount, gp.yield_amount) AS yield_amount, fb.result_image, fb.created_at,
           r.recommended_crop, r.reason,
           DATEDIFF(gp.harvest_date, gp.start_date) + 1 AS plan_days,
           (SELECT MAX(s.day_to) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id) AS plan_total_days,
           1 AS has_plan,
           (SELECT COUNT(*) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id AND s.is_done = 1) AS plan_done,
           (SELECT COUNT(*) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id AND s.is_skipped = 1) AS plan_skipped,
           (SELECT COUNT(*) FROM grow_plan_stages s WHERE s.plan_id = gp.plan_id) AS plan_stage_count
    FROM recommendation_feedback fb
    JOIN ai_recommendations r ON r.recommend_id = fb.recommend_id
    LEFT JOIN grow_plans gp ON gp.plan_id = (
        SELECT g2.plan_id FROM grow_plans g2 WHERE g2.recommend_id = fb.recommend_id AND g2.user_id = fb.user_id AND g2.status = 'harvested'
        ORDER BY g2.plan_id DESC LIMIT 1)
    WHERE fb.is_public = 1 AND fb.admin_approved = 1
";
$params = [];
if ($filterCrop !== '') {
    $sql .= " AND r.recommended_crop = ?";
    $params[] = $filterCrop;
}
$sql .= " ORDER BY fb.created_at DESC LIMIT 60";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll();

$crops = $pdo->query("
    SELECT DISTINCT r.recommended_crop
    FROM recommendation_feedback fb JOIN ai_recommendations r ON r.recommend_id = fb.recommend_id
    WHERE fb.is_public = 1 AND fb.admin_approved = 1 ORDER BY r.recommended_crop
")->fetchAll(PDO::FETCH_COLUMN);

$summary = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(followed) AS followed_count,
           AVG(NULLIF(outcome_rating,0)) AS avg_rating
    FROM recommendation_feedback WHERE is_public = 1 AND admin_approved = 1
")->fetch();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar_public.php';
?>
<div class="container py-5">
  <div class="text-center mb-5">
    <h2 class="fw-bold"><i class="bi bi-people-fill text-success"></i> กรณีศึกษาจากเกษตรกรจริง</h2>
    <p class="text-muted">ผลลัพธ์จริงจากเกษตรกรที่นำคำแนะนำของ AI ไปใช้ แบ่งปันโดยสมัครใจ (ไม่แสดงชื่อหรือที่อยู่ฟาร์ม)</p>
  </div>

  <div class="row g-3 mb-4 text-center">
    <div class="col-md-4">
      <div class="app-card p-3"><div class="fs-3 fw-bold text-success"><?= (int) $summary['total'] ?></div><div class="small text-muted">กรณีศึกษาทั้งหมด</div></div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-3"><div class="fs-3 fw-bold text-success"><?= (int) $summary['followed_count'] ?></div><div class="small text-muted">ทำตามคำแนะนำจริง</div></div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-3"><div class="fs-3 fw-bold text-success"><?= $summary['avg_rating'] ? number_format($summary['avg_rating'], 1) : '-' ?> ⭐</div><div class="small text-muted">คะแนนความพึงพอใจเฉลี่ย</div></div>
    </div>
  </div>

  <?php if ($crops): ?>
  <div class="mb-4">
    <form method="get" class="d-flex gap-2 align-items-center">
      <label class="small text-muted">กรองตามพืช:</label>
      <select name="crop" class="form-select form-select-sm" style="max-width:260px;" onchange="this.form.submit()">
        <option value="">— ทุกชนิดพืช —</option>
        <?php foreach ($crops as $c): ?>
          <option value="<?= e($c) ?>" <?= $filterCrop === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($cases as $c): ?>
      <div class="col-md-6 col-lg-4">
        <div class="app-card p-4 h-100 d-flex flex-column">
          <?php if ($c['result_image']): ?>
            <img src="<?= BASE_URL ?>/uploads/feedback/<?= e($c['result_image']) ?>" class="rounded-3 mb-3" style="width:100%; height:180px; object-fit:cover;" alt="ผลลัพธ์">
          <?php endif; ?>
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="fw-bold mb-0">🌾 <?= e($c['recommended_crop']) ?></h6>
            <?php if ($c['followed']): ?><span class="badge badge-soft-green">ทำตามคำแนะนำ</span><?php endif; ?>
          </div>
          <?php if ($c['outcome_rating']): ?>
            <div class="small mb-2"><?= str_repeat('⭐', (int) $c['outcome_rating']) ?></div>
          <?php endif; ?>
          <p class="small flex-grow-1"><?= nl2br(e($c['outcome_description'] ?: 'ไม่มีรายละเอียดเพิ่มเติม')) ?></p>
          <?php if ($c['yield_amount']): ?>
            <p class="small text-muted mb-1"><i class="bi bi-basket"></i> ผลผลิต: <?= number_format($c['yield_amount'], 2) ?> กก.</p>
          <?php endif; ?>
          <?php if ($c['plan_days']): ?>
            <p class="small text-muted mb-1"><i class="bi bi-calendar2-check"></i> ปลูกจริง <?= (int) $c['plan_days'] ?> วัน<?= $c['plan_total_days'] ? ' (แผนประมาณ ' . (int) $c['plan_total_days'] . ' วัน)' : '' ?></p>
            <?php if ($c['plan_done'] + $c['plan_skipped'] > 0): ?>
              <p class="small text-muted mb-1"><i class="bi bi-list-check"></i> ทำตามแผน <?= (int) $c['plan_done'] ?>/<?= (int) $c['plan_stage_count'] ?> ระยะ<?= $c['plan_skipped'] ? ' · ไม่ได้ทำตาม ' . (int) $c['plan_skipped'] . ' ระยะ' : '' ?></p>
            <?php endif; ?>
          <?php endif; ?>
          <p class="small text-muted mb-0"><?= formatDateThai($c['created_at']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (!$cases): ?>
      <div class="col-12">
        <div class="text-center text-muted py-5">
          <i class="bi bi-inboxes fs-1"></i>
          <p class="mt-2">ยังไม่มีกรณีศึกษาที่เผยแพร่ในขณะนี้ — เมื่อเกษตรกรเริ่มแบ่งปันผลลัพธ์ จะแสดงที่นี่</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
