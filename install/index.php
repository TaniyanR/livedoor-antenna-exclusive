<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/schema.php';

if(installed() && database_available()) redirect('/admin/');

$err=installed()?'既存のDB設定で接続できません。下のフォームでサーバーのDB情報を入力し直してください。':'';
$defaults=[
    'db_host'=>'localhost',
    'db_name'=>'',
    'db_user'=>'',
];

function install_value(string $name, array $defaults): string {
    if(isset($_POST[$name])) return (string)$_POST[$name];
    return (string)($defaults[$name] ?? '');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $host=trim((string)$_POST['db_host']);
        $name=trim((string)$_POST['db_name']);
        $user=trim((string)$_POST['db_user']);
        $pass=(string)($_POST['db_pass']??'');
        foreach(['DBホスト'=>$host,'DB名'=>$name,'DBユーザー'=>$user] as $label=>$value){
            if($value==='') throw new RuntimeException($label.'を入力してください。');
        }

        $server=new PDO("mysql:host=$host;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        try{
            $server->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`','``',$name).'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }catch(Throwable $createError){
            // CREATE DATABASE権限がない共有サーバーでも、DBが既に作成済みなら次の接続で続行します。
        }
        $pdo=new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        create_schema($pdo);

        $cfg="<?php\nreturn [\n    'db' => [\n        'host' => '".addslashes($host)."',\n        'name' => '".addslashes($name)."',\n        'user' => '".addslashes($user)."',\n        'pass' => '".addslashes($pass)."',\n    ],\n];\n";
        file_put_contents(CONFIG_FILE,$cfg,LOCK_EX);
        seed_settings(['site_name'=>'livedoorアンテナ','site_description'=>'livedoorアンテナ']);
        $exists=(int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if($exists===0){
            $st=db()->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,NOW())');
            $st->execute(['admin',password_hash('password',PASSWORD_DEFAULT)]);
        }
        redirect('/admin/login.php');
    }catch(Throwable $e){
        $err='インストールに失敗しました。DB情報と権限を確認してください。DBが未作成の場合は、このインストーラーが自動作成を試みます。';
        @unlink(CONFIG_FILE);
    }
}

function install_input(string $name, string $label, array $defaults, string $type='text', bool $required=true, string $hint=''): void {
    $requiredAttr=$required?' required':'';
    $value=$type==='password'?'':' value="'.e(install_value($name,$defaults)).'"';
    echo '<label class="install-field"><span>'.e($label).'</span><input name="'.e($name).'" type="'.e($type).'"'.$requiredAttr.$value.'>';
    if($hint!=='') echo '<small>'.e($hint).'</small>';
    echo '</label>';
}
?><!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?=e(app_url('/assets/admin.css'))?>">
<title>Install</title>
<style>
.install-page{margin:0;min-height:100vh;background:linear-gradient(135deg,#f6f8fb 0%,#eef4fb 100%)}
.install-wrap{max-width:780px;margin:0 auto;padding:48px 18px 56px}
.install-hero{margin-bottom:22px;text-align:center}.install-hero h1{margin:0 0 10px;font-size:28px}.install-hero p{margin:0 auto;max-width:640px;color:#50575e;line-height:1.8}
.install-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;box-shadow:0 14px 36px rgba(29,35,39,.08);padding:24px;overflow:hidden}.install-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.install-field{display:flex;flex-direction:column;font-weight:700;gap:7px}.install-field input{max-width:none;margin:0;padding:11px 12px;font-size:15px}.install-field small{color:#646970;font-weight:400;line-height:1.5}.install-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;border-top:1px solid #eef0f2;margin:24px -24px -24px;padding:20px 24px;background:#fbfcfd}.install-actions p{margin:0;color:#646970}.install-actions button{padding:12px 18px;font-size:15px;font-weight:700}.install-full{grid-column:1/-1}@media(max-width:720px){.install-grid{grid-template-columns:1fr}.install-actions{align-items:stretch;flex-direction:column}.install-actions button{width:100%}}
</style>
</head>
<body class="install-page">
<main class="install-wrap">
    <div class="install-hero">
        <h1>DB設定</h1>
        <p>最初にDB情報のみ入力してください。DBが存在しない場合は自動作成を試み、その後テーブルを自動作成してログイン画面へ移動します。</p>
    </div>
    <?php if($err)echo'<p class="notice error">'.e($err).'</p>'; ?>
    <form method="post" class="install-card">
        <div class="install-grid">
            <?php install_input('db_host','DBホスト',$defaults,'text',true,'例: localhost'); ?>
            <?php install_input('db_name','DB名',$defaults,'text',true,'存在しない場合は自動作成を試みます'); ?>
            <?php install_input('db_user','DBユーザー',$defaults); ?>
            <?php install_input('db_pass','DBパスワード',$defaults,'password',false,'パスワードなしの場合は空欄でOKです'); ?>
        </div>
        <div class="install-actions">
            <p>初期ログインは <b>admin / password</b> です。ログイン後に管理画面で変更してください。</p>
            <button type="submit">DBを設定してログイン画面へ進む</button>
        </div>
    </form>
</main>
</body>
</html>
