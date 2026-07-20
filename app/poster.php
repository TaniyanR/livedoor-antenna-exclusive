<?php
require_once __DIR__.'/rss.php';

function secure_shuffle(array $items): array {
    for($i=count($items)-1;$i>0;$i--){
        $j=random_int(0,$i);
        [$items[$i],$items[$j]]=[$items[$j],$items[$i]];
    }
    return $items;
}

function select_articles(int $limit): array {
    $limit=max(1,min(50,$limit));
    $rows=db()->query('SELECT a.*,f.site_name FROM articles a JOIN feeds f ON f.id=a.feed_id WHERE a.selected=0 AND a.last_post_id IS NULL ORDER BY COALESCE(a.published_at,a.fetched_at) DESC LIMIT 1000')->fetchAll();

    $groups=[];
    $seen=[];
    foreach($rows as $row){
        $url=(string)$row['url'];
        if($url==='' || isset($seen[$url]) || !valid_url($url)) continue;
        $seen[$url]=true;
        $groups[(int)$row['feed_id']][]=$row;
    }
    if(!$groups) return [];

    foreach($groups as $feedId=>$items) $groups[$feedId]=secure_shuffle($items);
    $feedIds=secure_shuffle(array_keys($groups));
    $selected=[];

    while(count($selected)<$limit){
        $added=false;
        foreach($feedIds as $feedId){
            if(empty($groups[$feedId])) continue;
            $selected[]=array_shift($groups[$feedId]);
            $added=true;
            if(count($selected)>=$limit) break;
        }
        if(!$added) break;
        $feedIds=secure_shuffle($feedIds);
    }

    return spread_article_sites($selected);
}

function spread_article_sites(array $items): array {
    $groups=[];
    foreach($items as $item) $groups[(int)$item['feed_id']][]=$item;
    foreach($groups as $feedId=>$rows) $groups[$feedId]=secure_shuffle($rows);

    $result=[];
    $lastFeedId=null;
    while($groups){
        $available=array_keys($groups);
        $candidates=array_values(array_filter($available,fn($id)=>$id!==$lastFeedId));
        if(!$candidates) $candidates=$available;
        $feedId=$candidates[random_int(0,count($candidates)-1)];
        $result[]=array_shift($groups[$feedId]);
        $lastFeedId=$feedId;
        if(!$groups[$feedId]) unset($groups[$feedId]);
    }
    return $result;
}

function clean_title(string $title): string {
    $title=preg_replace('/^【[^】]{1,20}】\s*/u','',$title);
    $title=preg_replace('/^[\[\(（【][^\]\)）】]{1,20}[\]\)）】]\s*/u','',$title);
    $title=preg_replace('/\s*[|｜\-–—:：]\s*[^|｜\-–—:：]{1,30}$/u','',$title);
    return trim((string)preg_replace('/[\s　]+/u',' ',$title));
}

function choose_main(array $items): array {
    $byFeed=[];
    foreach($items as $item) $byFeed[(int)$item['feed_id']][]=$item;
    $feedIds=array_keys($byFeed);
    $lastFeedId=(int)setting('last_main_feed_id','0');
    if(count($feedIds)>1) $feedIds=array_values(array_filter($feedIds,fn($id)=>$id!==$lastFeedId));
    if(!$feedIds) $feedIds=array_keys($byFeed);
    $feedId=$feedIds[random_int(0,count($feedIds)-1)];
    $candidates=$byFeed[$feedId];
    $withImage=array_values(array_filter($candidates,fn($item)=>!empty($item['image_url'])));
    if($withImage) $candidates=$withImage;
    return $candidates[random_int(0,count($candidates)-1)];
}

function safe_link(string $url): string { return valid_url($url)?$url:''; }

function body_html(array $items, int $mainArticleId=0, string $uploadedMainImage=''): string {
    $style='<style>'
        .'.la-antenna-grid{box-sizing:border-box;display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;width:100%;max-width:1500px;margin:0 auto;padding:12px 6px}'
        .'.la-antenna-card{box-sizing:border-box;display:flex!important;min-width:0;flex-direction:column;overflow:hidden;border:1px solid #ddd;border-radius:10px;background:#fff!important;color:#111!important;text-decoration:none!important;box-shadow:0 2px 8px rgba(0,0,0,.12)}'
        .'.la-antenna-image{display:flex;width:100%;height:180px;align-items:center;justify-content:center;overflow:hidden;background:#111}'
        .'.la-antenna-image img{display:block;width:100%;height:100%;object-fit:contain;background:#111;border:0}'
        .'.la-antenna-noimage{color:#777;font-size:14px;font-weight:700;background:#eee}'
        .'.la-antenna-body{display:flex;flex:1;flex-direction:column;padding:12px 13px 14px;background:#fff}'
        .'.la-antenna-site{display:block;margin:0 0 6px;color:#777;font-size:12px;line-height:1.4}'
        .'.la-antenna-title{display:-webkit-box;overflow:hidden;color:#111;font-size:16px;font-weight:700;line-height:1.55;word-break:break-word;-webkit-box-orient:vertical;-webkit-line-clamp:4}'
        .'#comments,#comment-form,.comment-form,.article-comment,.article-comments{display:none!important}'
        .'@media(max-width:1100px){.la-antenna-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}'
        .'@media(max-width:820px){.la-antenna-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.la-antenna-image{height:150px}}'
        .'@media(max-width:480px){.la-antenna-grid{grid-template-columns:1fr}.la-antenna-image{height:190px}}'
        .'</style>';

    $html=$style.'<div class="la-antenna-grid">';
    $seen=[];
    foreach(array_slice($items,0,50) as $item){
        $url=safe_link((string)$item['url']);
        if($url==='' || isset($seen[$url])) continue;
        $seen[$url]=true;
        $title=trim((string)$item['title']);
        $site=trim((string)($item['site_name']??''));
        $image=safe_link((string)($item['image_url']??''));
        if((int)$item['id']===$mainArticleId && $uploadedMainImage!=='') $image=$uploadedMainImage;
        $html.='<a class="la-antenna-card" href="'.e($url).'" target="_blank" rel="noopener noreferrer">';
        if($image!==''){
            $html.='<span class="la-antenna-image"><img src="'.e($image).'" alt="'.e($title).'" loading="lazy"></span>';
        }else{
            $html.='<span class="la-antenna-image la-antenna-noimage">画像なし</span>';
        }
        $html.='<span class="la-antenna-body">';
        if($site!=='') $html.='<span class="la-antenna-site">'.e($site).'</span>';
        $html.='<span class="la-antenna-title">'.e($title).'</span></span></a>';
    }
    return $html.'</div>';
}

function atom_entry(string $title,string $body): string { return '<?xml version="1.0" encoding="utf-8"?><entry xmlns="http://www.w3.org/2005/Atom"><title>'.e($title).'</title><content type="html">'.e($body).'</content><updated>'.date('c').'</updated></entry>'; }
function livedoor_atompub_headers(string $user,string $pass,string $contentType='application/atom+xml;type=entry; charset=utf-8'): array { return ['Content-Type: '.$contentType,'Authorization: Basic '.base64_encode($user.':'.$pass),'Expect:']; }
function upload_image_to_livedoor(string $sourceUrl): string {
    $sourceUrl=safe_link($sourceUrl);
    if($sourceUrl==='') return '';

    $download=http_request('GET',$sourceUrl,['Accept: image/jpeg,image/png,image/gif'],null,['timeout'=>20,'max_bytes'=>5242880]);
    if($download['status']<200 || $download['status']>=300) throw new RuntimeException('代表画像を取得できませんでした。HTTP '.$download['status']);
    $imageData=(string)$download['body'];
    $imageInfo=@getimagesizefromstring($imageData);
    $mime=is_array($imageInfo)?(string)($imageInfo['mime']??''):'';
    if(!in_array($mime,['image/jpeg','image/png','image/gif'],true)) throw new RuntimeException('代表画像の形式がJPEG・PNG・GIFではありません');

    $root=validate_livedoor_atompub_settings((string)setting('atompub_url',''));
    $user=(string)setting('livedoor_id','');
    $pass=(string)setting('api_password','');
    if($user==='' || $pass==='') throw new RuntimeException('livedoor API設定が未入力です');
    $response=http_request('POST',$root.'/image',livedoor_atompub_headers($user,$pass,$mime),$imageData,['timeout'=>30,'max_bytes'=>5242880]);
    if($response['status']<200 || $response['status']>=300) throw new RuntimeException('代表画像のアップロードに失敗しました。HTTP '.$response['status']);

    $uploadedUrl='';
    if(preg_match('/<content\\b[^>]*\\bsrc=["\x27]([^"\x27]+)["\x27]/i',(string)$response['body'],$matches)) $uploadedUrl=html_entity_decode($matches[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
    if($uploadedUrl==='' && preg_match('/<link\\b[^>]*\\brel=["\x27]alternate["\x27][^>]*\\bhref=["\x27]([^"\x27]+)["\x27]/i',(string)$response['body'],$matches)) $uploadedUrl=html_entity_decode($matches[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
    if(!valid_url($uploadedUrl)) throw new RuntimeException('アップロード後の代表画像URLを取得できませんでした');
    return $uploadedUrl;
}
function validate_livedoor_atompub_settings(string $url): string { $url=rtrim(trim($url),'/'); if(!valid_url($url)) throw new RuntimeException('ルートエンドポイントが正しくありません'); if(!preg_match('#^https://livedoor\.blogcms\.jp/atompub/[^/]+$#',$url)) throw new RuntimeException('ルートエンドポイントは https://livedoor.blogcms.jp/atompub/{BLOG_NAME} の形式で入力してください'); if(parse_url($url,PHP_URL_SCHEME)!=='https') throw new RuntimeException('HTTPSのルートエンドポイントを使用してください'); return $url; }
function livedoor_connection_test(): string { $url=validate_livedoor_atompub_settings((string)setting('atompub_url','')); $user=setting('livedoor_id',''); $pass=setting('api_password',''); if($user==='') throw new RuntimeException('livedoor ID（ログイン用）が入力されていません'); if($pass==='') throw new RuntimeException('AtomPub用パスワードが入力されていません'); $response=http_request('GET',$url,livedoor_atompub_headers($user,$pass,'application/atom+xml'),null,['timeout'=>15]); if($response['status']===401) throw new RuntimeException('認証に失敗しました。HTTP 401'); if($response['status']===403) throw new RuntimeException('投稿先ブログへアクセスできません。HTTP 403'); if($response['status']===404) throw new RuntimeException('ルートエンドポイントが正しくありません。HTTP 404'); if($response['status']<200||$response['status']>=300) throw new RuntimeException('livedoorへ接続できませんでした。HTTP '.$response['status']); return '接続テストに成功しました。記事は作成していません。'; }
function post_to_livedoor(string $title,string $body): array { $root=validate_livedoor_atompub_settings((string)setting('atompub_url','')); $url=$root.'/article'; $user=setting('livedoor_id',''); $pass=setting('api_password',''); if(!$root||!$user||!$pass) throw new RuntimeException('livedoor API設定が未入力です'); $response=http_request('POST',$url,livedoor_atompub_headers($user,$pass),atom_entry($title,$body),['timeout'=>20]); if($response['status']<200||$response['status']>=300) throw new RuntimeException('APIエラー HTTP '.$response['status']); $location=''; if(preg_match('/^Location:\s*(.+)$/im',$response['headers'],$matches)) $location=trim($matches[1]); return ['url'=>$location?:$url,'response'=>$response['body']]; }

function run_post(): int {
    $items=select_articles(50);
    if(!$items) throw new RuntimeException('投稿対象記事がありません');
    $main=choose_main($items);
    $title=clean_title($main['title']);
    $uploadedMainImage='';
    if(!empty($main['image_url'])){
        try{
            $uploadedMainImage=upload_image_to_livedoor((string)$main['image_url']);
        }catch(Throwable $imageError){
            log_event('warning','代表画像アップロードをスキップ',$imageError->getMessage());
        }
    }
    $body=body_html($items,(int)$main['id'],$uploadedMainImage);
    $pdo=db();
    try{
        $result=post_to_livedoor($title,$body);
        $pdo->beginTransaction();
        $statement=$pdo->prepare('INSERT INTO post_history(posted_at,main_title,livedoor_url,article_count,result,error,image_url,created_at) VALUES(NOW(),?,?,?,?,?,?,NOW())');
        $statement->execute([$title,$result['url'],count($items),'OK',null,$main['image_url']]);
        $postId=(int)$pdo->lastInsertId();
        foreach($items as $item){
            $pdo->prepare('INSERT INTO post_items(post_id,article_id) VALUES(?,?)')->execute([$postId,$item['id']]);
            $pdo->prepare('UPDATE articles SET selected=1,last_post_id=? WHERE id=?')->execute([$postId,$item['id']]);
        }
        set_setting('last_main_feed_id',(string)$main['feed_id']);
        $pdo->commit();
        log_event('info','投稿成功','post_id='.$postId.' count='.count($items));
        return $postId;
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        $error=$e->getMessage();
        try{
            $pdo->beginTransaction();
            $statement=$pdo->prepare('INSERT INTO post_history(posted_at,main_title,livedoor_url,article_count,result,error,image_url,created_at) VALUES(NOW(),?,?,?,?,?,?,NOW())');
            $statement->execute([$title,'',count($items),'ERROR',$error,$main['image_url']]);
            $pdo->commit();
            log_event('error','投稿失敗',$error);
        }catch(Throwable $historyError){
            if($pdo->inTransaction()) $pdo->rollBack();
            log_event('error','投稿失敗履歴の保存にも失敗',$historyError->getMessage());
        }
        throw $e;
    }
}

function cleanup_history(): void { $days=max(1,min(3650,(int)setting('history_retention_days',90))); $pdo=db(); $pdo->beginTransaction(); try{ $statement=$pdo->prepare('SELECT id FROM post_history WHERE created_at < DATE_SUB(NOW(), INTERVAL '.$days.' DAY)'); $statement->execute(); $ids=array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN)); if($ids){ $placeholders=implode(',',array_fill(0,count($ids),'?')); $pdo->prepare("DELETE FROM post_items WHERE post_id IN ($placeholders)")->execute($ids); $pdo->prepare("DELETE FROM post_history WHERE id IN ($placeholders)")->execute($ids); } $pdo->commit(); log_event('info','投稿履歴自動削除','deleted='.count($ids).' days='.$days); }catch(Throwable $e){ $pdo->rollBack(); throw $e; } }
