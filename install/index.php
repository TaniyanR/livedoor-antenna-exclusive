<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/schema.php';

if(installed() && database_available()) redirect('/admin/');

$err=installed()?'既存のDB設定で接続できません。下のフォームでサーバーのDB情報を入力し直してください。':'';
$defaults=[
    'db_host'=>'localhost',
    'db_name'=>'',
    'db_user'=>'',
    'site_name'=>'livedoorアンテナ',
    'site_description'=>'livedoorアンテナ',
    'admin_user'=>'admin',
];

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $host=trim((string)$_POST['db_host']);
        $name=trim((string)$_POST['db_name']);
        $user=trim((string)$_POST['db_user']);
        $pass=(string)($_POST['db_pass']??'');
        $adminUser=trim((string)$_POST['admin_user']);
        $adminPass=(string)$_POST['admin_pass'];
        $siteName=trim((string)$_POST['site_name']);
        $siteDescription=trim((string)$_POST['site_description']);
        foreach(['DBホスト'=>$host,'DB名'=>$name,'DBユーザー'=>$user,'管理者ユーザー名'=>$adminUser,'管理者パスワード'=>$adminPass,'サイト名'=>$siteName,'サイト説明'=>$siteDescription] as $label=>$value){
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
        seed_settings(['site_name'=>$siteName,'site_description'=>$siteDescription]);
        $st=db()->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,NOW())');
        $st->execute([$adminUser,password_hash($adminPass,PASSWORD_DEFAULT)]);
        redirect('/admin/login.php');
    }catch(Throwable $e){
        $err='インストールに失敗しました。DB情報と権限を確認してください。DBが未作成の場合は、このインストーラーが自動作成を試みます。';
        @unlink(CONFIG_FILE);
    }
}
?><!doctype html><html lang=ja><head><meta charset=utf-8><link rel=stylesheet href='<?=e(app_url('/assets/admin.css'))?>'><title>Install</title></head><body><main style="margin:0 auto;max-width:760px"><h1>livedoorアンテナ インストール</h1><p>最初にDB情報を入力してください。DBが存在しない場合は自動作成を試み、その後テーブルを自動作成します。</p><?php if($err)echo'<p class="notice error">'.e($err).'</p>';?><form method=post><?php foreach(['db_host'=>'DBホスト','db_name'=>'DB名','db_user'=>'DBユーザー','db_pass'=>'DBパスワード','admin_user'=>'管理者ユーザー名','admin_pass'=>'管理者パスワード','site_name'=>'サイト名','site_description'=>'サイト説明'] as $n=>$l){$type=($n==='db_pass'||$n==='admin_pass')?' type=password':'';$required=$n==='db_pass'?'':' required';$value=isset($defaults[$n])?' value="'.e($defaults[$n]).'"':'';echo'<label>'.e($l).'<input name="'.e($n).'"'.$type.$required.$value.'></label>';}?><button>DBを設定してインストール</button></form></main></body></html>
