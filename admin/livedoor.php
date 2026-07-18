<?php
require_once __DIR__.'/../app/poster.php';

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    foreach(['blog_id','livedoor_id','atompub_url','api_password'] as $k){
        if(isset($_POST[$k])&&$_POST[$k]!=='********') set_setting($k,(string)$_POST[$k]);
    }
    set_setting('auth_method','basic');
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
            <label for="blog-id">ブログID（BLOG_NAME）</label>
            <input id="blog-id" name="blog_id" value="<?=e(setting('blog_id',''))?>">
        </div>

        <div class="admin-ui-field">
            <label for="livedoor-id">livedoor ID（ログイン用）</label>
            <input id="livedoor-id" name="livedoor_id" value="<?=e(setting('livedoor_id',''))?>">
        </div>

        <div class="admin-ui-field">
            <label for="atompub-url">ルートエンドポイント</label>
            <input id="atompub-url" name="atompub_url" placeholder="https://livedoor.blogcms.jp/atompub/{BLOG_NAME}/article" value="<?=e(setting('atompub_url',''))?>">
        </div>

        <div class="admin-ui-field">
            <label for="api-password">AtomPub用パスワード</label>
            <input id="api-password" type="password" name="api_password" value="********">
        </div>

        <div class="admin-ui-actions">
            <button>保存</button>
            <button name="test" value="1">接続テスト</button>
        </div>
    </form>
</div>
<?php admin_footer();
