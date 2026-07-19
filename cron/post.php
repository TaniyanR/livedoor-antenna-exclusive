<?php
require_once __DIR__.'/../app/poster.php';
protect_cron('post', function(): void {
    try{
        $interval=(int)setting('post_interval_minutes',60);
        if(!in_array($interval,[10,30,60],true)) $interval=60;
        $last=(string)setting('last_post_cron','');
        if($last!=='' && ($lastTime=strtotime($last))!==false && time()<$lastTime+($interval*60)){
            log_event('info','投稿Cron間隔待ち','interval='.$interval);
            echo "SKIPPED interval\n";
            return;
        }
        $id=run_post();
        cleanup_history();
        set_setting('last_post_cron',date('Y-m-d H:i:s'));
        log_event('info','投稿Cron完了','post_id='.$id);
        echo "OK\n";
    }catch(Throwable $e){
        log_event('error','投稿Cronエラー',$e->getMessage());
        http_response_code(500);
        echo "ERROR\n";
    }
});
