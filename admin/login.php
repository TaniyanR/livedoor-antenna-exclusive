<?php
require_once __DIR__.'/../app/bootstrap.php';

if(!installed()) redirect('/install/');

$err='';
$dbReady=true;
$site='livedoorアンテナ';
$needsAdmin=false;

try{
    $site=e(setting('site_name','livedoorアンテナ'));
    $needsAdmin=(int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn()===0;
}catch(Throwable $e){
    $dbReady=false;
    $err=database_error_message($e);
}

if($dbReady && $_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();

    try{
        if($needsAdmin){
            $username=trim((string)($_POST['username']??''));
            $password=(string)($_POST['password']??'');
            $passwordConfirm=(string)($_POST['password_confirm']??'');

            if($username==='') throw new RuntimeException('管理者ユーザー名を入力してください。');
            if(strlen($password)<8) throw new RuntimeException('管理者パスワードは8文字以上で入力してください。');
            if($password!==$passwordConfirm) throw new RuntimeException('管理者パスワード確認が一致しません。');

            $st=db()->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,NOW())');
            $st->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin_id']=(int)db()->lastInsertId();
            redirect('/admin/');
        }

        if(!empty($_SESSION['login_block_until']) && time()<(int)$_SESSION['login_block_until']){
            throw new RuntimeException('ログイン試行回数が多すぎます。しばらく待ってから再試行してください。');
        }

        $st=db()->prepare('SELECT * FROM admins WHERE username=?');
        $st->execute([$_POST['username']??'']);
        $u=$st->fetch();

        if($u && password_verify((string)($_POST['password']??''),(string)$u['password_hash'])){
            session_regenerate_id(true);
            unset($_SESSION['login_fail'],$_SESSION['login_block_until']);
            $_SESSION['admin_id']=$u['id'];
            redirect('/admin/');
        }

        $_SESSION['login_fail']=(int)($_SESSION['login_fail']??0)+1;
        if($_SESSION['login_fail']>=5) $_SESSION['login_block_until']=time()+300;
        $err='ログインに失敗しました';
    }catch(RuntimeException $e){
        $err=$e->getMessage();
    }catch(Throwable $e){
        $err=database_error_message($e);
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?=e(app_url('/assets/admin.css'))?>">
<title>Login</title>
</head>
<body>
<main style="margin:105px auto 0;max-width:430px;padding:0 12px">
<form method="post" style="padding:22px 20px">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<h1 style="margin:0 0 7px;font-size:20px"><?=$site?></h1>
<p style="margin:0 0 16px;color:#50575e;font-size:14px"><?=$needsAdmin?'初回管理者設定':'管理画面ログイン'?></p>
<?php if($err) echo '<p class="notice error">'.e($err).'</p>'; ?>
<label style="font-size:15px;font-weight:700">ユーザー名
<input name="username" required style="margin-top:6px;margin-bottom:12px">
</label>
<label style="font-size:15px;font-weight:700">パスワード
<input type="password" name="password" required style="margin-top:6px;margin-bottom:14px">
</label>
<?php if($needsAdmin): ?>
<label style="font-size:15px;font-weight:700">パスワード確認
<input type="password" name="password_confirm" required style="margin-top:6px;margin-bottom:14px">
</label>
<p style="margin-top:0;color:#50575e;font-size:13px">管理者パスワードは8文字以上で入力してください。</p>
<?php endif; ?>
<?php if(!$dbReady) echo '<p><a href="'.e(app_url('/install/')).'">DB設定画面へ戻る</a></p>'; ?>
<button style="width:100%;padding:9px 12px;font-size:14px" <?=$dbReady?'':'disabled'?>><?=$needsAdmin?'管理者を作成してログイン':'ログイン'?></button>
</form>
</main>
</body>
</html>
