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
echo '<form method=post class=settings-form><input type=hidden name=csrf value='.csrf_token().'>';
echo '<h3>サイト情報</h3><p class=section-lead>公開サイトと自動取得・投稿に関する基本値を設定します。</p><div class=form-grid>';
foreach(['site_name'=>'サイト名','site_description'=>'サイト説明','rss_fetch_limit'=>'RSS共通取得件数','post_article_count'=>'livedoor投稿件数','post_interval_minutes'=>'投稿間隔(分)','history_retention_days'=>'投稿履歴保持日数'] as $k=>$l){
    $cls=in_array($k,['site_name','site_description'],true)?' class=full':'';
    echo '<label'.$cls.'>'.e($l).'<input name='.e($k).' value="'.e(setting($k,'')).'"></label>';
}
echo '</div><h3>管理者情報</h3><p class=section-lead>管理画面にログインするユーザー情報を変更できます。</p><div class=form-grid>';
echo '<label>管理者ユーザー名<input name=admin_username value="'.e($currentAdmin).'" required></label>';
echo '<label>管理者パスワード<input type=password name=admin_password placeholder="変更しない場合は空欄"></label></div>';
echo '<p class=muted>インストール時に設定した管理者情報を変更できます。パスワードは8文字以上を推奨します。</p>';
echo '<p class=muted>ツールRSS配信件数は20件固定です。</p><div class=actions><button>保存</button></div></form>';
admin_footer();
