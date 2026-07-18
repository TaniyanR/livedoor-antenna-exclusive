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
?>
<div class="settings-page admin-ui-page">
    <form method="post" class="admin-ui-card">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

        <section class="admin-ui-section">
            <h3>サイト情報</h3>
            <?php foreach([
                'site_name'=>'サイト名',
                'site_description'=>'サイト説明',
                'rss_fetch_limit'=>'RSS共通取得件数',
                'post_article_count'=>'livedoor投稿件数',
                'post_interval_minutes'=>'投稿間隔(分)',
                'history_retention_days'=>'投稿履歴保持日数'
            ] as $k=>$l): ?>
            <div class="admin-ui-field">
                <label for="setting-<?=e($k)?>"><?=e($l)?></label>
                <input id="setting-<?=e($k)?>" name="<?=e($k)?>" value="<?=e(setting($k,''))?>">
            </div>
            <?php endforeach; ?>
        </section>

        <section class="admin-ui-section">
            <h3>管理者情報</h3>
            <div class="admin-ui-field">
                <label for="admin-username">管理者ユーザー名</label>
                <input id="admin-username" name="admin_username" value="<?=e($currentAdmin)?>" required>
            </div>
            <div class="admin-ui-field">
                <label for="admin-password">管理者パスワード</label>
                <input id="admin-password" type="password" name="admin_password" placeholder="変更しない場合は空欄">
            </div>
            <p class="admin-ui-note">インストール時に設定した管理者情報を変更できます。パスワードは8文字以上を推奨します。</p>
            <p class="admin-ui-note">ツールRSS配信件数は20件固定です。</p>
        </section>

        <div class="admin-ui-actions">
            <button>保存</button>
        </div>
    </form>
</div>
<?php admin_footer();