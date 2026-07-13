<?php
require_once __DIR__.'/../app/rss.php';
protect_cron('fetch', function(): void {
    try{$r=fetch_all_feeds(); set_setting('last_rss_cron',date('Y-m-d H:i:s')); log_event('info','RSS Cron完了',json_encode($r,JSON_UNESCAPED_UNICODE)); echo "OK\n";}catch(Throwable $e){log_event('error','RSS Cronエラー',$e->getMessage()); http_response_code(500); echo "ERROR\n";}
});
