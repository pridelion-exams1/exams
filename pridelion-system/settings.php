<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);
require_admin($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['school_name','motto','address','phone','email','head_name','term_fee','term','year','streaming_mode','report_footer'];
    $st = $pdo->prepare("INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value");
    foreach ($fields as $f) {
        $st->execute([$f, trim($_POST[$f] ?? '')]);
    }
    flash('success', 'Settings saved.');
    header('Location: settings.php'); exit;
}

$settings = all_settings($pdo);
$pageTitle = 'Settings'; $activeNav = 'settings';
require __DIR__ . '/includes/layout_top.php';
?>
<h1 style="font-size:20px;margin-bottom:18px">School Settings</h1>
<div class="card">
  <div class="card-body">
    <form method="post">
      <div class="frow">
        <div class="fg"><label class="fl">School Name</label><input class="fi" name="school_name" value="<?= h($settings['school_name']) ?>"></div>
        <div class="fg"><label class="fl">Motto / Tagline</label><input class="fi" name="motto" value="<?= h($settings['motto']) ?>" placeholder="e.g. Nurturing Excellence"></div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Address</label><input class="fi" name="address" value="<?= h($settings['address']) ?>"></div>
        <div class="fg"><label class="fl">Phone</label><input class="fi" name="phone" value="<?= h($settings['phone']) ?>"></div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Email</label><input class="fi" name="email" type="email" value="<?= h($settings['email']) ?>"></div>
        <div class="fg"><label class="fl">Head of Institution Name</label><input class="fi" name="head_name" value="<?= h($settings['head_name']) ?>"></div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Default Term Fee</label><input class="fi" name="term_fee" type="number" value="<?= h($settings['term_fee']) ?>"></div>
        <div class="fg"><label class="fl">Current Term</label>
          <select class="fs" name="term">
            <?php foreach (['Term 1','Term 2','Term 3'] as $t): ?><option value="<?= $t ?>" <?= $settings['term'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Current Year</label><input class="fi" name="year" type="number" value="<?= h($settings['year']) ?>"></div>
        <div class="fg"><label class="fl">Streaming Mode</label>
          <select class="fs" name="streaming_mode">
            <option value="multi" <?= $settings['streaming_mode'] === 'multi' ? 'selected' : '' ?>>Multi Stream (A, B, C...)</option>
            <option value="single" <?= $settings['streaming_mode'] === 'single' ? 'selected' : '' ?>>Single Stream (one class per grade)</option>
          </select>
        </div>
      </div>
      <div class="fg"><label class="fl">Default Report Footer</label><input class="fi" name="report_footer" value="<?= h($settings['report_footer']) ?>" placeholder="e.g. motto or contact info printed at the bottom of reports"></div>
      <button class="btn btn-primary" type="submit">Save Settings</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
