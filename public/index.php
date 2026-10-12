<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'หน้าแรก';

$pdo = getDBConnection();
$articles = $pdo->query('SELECT * FROM articles ORDER BY created_at DESC LIMIT 3')->fetchAll();
$caseStudies = $pdo->query("
  SELECT fb.outcome_description, fb.outcome_rating, fb.created_at, r.recommended_crop,
         DATEDIFF(gp.harvest_date, gp.start_date) + 1 AS plan_days
  FROM recommendation_feedback fb JOIN ai_recommendations r ON r.recommend_id = fb.recommend_id
  LEFT JOIN grow_plans gp ON gp.plan_id = (
        SELECT g2.plan_id FROM grow_plans g2 WHERE g2.recommend_id = fb.recommend_id AND g2.user_id = fb.user_id AND g2.status = 'harvested'
        ORDER BY g2.plan_id DESC LIMIT 1)
  WHERE fb.is_public = 1 AND fb.admin_approved = 1
  ORDER BY fb.created_at DESC LIMIT 3
")->fetchAll();
$marketRows = $pdo->query('
  SELECT c.crop_name, m.market_price, m.record_date
  FROM market_data m JOIN crops c ON c.crop_id = m.crop_id
  WHERE m.record_date = (SELECT MAX(record_date) FROM market_data m2 WHERE m2.crop_id = m.crop_id)
  ORDER BY m.record_date DESC LIMIT 6
')->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar_public.php';
?>
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="hero-badge mb-3"><i class="bi bi-cpu me-1"></i> ขับเคลื่อนด้วย AI ที่รันบนเครื่อง ข้อมูลไม่ออกนอกระบบ</span>
        <h1 class="mt-3 mb-3">ระบบวิเคราะห์การปลูก<br>พืชหมุนเวียนด้วย AI</h1>
        <p class="lead mb-4">วางแผนปลูกพืชหมุนเวียน ฟื้นฟูดิน วินิจฉัยโรคพืชเบื้องต้นจากภาพถ่าย พร้อมคำแนะนำอ้างอิงเอกสารวิชาการเกษตรผ่านเทคโนโลยี RAG</p>
        <div class="d-flex gap-3">
          <a href="register.php" class="btn btn-hero-cta px-4">เริ่มใช้งานฟรี <i class="bi bi-arrow-right"></i></a>
          <a href="articles.php" class="btn btn-outline-light px-4">อ่านบทความ</a>
        </div>
      </div>
      <div class="col-lg-5 text-center">
        <i class="bi bi-flower3" style="font-size: 14rem; opacity:.25;"></i>
      </div>
    </div>
  </div>
</section>

<section class="container py-5">
  <div class="row g-4 text-center">
    <div class="col-md-3 col-6">
      <div class="app-card p-4 h-100">
        <i class="bi bi-clipboard-data text-success fs-2"></i>
        <h6 class="fw-bold mt-2">จัดการฟาร์ม</h6>
        <p class="small text-muted mb-0">บันทึกแปลง ค่าดิน และประวัติการปลูกครบวงจร</p>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="app-card p-4 h-100">
        <i class="bi bi-stars text-success fs-2"></i>
        <h6 class="fw-bold mt-2">แนะนำพืชหมุนเวียน</h6>
        <p class="small text-muted mb-0">AI วิเคราะห์และเสนอชนิดพืชที่เหมาะกับดิน</p>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="app-card p-4 h-100">
        <i class="bi bi-camera text-success fs-2"></i>
        <h6 class="fw-bold mt-2">วินิจฉัยโรคพืช</h6>
        <p class="small text-muted mb-0">อัปโหลดภาพ ให้ AI วิเคราะห์ภาพเบื้องต้น</p>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="app-card p-4 h-100">
        <i class="bi bi-journal-richtext text-success fs-2"></i>
        <h6 class="fw-bold mt-2">อ้างอิงวิชาการ (RAG)</h6>
        <p class="small text-muted mb-0">คำแนะนำอ้างอิงคู่มือเกษตรจริง ลด Hallucination</p>
      </div>
    </div>
  </div>
</section>

<section class="container py-4" id="prices">
  <h4 class="fw-bold mb-4"><i class="bi bi-graph-up-arrow text-success"></i> ราคาพืชผลตลาดล่าสุด</h4>
  <div class="row g-3">
    <?php foreach ($marketRows as $m): ?>
      <div class="col-md-4 col-6">
        <div class="app-card p-3 d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold"><?= e($m['crop_name']) ?></div>
            <div class="small text-muted"><?= formatDateThai($m['record_date']) ?></div>
          </div>
          <div class="fs-5 fw-bold text-success"><?= number_format($m['market_price'], 2) ?> ฿/กก.</div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$marketRows): ?><p class="text-muted">ยังไม่มีข้อมูลราคาตลาด</p><?php endif; ?>
  </div>
</section>

<?php if ($caseStudies): ?>
<section class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people-fill text-success"></i> กรณีศึกษาจากเกษตรกรจริง</h4>
    <a href="case_studies.php" class="small text-success">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="row g-4">
    <?php foreach ($caseStudies as $cs): ?>
      <div class="col-md-4">
        <div class="app-card p-4 h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="fw-bold mb-0">🌾 <?= e($cs['recommended_crop']) ?></h6>
            <?php if ($cs['outcome_rating']): ?><span class="small"><?= str_repeat('⭐', (int) $cs['outcome_rating']) ?></span><?php endif; ?>
          </div>
          <p class="small text-muted"><?= e(mb_substr($cs['outcome_description'] ?: 'เกษตรกรนำคำแนะนำไปใช้จริงแล้ว', 0, 120)) ?>...</p>
          <?php if ($cs['plan_days']): ?><p class="small text-muted mb-1"><i class="bi bi-calendar2-check"></i> ปลูกจริง <?= (int) $cs['plan_days'] ?> วัน</p><?php endif; ?>
          <span class="small text-success"><?= formatDateThai($cs['created_at']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="container py-5">
  <h4 class="fw-bold mb-4"><i class="bi bi-newspaper text-success"></i> ข่าวสาร/บทความความรู้การเกษตร</h4>
  <div class="row g-4">
    <?php foreach ($articles as $a): ?>
      <div class="col-md-4">
        <div class="app-card p-4 h-100">
          <h6 class="fw-bold"><?= e($a['title']) ?></h6>
          <p class="small text-muted"><?= e(mb_substr($a['content'], 0, 120)) ?>...</p>
          <span class="small text-success"><?= formatDateThai($a['created_at']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$articles): ?><p class="text-muted">ยังไม่มีบทความ</p><?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
