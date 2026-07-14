<?php
// Silent Auction item-donation form — public, no password gate (meant to be
// linked/embedded from another website so donors can submit an item directly
// instead of emailing the auction inbox).
//
// Submissions are INSERTed into donated_items_pending (its own table, see
// api.php's table-creation block) — NOT into the app's live `items` table.
// api.php's save_items does a full delete-and-reinsert of `items` every time
// the app saves its item list, so a row written here directly would be
// silently wiped out the next time staff touched anything on Load Item
// Emails. Staff instead pull these in on their own schedule via the
// "Import Donated Items" button on that screen (get_pending_donations /
// mark_donations_imported actions in api.php), which assigns a real
// item_number the same way the app's own "+ Add Item" does.
//
// Category codes/names intentionally match index.html's CATEGORIES object —
// update both places together if categories ever change.

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$CATEGORIES = [
    '100' => 'General Auto Repair / Car Items',
    '200' => 'Corvette Items',
    '300' => "Men's Items",
    '400' => "Women's Items",
    '500' => 'General Household',
    '600' => 'Framed Artwork or other Artwork to be Hung',
    '700' => 'Baskets / Gift Sets',
    '800' => 'Gift Certificates',
    '900' => 'Miscellaneous / Other',
];

// ── Minimal .env loader (mirrors api.php's — kept separate so this public
// page has no dependency on api.php's session/auth machinery) ──────────────
function donate_load_env($envFile) {
    $env = [];
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
    return $env;
}
$env = donate_load_env(__DIR__ . '/.env');

$errors = [];
$success = false;
$values = [
    'category' => '', 'description' => '', 'itemValue' => '',
    'donorName' => '', 'donorEmail' => '', 'donorPhone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['category', 'description', 'itemValue', 'donorName', 'donorEmail', 'donorPhone'] as $f) {
        $values[$f] = trim((string)($_POST[$f] ?? ''));
    }

    if (!array_key_exists($values['category'], $CATEGORIES)) $errors[] = 'Choose a valid item category.';
    if ($values['description'] === '') $errors[] = 'Description is required.';
    if ($values['itemValue'] === '') $errors[] = 'Item Value is required.';
    if ($values['donorName'] === '') $errors[] = 'Donor Name is required.';
    if ($values['donorEmail'] === '' || !filter_var($values['donorEmail'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid Donor Email is required.';

    if (!$errors) {
        try {
            $pdo = new PDO(
                "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
                $env['DB_USER'], $env['DB_PASS'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $pdo->exec("CREATE TABLE IF NOT EXISTS donated_items_pending (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                category_code    VARCHAR(10)  NOT NULL,
                category_name    VARCHAR(255),
                description      LONGTEXT,
                item_value       VARCHAR(20),
                donor_name       VARCHAR(255),
                donor_email      VARCHAR(255),
                donor_phone      VARCHAR(20),
                status           VARCHAR(20)  NOT NULL DEFAULT 'pending',
                submitted_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $pdo->prepare("INSERT INTO donated_items_pending
                (category_code, category_name, description, item_value, donor_name, donor_email, donor_phone)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $values['category'], $CATEGORIES[$values['category']], $values['description'],
                $values['itemValue'], $values['donorName'], $values['donorEmail'], $values['donorPhone'],
            ]);

            $success = true;
            $values = [
                'category' => '', 'description' => '', 'itemValue' => '',
                'donorName' => '', 'donorEmail' => '', 'donorPhone' => '',
            ];
        } catch (Exception $e) {
            error_log('[donate-item] ' . $e->getMessage());
            $errors[] = 'Could not save your donation right now — please try again in a moment.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ETCC Silent Auction — Donate an Item</title>
<style>
  :root { --red:#b0141e; --red-dark:#7d0e15; --ink:#1a1a1a; --muted:#667085; --line:#e3e6ea; --bg:#f4f6f8; --panel:#fff; --good:#147d3a; }
  * { box-sizing: border-box; }
  body { font: 15px/1.5 "Segoe UI", Arial, sans-serif; color: var(--ink); background: var(--bg); margin:0; padding: 28px 16px 60px; }
  .wrap { max-width: 560px; margin: 0 auto; }
  .logo { display:block; height:64px; width:64px; object-fit:contain; margin: 0 auto 10px; border-radius:6px; }
  h1 { font-size: 20px; text-align: center; margin: 0 0 2px; }
  .sub { text-align:center; color:var(--muted); font-size:13px; margin-bottom:22px; }
  .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 22px 24px; }
  .form-row { margin: 12px 0; }
  label { display:block; font-weight:600; font-size:13px; margin-bottom:4px; }
  input[type=text], input[type=email], input[type=tel], textarea, select {
    width:100%; padding:9px 10px; border:1px solid var(--line); border-radius:7px; font-size:14px; font-family:inherit; color: var(--ink); background: #fff;
  }
  textarea { resize:vertical; min-height:70px; }
  .btn { background: var(--red); border: 1px solid var(--red-dark); color:#fff; padding: 11px 18px; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; width:100%; margin-top:10px; }
  .btn:hover { background: var(--red-dark); }
  .errors { background:#fff5f5; border-left:4px solid var(--red); border-radius:6px; padding:10px 14px; margin-bottom:14px; color:var(--red-dark); font-size:13px; }
  .errors ul { margin:4px 0 0; padding-left:18px; }
  .success { background:#f0faf3; border-left:4px solid var(--good); border-radius:6px; padding:10px 14px; margin-bottom:14px; color:var(--good); font-size:13px; font-weight:600; }
  .footer { text-align:center; color:var(--muted); font-size:11px; margin-top:22px; }
</style>
</head>
<body>
<div class="wrap">
  <img src="Images/ETCClogoWhiteBackground.png" alt="ETCC Logo" class="logo">
  <h1>Donate an Item</h1>
  <div class="sub">East Tennessee Corvette Club Silent Auction</div>
  <div class="panel">
    <?php if ($success): ?>
      <div class="success">Thanks! Your item donation was submitted successfully. You can add another below.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="errors"><strong>Please fix the following:</strong><ul>
        <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
      </ul></div>
    <?php endif; ?>
    <form method="post" novalidate>
      <div class="form-row">
        <label for="f-category">Item Category *</label>
        <select id="f-category" name="category">
          <option value="">— choose —</option>
          <?php foreach ($CATEGORIES as $code => $label): ?>
            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $values['category'] === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label for="f-desc">Description *</label>
        <textarea id="f-desc" name="description" required><?php echo htmlspecialchars($values['description']); ?></textarea>
      </div>
      <div class="form-row">
        <label for="f-value">Item Value *</label>
        <input type="text" id="f-value" name="itemValue" placeholder="$25.00" required value="<?php echo htmlspecialchars($values['itemValue']); ?>">
      </div>
      <div class="form-row">
        <label for="f-donor">Donor Name *</label>
        <input type="text" id="f-donor" name="donorName" required value="<?php echo htmlspecialchars($values['donorName']); ?>">
      </div>
      <div class="form-row">
        <label for="f-email">Donor Email *</label>
        <input type="email" id="f-email" name="donorEmail" required value="<?php echo htmlspecialchars($values['donorEmail']); ?>">
      </div>
      <div class="form-row">
        <label for="f-phone">Donor Phone</label>
        <input type="tel" id="f-phone" name="donorPhone" placeholder="(123) 456-7890" value="<?php echo htmlspecialchars($values['donorPhone']); ?>">
      </div>
      <script>
        (function () {
          var phoneInput = document.getElementById('f-phone');
          function formatPhone(v) {
            var digits = v.replace(/\D/g, '').slice(0, 10);
            if (digits.length < 4) return digits;
            if (digits.length < 7) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
            return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
          }
          phoneInput.addEventListener('input', function () { phoneInput.value = formatPhone(phoneInput.value); });
          phoneInput.value = formatPhone(phoneInput.value);
        })();
      </script>
      <button type="submit" class="btn">Submit Item Donation</button>
    </form>
  </div>
  <div class="footer">&copy; 2026 East Tennessee Corvette Club &middot; Knoxville, TN &middot; <a href="mailto:etccwebsite.webmanager@gmail.com">etccwebsite.webmanager@gmail.com</a></div>
</div>
</body>
</html>
