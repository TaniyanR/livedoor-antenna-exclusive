<?php
require_once __DIR__.'/../app/poster.php';

if(!installed() || !database_available()){
    redirect('/install/');
}

$msg='';
$class='notice';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        if(isset($_POST['interval'])){
            $interval=(int)$_POST['interval'];
            if(!in_array($interval,[10,30,60],true)) $interval=60;
            set_setting('post_interval_minutes',(string)$interval);
            set_setting('cron_interval_minutes',(string)$interval);
            $msg='投稿間隔を'.$interval.'分に設定しました。';
        }
        if(isset($_POST['rss'])){
            $result=fetch_all_feeds();
            $msg='RSS取得完了: 成功 '.$result['ok'].'件 / 失敗 '.$result['ng'].'件（'.date('Y-m-d H:i:s').'）';
            set_setting('last_rss_cron',date('Y-m-d H:i:s'));
        }
        if(isset($_POST['post'])){
            $id=run_post();
            $msg='投稿処理完了: 履歴ID '.$id.'（'.date('Y-m-d H:i:s').'）';
            set_setting('last_post_cron',date('Y-m-d H:i:s'));
        }
    }catch(Throwable $e){
        $class='notice error';
        $msg='エラー: 処理に失敗しました。詳細はログを確認してください。';
        log_event('error','手動Cron失敗',$e->getMessage());
    }
}

$interval=(int)setting('post_interval_minutes',60);
if(!in_array($interval,[10,30,60],true)) $interval=60;

admin_header('Cron設定');
if($msg) echo '<p class="'.e($class).'">'.e($msg).'</p>';
$php='/usr/bin/php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
$appRoot=realpath(APP_ROOT)?:APP_ROOT;
?>
<div class="cron-page admin-ui-page">
    <form method="post" class="admin-ui-card">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

        <section class="admin-ui-section">
            <h3>投稿間隔設定</h3>
            <div class="admin-ui-field">
                <label for="cron-interval">投稿間隔（分）</label>
                <select id="cron-interval" name="interval">
                    <?php foreach([10,30,60] as $minutes): ?>
                        <option value="<?=$minutes?>" <?=$interval===$minutes?'selected':''?>><?=$minutes?>分</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="admin-ui-note">サーバーのCronは10分ごとに実行しても、ここで選んだ間隔になるまで自動投稿を待機します。</p>
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
            <p class="admin-ui-note">手動のlivedoor投稿実行は、設定した投稿間隔を待たずに実行します。</p>
        </section>

        <section class="admin-ui-section">
            <h3>Cron設定</h3>
            <p class="admin-ui-note">シンレンタルサーバーのサーバーパネルに、以下の2件を登録してください。</p>
            <div class="admin-ui-code-row">
                <strong>RSS取得用Cronコマンド</strong>
                <code><?=e($php.' '.$appRoot.'/cron/fetch.php')?></code>
            </div>
            <p class="admin-ui-note">実行時刻：分「*/10」、時間・日・月・曜日はすべて「*」</p>
            <div class="admin-ui-code-row">
                <strong>livedoor投稿用Cronコマンド</strong>
                <code><?=e($php.' '.$appRoot.'/cron/post.php')?></code>
            </div>
            <p class="admin-ui-note">実行時刻：分「5,15,25,35,45,55」、時間・日・月・曜日はすべて「*」</p>
            <p class="admin-ui-note">RSS取得の5分後に投稿処理を実行します。多重実行はファイルロックで防止します。</p>
        </section>

        <section class="admin-ui-section">
            <h3>最終実行日時</h3>
            <dl class="admin-ui-status-list">
                <div><dt>最終RSS実行</dt><dd><?=e(setting('last_rss_cron','-'))?></dd></div>
                <div><dt>最終投稿実行</dt><dd><?=e(setting('last_post_cron','-'))?></dd></div>
            </dl>
        </section>
    </form>
</div>
<?php admin_footer();
