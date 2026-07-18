<?php
require_once __DIR__.'/../app/bootstrap.php';
require_admin();

$notice='';
$error='';
$adminId=(int)($_SESSION['admin_id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
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
        $notice='管理者情報を保存しました。';
    }catch(Throwable $e){
        $error='管理者情報の保存に失敗しました。ユーザー名が重複していないか確認してください。';
    }
}

$st=db()->prepare('SELECT username FROM admins WHERE id=?');
$st->execute([$adminId]);
$currentAdmin=(string)($st->fetchColumn()?:'admin');

admin_header('管理者情報');
if($notice) echo '<p class="notice">'.e($notice).'</p>';
if($error) echo '<p class="notice error">'.e($error).'</p>';
?>
<div class="account-page admin-ui-page">
    <form method="post" class="admin-ui-card">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

        <div class="admin-ui-field">
            <label for="admin-username">管理者ユーザー名</label>
            <input id="admin-username" name="admin_username" value="<?=e($currentAdmin)?>" required>
        </div>

        <div class="admin-ui-field">
            <label for="admin-password">管理者パスワード</label>
            <input id="admin-password" type="password" name="admin_password" placeholder="変更しない場合は空欄">
        </div>

        <p class="admin-ui-note">管理画面へのログイン情報を変更できます。パスワードは8文字以上を推奨します。</p>

        <div class="admin-ui-actions">
            <button>保存</button>
        </div>
    </form>
</div>
<?php admin_footer();
