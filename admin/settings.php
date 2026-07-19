<?php
require_once __DIR__.'/../app/bootstrap.php';
require_admin();

$notice='';
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        foreach(['site_name','site_description','rss_fetch_limit','history_retention_days'] as $k){
            set_setting($k,(string)($_POST[$k]??''));
        }
        $interval=(int)($_POST['post_interval_minutes']??60);
        if(!in_array($interval,[10,30,60],true)) $interval=60;
        set_setting('post_interval_minutes',(string)$interval);
        set_setting('post_article_count','50');
        $notice='設定を保存しました。';
    }catch(Throwable $e){
        $error='設定の保存に失敗しました。';
        log_event('error','基本設定保存失敗',$e->getMessage());
    }
}

$interval=(int)setting('post_interval_minutes',60);
if(!in_array($interval,[10,30,60],true)) $interval=60;

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
                'site_name'=>'ツール名',
                'site_description'=>'配信RSSの説明',
                'rss_fetch_limit'=>'RSS共通取得件数',
                'history_retention_days'=>'投稿履歴保持日数'
            ] as $k=>$l): ?>
            <div class="admin-ui-field">
                <label for="setting-<?=e($k)?>"><?=e($l)?></label>
                <input id="setting-<?=e($k)?>" name="<?=e($k)?>" value="<?=e(setting($k,''))?>">
            </div>
            <?php endforeach; ?>

            <div class="admin-ui-field">
                <label for="setting-post-interval">投稿間隔（分）</label>
                <select id="setting-post-interval" name="post_interval_minutes">
                    <?php foreach([10,30,60] as $minutes): ?>
                        <option value="<?=$minutes?>" <?=$interval===$minutes?'selected':''?>><?=$minutes?>分</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="admin-ui-note">livedoorへの1回の投稿には、未投稿記事を最大50件入れます。</p>
            <p class="admin-ui-note">各RSSサイトからできるだけ同じ件数を選び、同じサイトの記事が固まりすぎないようランダムに並べます。</p>
            <p class="admin-ui-note">「ツール名」は管理画面左上とブラウザのタイトルに表示されます。</p>
            <p class="admin-ui-note">「配信RSSの説明」は、このツールが出力するRSSの説明文に使われます。</p>
            <p class="admin-ui-note">ツールRSS配信件数は20件固定です。</p>
        </section>

        <div class="admin-ui-actions">
            <button>保存</button>
        </div>
    </form>
</div>
<?php admin_footer();
