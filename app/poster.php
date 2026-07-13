<?php
require_once __DIR__.'/rss.php';
function select_articles(int $limit): array { $rows=db()->query('SELECT a.*,f.site_name,(SELECT MAX(ph.posted_at) FROM post_items pi JOIN post_history ph ON ph.id=pi.post_id JOIN articles aa ON aa.id=pi.article_id WHERE aa.feed_id=a.feed_id) last_feed_post FROM articles a JOIN feeds f ON f.id=a.feed_id ORDER BY COALESCE(a.published_at,a.fetched_at) DESC LIMIT 300')->fetchAll(); $sel=[];$per=[];$pool=[];$seen=[]; foreach($rows as $r){ $score=strtotime($r['published_at']?:$r['fetched_at'])/100000; $score-=($per[$r['feed_id']]??0)*8; if(empty($r['last_feed_post'])||strtotime($r['last_feed_post'])<time()-86400*3)$score+=3; $r['_score']=$score; $pool[]=$r; } usort($pool,fn($a,$b)=>$b['_score']<=>$a['_score']); foreach($pool as $r){ if(count($sel)>=$limit) break; if(isset($seen[$r['url']])) continue; $sel[]=$r; $seen[$r['url']]=1; $per[$r['feed_id']]=($per[$r['feed_id']]??0)+1; } return $sel; }
function clean_title(string $t): string { $t=preg_replace('/^【[^】]{1,20}】\s*/u','',$t); $t=preg_replace('/^[\[\(（【][^\]\)）】]{1,20}[\]\)）】]\s*/u','',$t); $t=preg_replace('/\s*[|｜\-–—:：]\s*[^|｜\-–—:：]{1,30}$/u','',$t); return trim(preg_replace('/[\s　]+/u',' ',$t)); }
function choose_main(array $items): array { $best=null;$bs=-1; foreach($items as $i){$l=mb_strlen($i['title']); $s=($i['image_url']?4:0)+($l>=18&&$l<=55?3:0)+(preg_match('/！？|!|\?|動画|画像|速報|話題/u',$i['title'])?2:0)+random_int(0,3); if($s>$bs){$bs=$s;$best=$i;}} return $best?:$items[0]; }
function body_html(array $items): string { $h='<div class="la-antenna-post">'; foreach($items as $i){$h.='<a class="la-card" href="'.e($i['url']).'" target="_blank" rel="noopener">'; if($i['image_url']) $h.='<span class="la-card-image-wrap"><img class="la-card-image" src="'.e($i['image_url']).'" alt=""></span>'; $h.='<span class="la-card-title">'.e($i['title']).'</span></a>'; } return $h.'</div>'; }
function atom_entry(string $title,string $body): string { return '<?xml version="1.0" encoding="utf-8"?><entry xmlns="http://www.w3.org/2005/Atom"><title>'.e($title).'</title><content type="html">'.e($body).'</content><updated>'.date('c').'</updated></entry>'; }
function livedoor_atompub_headers(string $user,string $pass,string $authMethod,string $contentType='application/atom+xml;type=entry; charset=utf-8'): array { $headers=['Content-Type: '.$contentType]; if($authMethod==='wsse'){ $nonce=random_bytes(16); $created=gmdate('Y-m-d\TH:i:s\Z'); $digest=base64_encode(sha1($nonce.$created.$pass,true)); $headers[]='Authorization: WSSE profile="UsernameToken"'; $headers[]='X-WSSE: UsernameToken Username="'.$user.'", PasswordDigest="'.$digest.'", Nonce="'.base64_encode($nonce).'", Created="'.$created.'"'; } else $headers[]='Authorization: Basic '.base64_encode($user.':'.$pass); return $headers; }
function validate_livedoor_atompub_settings(string $url,string $authMethod): void { if(!valid_url($url)) throw new RuntimeException('AtomPub投稿URLが不正です'); if(!preg_match('#^https://livedoor\.blogcms\.jp/atompub/[^/]+/article$#',$url)) throw new RuntimeException('AtomPub投稿URLは https://livedoor.blogcms.jp/atompub/{BLOG_NAME}/article の形式で入力してください'); if($authMethod==='basic'&&parse_url($url,PHP_URL_SCHEME)!=='https') throw new RuntimeException('Basic認証ではHTTPSのAtomPub投稿URLを使用してください'); }
function http_request(string $method,string $url,array $headers=[],?string $body=null,int $timeout=20): array { if(!valid_url($url)) throw new RuntimeException('URLが不正です'); $ch=curl_init($url); if($ch===false) throw new RuntimeException('HTTP通信の初期化に失敗しました'); curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS]); if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$body); $raw=curl_exec($ch); if($raw===false){ $err=curl_error($ch); curl_close($ch); throw new RuntimeException('HTTP通信エラー '.$err); } $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $headerSize=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch); $headerText=substr($raw,0,$headerSize); $responseBody=substr($raw,$headerSize); $responseHeaders=[]; foreach(preg_split('/\r\n|\n|\r/',$headerText) as $line){ if($line!=='') $responseHeaders[]=$line; } return ['code'=>$code,'headers'=>$responseHeaders,'body'=>$responseBody]; }
function post_to_livedoor(string $title,string $body): array { $url=setting('atompub_url',''); $user=setting('livedoor_id',''); $pass=setting('api_password',''); $auth=setting('auth_method','basic')==='wsse'?'wsse':'basic'; if(!$url||!$user||!$pass) throw new RuntimeException('livedoor API設定が未入力です'); validate_livedoor_atompub_settings($url,$auth); $res=http_request('POST',$url,livedoor_atompub_headers($user,$pass,$auth),atom_entry($title,$body),20); $code=$res['code']; if($code<200||$code>=300) throw new RuntimeException('APIエラー HTTP '.$code.' '.substr((string)$res['body'],0,300)); $loc=''; foreach($res['headers'] as $hh) if(stripos($hh,'Location:')===0) $loc=trim(substr($hh,9)); return ['url'=>$loc?:$url,'response'=>(string)$res['body']]; }
function livedoor_connection_test(): array { $url=setting('atompub_url',''); $user=setting('livedoor_id',''); $pass=setting('api_password',''); $auth=setting('auth_method','basic')==='wsse'?'wsse':'basic'; if(!$url||!$user||!$pass) throw new RuntimeException('livedoor API設定が未入力です'); validate_livedoor_atompub_settings($url,$auth); $res=http_request('GET',$url,livedoor_atompub_headers($user,$pass,$auth,'application/atom+xml; charset=utf-8'),null,10); return ['ok'=>$res['code']>=200&&$res['code']<500,'code'=>$res['code'],'body'=>$res['body']]; }
function run_post(): int {
    $items=select_articles((int)setting('post_article_count',36));
    if(!$items) throw new RuntimeException('投稿対象記事がありません');

    $main=choose_main($items);
    $title=clean_title($main['title']);
    $body=body_html($items);
    $pdo=db();

    try{
        $r=post_to_livedoor($title,$body);
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();

        $err=$e->getMessage();

        try{
            $pdo->beginTransaction();
            $st=$pdo->prepare('INSERT INTO post_history(posted_at,main_title,livedoor_url,article_count,result,error,image_url,created_at) VALUES(NOW(),?,?,?,?,?,?,NOW())');
            $st->execute([$title,'',count($items),'ERROR',$err,$main['image_url']]);
            $pdo->commit();
        }catch(Throwable $historyError){
            if($pdo->inTransaction()) $pdo->rollBack();
            log_event('error','投稿失敗履歴の保存にも失敗',$historyError->getMessage());
        }

        log_event('error','投稿失敗',$err);
        throw $e;
    }

    try{
        $pdo->beginTransaction();
        $st=$pdo->prepare('INSERT INTO post_history(posted_at,main_title,livedoor_url,article_count,result,error,image_url,created_at) VALUES(NOW(),?,?,?,?,?,?,NOW())');
        $st->execute([$title,$r['url'],count($items),'OK',null,$main['image_url']]);
        $pid=(int)$pdo->lastInsertId();
        foreach($items as $i){
            $pdo->prepare('INSERT INTO post_items(post_id,article_id) VALUES(?,?)')->execute([$pid,$i['id']]);
            $pdo->prepare('UPDATE articles SET selected=1,last_post_id=? WHERE id=?')->execute([$pid,$i['id']]);
        }
        $pdo->commit();
        log_event('info','投稿成功','post_id='.$pid);
        return $pid;
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
function cleanup_history(): void { $d=(int)setting('history_retention_days',90); $pdo=db(); $pdo->beginTransaction(); try{ $ids=$pdo->prepare('SELECT id FROM post_history WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'); $ids->execute([$d]); $postIds=$ids->fetchAll(PDO::FETCH_COLUMN); if($postIds){ $placeholders=implode(',',array_fill(0,count($postIds),'?')); $pdo->prepare('DELETE FROM post_items WHERE post_id IN ('.$placeholders.')')->execute($postIds); $pdo->prepare('DELETE FROM post_history WHERE id IN ('.$placeholders.')')->execute($postIds); } $pdo->commit(); }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; } }
