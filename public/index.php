<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/../vendor/autoload.php';

use App\Database;

$pdo = Database::pdoFromEnv();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        $message = 'Invalid CSRF token.';
    } else {
        $value = trim((string)($_POST['value'] ?? ''));
        if ($value === '') {
            $message = 'Enter a value.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO items (value, created_at) VALUES (:v, CURRENT_TIMESTAMP)');
            $stmt->execute([':v' => $value]);
            $message = 'Saved.';
        }
    }
}

$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
$items = $pdo->query('SELECT id, value, created_at FROM items ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Simple Monolith</title>
  <style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;margin:2rem}</style>
</head>
<body>
  <h1>Submit</h1>
  <form method="post" action="/">
    <input name="value" type="text" required placeholder="Enter something" />
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>" />
    <button type="submit">Enter</button>
  </form>
  <p><?php echo htmlspecialchars($message); ?></p>

  <h2>Recent</h2>
  <ul>
    <?php foreach ($items as $it): ?>
      <li><?php echo htmlspecialchars($it['value']) . ' — ' . htmlspecialchars($it['created_at']); ?></li>
    <?php endforeach; ?>
  </ul>
</body>
</html>
