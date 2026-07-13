<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/schema.php';

if(installed() && database_available()){
    redirect('/admin/login.php');
}

$err='';
$defaults=['db_host'=>'localhost','db_name'=>'','db_user'=>''];

function install_value(string $name,array $defaults): string {
    return (string)($_POST[$name]??$defaults[$name]??'');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $host=trim((string)($_POST['db_host']??''));
        $name=trim((string)($_POST['db_name']??''));
        $user=trim((string)($_POST['db_user']??''));
        $pass=(string)($_POST['db_pass']??'');

        foreach(['DBホスト'=>$host,'DB名'=>$name,'DBユーザー'=>$user] as $label=>$value){
            if($value==='') throw new RuntimeException($label.'を入力してください。');
        }

        try{
            $server=new PDO('mysql:host='.$host.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            try{
                $server->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`','``',$name).'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }catch(Throwable $e){}
        }catch(Throwable $e){}

        $pdo=new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        create_schema($pdo);

        $cfg="<?php\nreturn [\n    'db' => [\n        'host' => ".var_export($host,true).",\n        'name' => ".var_export($name,true).",\n        'user' => ".var_export($user,true).",\n        'pass' => ".var_export($pass,true).",\n    ],\n];\n";
        $tmp=CONFIG_FILE.'.tmp';
        if(!is_dir(dirname(CONFIG_FILE))) mkdir(dirname(CONFIG_FILE),0755,true);
        if(file_put_contents($tmp,$cfg,LOCK_EX)===false) throw new RuntimeException('設定ファイルを書き込めませんでした。');
        if(!rename($tmp,CONFIG_FILE)) throw new RuntimeException('設定ファイルを保存できませんでした。');

        seed_settings(['site_name'=>'livedoorアンテナ','site_description'=>'livedoorアンテナ']);
        redirect('/admin/login.php');
    }catch(Throwable $e){
        $err='DBへ接続できませんでした。入力内容を確認してください。';
        @unlink(CONFIG_FILE.'.tmp');
        if(!installed()) @unlink(CONFIG_FILE);
    }
}

function install_input(string $name,string $label,array $defaults,string $type='text',bool $required=true,string $hint=''): void {
    $value=$type==='password'?'':' value="'.e(install_value($name,$defaults)).'"';
    echo '<label class="install-field"><span>'.e($label).'</span><input name="'.e($name).'" type="'.e($type).'"'.($required?' required':'').$value.'>';
    if($hint) echo '<small>'.e($hint).'</small>';
    echo '</label>';
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?=e(app_url('/assets/admin.css'))?>">
<title>DB設定</title>
<style>
.install-page{margin:0;min-height:100vh;background:#f6f8fb}
.install-wrap{max-width:760px;margin:0 auto;padding:42px 18px}
.install-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:24px}
.install-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.install-field{display:flex;flex-direction:column;font-weight:700;gap:7px}
.install-actions{border-top:1px solid #eef0f2;margin:24px -24px -24px;padding:20px 24px;background:#fbfcfd}
@media(max-width:720px){.install-grid{grid-template-columns:1fr}}
</style>
</head>
<body class="install-page">
<main class="install-wrap">
<h1>DB設定</h1>
<p>データベースの接続情報を入力してください。設定完了後、ログイン画面へ移動します。</p>
<?php if($err) echo '<p class="notice error">'.e($err).'</p>'; ?>
<form method="post" class="install-card">
<div class="install-grid">
<?php
install_input('db_host','DBホスト',$defaults,'text',true,'例: localhost');
install_input('db_name','DB名',$defaults);
install_input('db_user','DBユーザー',$defaults);
install_input('db_pass','DBパスワード',$defaults,'password',false);
?>
</div>
<div class="install-actions"><button type="submit">DB設定を保存</button></div>
</form>
</main>
</body>
</html>
