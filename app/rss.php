<?php
require_once __DIR__.'/bootstrap.php';

function article_url_key(string $url): string {
    $url=trim(html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8'));
    if(!valid_url($url)) return '';

    $parts=parse_url($url);
    if(!is_array($parts) || empty($parts['host'])) return '';

    $host=strtolower(rtrim((string)$parts['host'],'.'));
    if(str_starts_with($host,'www.')) $host=substr($host,4);
    $port=isset($parts['port'])?':'.(int)$parts['port']:'';
    $path=(string)($parts['path']??'/');
    $path=preg_replace('~/+~','/',$path);
    if($path==='' || $path==='/') $path='/';
    else $path=rtrim($path,'/');

    $query=[];
    if(!empty($parts['query'])){
        parse_str((string)$parts['query'],$query);
        foreach(array_keys($query) as $name){
            $lower=strtolower((string)$name);
            if(str_starts_with($lower,'utm_') || in_array($lower,['fbclid','gclid','dclid','yclid','mc_cid','mc_eid'],true)) unset($query[$name]);
        }
        ksort($query);
    }

    return $host.$port.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
}
function parse_feed(string $xml): array { libxml_use_internal_errors(true); $sx=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA); if(!$sx) throw new RuntimeException('RSS/Atom XMLを解析できません'); $items=[]; $type='rss'; if(isset($sx->channel->item)){foreach($sx->channel->item as $it)$items[]=parse_rss_item($it);} elseif(isset($sx->entry)){ $type='atom'; foreach($sx->entry as $it)$items[]=parse_atom_item($it);} else throw new RuntimeException('RSS/Atomの記事が見つかりません'); return ['type'=>$type,'items'=>$items]; }
function parse_rss_item(SimpleXMLElement $it): array { $ns=$it->getNamespaces(true); $media=$ns['media']??'http://search.yahoo.com/mrss/'; $m=$it->children($media); $url=(string)($it->link??''); return ['title'=>trim((string)$it->title),'url'=>trim($url),'published_at'=>date_or_null((string)($it->pubDate??$it->children('dc',true)->date??'')),'image_url'=>extract_image($it,$m),'description'=>(string)($it->description??'')]; }
function parse_atom_item(SimpleXMLElement $it): array { $url=''; foreach($it->link as $l){$a=$l->attributes(); if((string)($a['rel']??'alternate')==='alternate'||$url==='') $url=(string)$a['href'];} $ns=$it->getNamespaces(true); $m=$it->children($ns['media']??'http://search.yahoo.com/mrss/'); return ['title'=>trim((string)$it->title),'url'=>trim($url),'published_at'=>date_or_null((string)($it->published??$it->updated??'')),'image_url'=>extract_image($it,$m),'description'=>(string)($it->summary??$it->content??'')]; }
function date_or_null(string $s): ?string { $t=strtotime($s); return $t?date('Y-m-d H:i:s',$t):null; }
function extract_image(SimpleXMLElement $it, SimpleXMLElement $m): string { foreach(['content','thumbnail'] as $n) if(isset($m->$n)){ $a=$m->$n->attributes(); if(!empty($a['url'])) return (string)$a['url']; } if(isset($it->enclosure)){ $a=$it->enclosure->attributes(); if(str_starts_with((string)($a['type']??''),'image/') && !empty($a['url'])) return (string)$a['url']; } foreach([(string)($it->description??''),(string)($it->children('content',true)->encoded??$it->content??'')] as $html) if(preg_match('/<img[^>]+src=["\']([^"\']+)/i',$html,$m2)) return html_entity_decode($m2[1]); return ''; }
function test_feed(string $url): array { $p=parse_feed(http_get($url)); $p['items']=array_values(array_filter($p['items'],function($i){ return $i['title']&&valid_url($i['url']); })); foreach($p['items'] as &$i){ if(!empty($i['image_url']) && !valid_url($i['image_url'])) $i['image_url']=''; } unset($i); $p['has_images']=(bool)array_filter($p['items'],fn($i)=>$i['image_url']); return $p; }
function fetch_all_feeds(): array {
    $limit=(int)setting('rss_fetch_limit',20);
    $pdo=db();
    $feeds=$pdo->query('SELECT * FROM feeds ORDER BY sort_order,id')->fetchAll();
    $knownUrls=[];
    foreach($pdo->query('SELECT url FROM articles')->fetchAll(PDO::FETCH_COLUMN) as $existingUrl){
        $key=article_url_key((string)$existingUrl);
        if($key!=='') $knownUrls[$key]=true;
    }

    $ok=0;
    $ng=0;
    foreach($feeds as $f){
        try{
            $p=test_feed($f['feed_url']);
            $cnt=0;
            foreach(array_slice($p['items'],0,$limit) as $i){
                $key=article_url_key((string)$i['url']);
                if($key==='' || isset($knownUrls[$key])) continue;
                $st=$pdo->prepare('INSERT IGNORE INTO articles(feed_id,title,url,image_url,published_at,fetched_at) VALUES(?,?,?,?,?,NOW())');
                $st->execute([$f['id'],$i['title'],$i['url'],$i['image_url'],$i['published_at']]);
                if($st->rowCount()>0){
                    $knownUrls[$key]=true;
                    $cnt++;
                }
            }
            $pdo->prepare('UPDATE feeds SET feed_type=?,last_fetched_at=NOW(),last_result=?,last_error=NULL WHERE id=?')->execute([$p['type'],'OK '.$cnt,$f['id']]);
            $ok++;
        }catch(Throwable $e){
            $ng++;
            $pdo->prepare('UPDATE feeds SET last_fetched_at=NOW(),last_result=?,last_error=? WHERE id=?')->execute(['ERROR',$e->getMessage(),$f['id']]);
            log_event('error','RSS取得失敗',$f['site_name'].': '.$e->getMessage());
        }
    }
    return ['ok'=>$ok,'ng'=>$ng];
}
