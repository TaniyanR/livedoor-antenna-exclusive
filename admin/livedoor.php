<?php
require_once __DIR__.'/../app/poster.php';

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    foreach(['blog_id','atompub_url','livedoor_id','api_password','auth_method'] as $k){
        if(isset($_POST[$k])&&$_POST[$k]!=='********') set_setting($k,(string)$_POST[$k]);
    }
    if(isset($_POST['test'])){
        try{
            $msg=livedoor_connection_test();
        }catch(Throwable $e){
            $msg=$e->getMessage();
        }
    }
}

admin_header('livedoor設定');
if($msg) echo '<p class="notice">'.e($msg).'</p>';
?>
<div class="livedoor-page admin-ui-page">
    <form method="post" class="admin-ui-card">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

        <div class="admin-ui-field">
            <label for="blog-id">ブログID</label>
            <input id="blog-id" name="blog_id" value="<?=e(setting('blog_id',''))?>">
        </div>

        <div class="admin-ui-field">
            <label for="atompub-url">AtomPub投稿URL</label>
            <input id="atompub-url" name="atompub_url" placeholder="https://livedoor.blogcms.jp/atompub/{BLOG_NAME}/article" value="<?=e(setting('atompub_url',''))?>">
        </div>

        <div class="admin-ui-field">
            <label for="livedoor-id">livedoor ID</label>
            <input id="livedoor-id" name="livedoor_id" value="<?=e(setting('livedoor_id',''))?>">
        </div>

        <div class="admin-ui-field">
            <label for="api-password">AtomPub用パスワード / APIキー</label>
            <input id="api-password" type="password" name="api_password" value="********">
        </div>

        <div class="admin-ui-field">
            <label for="auth-method">認証方式</label>
            <select id="auth-method" name="auth_method">
                <option value="basic">Basic</option>
                <option value="wsse" <?=setting('auth_method')==='wsse'?'selected':''?>>WSSE</option>
            </select>
        </div>

        <div class="admin-ui-actions">
            <button>保存</button>
            <button name="test" value="1">接続テスト</button>
        </div>
    </form>
</div>
<?php admin_footer();