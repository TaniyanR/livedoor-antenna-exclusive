<?php
require_once __DIR__.'/app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
$config=app_config();
$db=$config['db'] ?? [];
$scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$checks=[];
$checks[]=['アクセス方式', $scheme === 'http' ? 'OK' : 'NG', $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').($_SERVER['REQUEST_URI'] ?? '')];
$checks[]=['設定ファイル', installed() ? 'OK' : 'NG', CONFIG_FILE];
$checks[]=['DB名', !empty($db['name']) ? 'OK' : 'NG', (string)($db['name'] ?? '')];
try {
    $pdo=db();
    $checks[]=['DB接続', 'OK', '接続できました'];
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $checks[]=['テーブル数', count($tables) > 0 ? 'OK' : 'NG', count($tables).' 件'.($tables ? ' / '.implode(', ', $tables) : '')];
} catch (Throwable $e) {
    $checks[]=['DB接続', 'NG', $e->getMessage()];
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>XAMPP setup check</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.6;margin:32px;background:#f6f7f9;color:#111}.wrap{max-width:960px;margin:auto;background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px}table{border-collapse:collapse;width:100%;margin-top:16px}th,td{border:1px solid #ddd;padding:10px;text-align:left}.ok{color:#087f23;font-weight:700}.ng{color:#b00020;font-weight:700}.note{background:#fff8e1;border:1px solid #f0c36d;padding:12px;border-radius:6px}</style>
</head>
<body><div class="wrap">
<h1>livedoorアンテナ XAMPP確認</h1>
<?php if($scheme !== 'http'): ?><p class="note">このページは <strong>http://</strong> で開いてください。https の証明書エラー画面ではPHPが実行されないため、この確認画面も表示できません。</p><?php endif; ?>
<table><tr><th>項目</th><th>結果</th><th>詳細</th></tr><?php foreach($checks as [$label,$result,$detail]): ?><tr><td><?=e($label)?></td><td class="<?=strtolower($result)==='ok'?'ok':'ng'?>"><?=e($result)?></td><td><?=e($detail)?></td></tr><?php endforeach; ?></table>
<p>管理画面: <a href="<?=e(app_url('/admin/'))?>"><?=e(app_url('/admin/'))?></a></p>
<p>この画面を開くURL例: <code>http://localhost/livedoor-antenna/setup_check.php</code></p>
</div></body></html>
