<?php
require_once __DIR__.'/../app/poster.php';

$msg='';
$class='notice';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        if(isset($_POST['regen'])) set_setting('cron_token',bin2hex(random_bytes(32)));
        if(isset($_POST['interval'])) set_setting('cron_interval_minutes',(string)max(1,(int)$_POST['interval']));
        if(isset($_POST['rss'])){
            $r=fetch_all_feeds();
            $msg='RSS取得完了: 成功 '.$r['ok'].'件 / 失敗 '.$r['ng'].'件（'.date('Y-m-d H:i:s').'）';
            set_setting('last_rss_cron',date('Y-m-d H:i:s'));
        }
        if(isset($_POST['post'])){
            $id=run_post();
            $msg='投稿処理完了: 履歴ID '.$id.'（'.date('Y-m-d H:i:s').'）';
            set_setting('last_post_cron',date('Y-m-d H:i:s'));
        }
        if(isset($_POST['regen'])) $msg='Web実行用秘密トークンを再生成しました。';
    }catch(Throwable $e){
        $class='notice error';
        $msg='エラー: 処理に失敗しました。詳細はログを確認してください。';
        log_event('error','手動Cron失敗',$e->getMessage());
    }
}

admin_header('Cron設定');
if($msg) echo '<p class="'.e($class).'">'.e($msg).'</p>';
$php=PHP_BINARY?:'php';
$token=ensure_cron_token();
?>
<div class="cron-page admin-ui-page">
    <form method="post" class="admin-ui-card">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

        <section class="admin-ui-section">
            <h3>実行間隔設定</h3>
            <div class="admin-ui-field">
                <label for="cron-interval">実行間隔(分)</label>
                <input id="cron-interval" name="interval" value="<?=e(setting('cron_interval_minutes',60))?>">
            </div>
            <div class="admin-ui-actions">
                <button>保存</button>
            </div>
        </section>

        <section class="admin-ui-section">
            <h3>手動実行</h3>
            <div class="admin-ui-actions">
                <button name="rss" value="1">RSS取得実行</button>
                <button name="post" value="1">livedoor投稿実行</button>
            </div>
        </section>

        <section class="admin-ui-section">
            <h3>Web実行設定</h3>
            <div class="admin-ui-field">
                <label for="web-rss-url">Web RSS取得URL</label>
                <input id="web-rss-url" class="admin-ui-mono" readonly value="<?=e(app_absolute_url('/cron/fetch.php?token='.$token))?>">
            </div>
            <div class="admin-ui-field">
                <label for="web-post-url">Web 投稿URL</label>
                <input id="web-post-url" class="admin-ui-mono" readonly value="<?=e(app_absolute_url('/cron/post.php?token='.$token))?>">
            </div>
            <p class="admin-ui-note">Web実行は秘密トークン必須です。ログには完全なトークンを出力しません。CLI実行ではトークン不要です。多重実行はファイルロックで防止します。</p>
            <div class="admin-ui-actions">
                <button name="regen" value="1" class="danger">Webトークン再生成</button>
            </div>
        </section>
    </form>

    <section class="admin-ui-card admin-ui-section">
        <h3>CLI設定例</h3>
        <div class="admin-ui-code-row">
            <strong>CLI RSS取得</strong>
            <code><?=e($php.' '.APP_ROOT.'/cron/fetch.php')?></code>
        </div>
        <div class="admin-ui-code-row">
            <strong>CLI 投稿</strong>
            <code><?=e($php.' '.APP_ROOT.'/cron/post.php')?></code>
        </div>
    </section>

    <section class="admin-ui-card admin-ui-section">
        <h3>最終実行日時</h3>
        <dl class="admin-ui-status-list">
            <div><dt>最終RSS実行</dt><dd><?=e(setting('last_rss_cron','-'))?></dd></div>
            <div><dt>最終投稿実行</dt><dd><?=e(setting('last_post_cron','-'))?></dd></div>
        </dl>
    </section>
</div>
<?php admin_footer();