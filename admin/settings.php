<?php
require_once __DIR__.'/../app/bootstrap.php';
require_admin();

$notice='';
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        foreach(['site_name','site_description','rss_fetch_limit','post_article_count','post_interval_minutes','history_retention_days'] as $k){
            set_setting($k,(string)($_POST[$k]??''));
        }

        $adminId=(int)($_SESSION['admin_id']??0);
        $adminUser=trim((string)($_POST['admin_username']??''));
        $adminPass=(string)($_POST['admin_password']??'');
        if($adminUser==='') throw new RuntimeException('管理者ユーザー名を入力してください。');

        if($adminPass!==''){
            $st=db()->prepare('UPDATE admins SET username=?, password_hash=? WHERE id=?');
            $st->execute([$adminUser,password_hash($adminPass,PASSWORD_DEFAULT),$adminId]);
        }else{
            $st=db()->prepare('UPDATE admins SET username=? WHERE id=?');
            $st->execute([$adminUser,$adminId]);
        }
        $notice='設定を保存しました。';
    }catch(Throwable $e){
        $error='設定の保存に失敗しました。管理者ユーザー名が重複していないか確認してください。';
    }
}

$adminId=(int)($_SESSION['admin_id']??0);
$st=db()->prepare('SELECT username FROM admins WHERE id=?');
$st->execute([$adminId]);
$currentAdmin=(string)($st->fetchColumn()?:'admin');

admin_header('基本設定');
if($notice) echo '<p class="notice">'.e($notice).'</p>';
if($error) echo '<p class="notice error">'.e($error).'</p>';
echo '<form method=post><input type=hidden name=csrf value='.csrf_token().'>';
echo '<h3>サイト情報</h3>';
foreach(['site_name'=>'サイト名','site_description'=>'サイト説明','rss_fetch_limit'=>'RSS共通取得件数','post_article_count'=>'livedoor投稿件数','post_interval_minutes'=>'投稿間隔(分)','history_retention_days'=>'投稿履歴保持日数'] as $k=>$l){
    echo '<label>'.e($l).'<input name='.e($k).' value="'.e(setting($k,'')).'"></label>';
}
echo '<h3>管理者情報</h3>';
echo '<label>管理者ユーザー名<input name=admin_username value="'.e($currentAdmin).'" required></label>';
echo '<label>管理者パスワード<input type=password name=admin_password placeholder="変更しない場合は空欄"></label>';
echo '<p>初期ログイン情報は admin / password です。インストール後は必ずここで変更してください。</p>';
echo '<p>ツールRSS配信件数は20件固定です。</p><button>保存</button></form>';
admin_footer();
