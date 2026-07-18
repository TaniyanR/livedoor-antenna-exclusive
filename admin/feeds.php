<?php
require_once __DIR__.'/../app/rss.php';

$msg='';
$form=[
    'id'=>'',
    'site_name'=>'',
    'site_url'=>'',
    'feed_url'=>'',
];

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $form=[
        'id'=>(string)($_POST['id']??''),
        'site_name'=>trim((string)($_POST['site_name']??'')),
        'site_url'=>trim((string)($_POST['site_url']??'')),
        'feed_url'=>trim((string)($_POST['feed_url']??'')),
    ];
    try{
        if(isset($_POST['delete'])){
            db()->prepare('DELETE FROM feeds WHERE id=?')->execute([$_POST['delete']]);
        }elseif(isset($_POST['order'])){
            foreach(explode(',',$_POST['order']) as $i=>$id){
                db()->prepare('UPDATE feeds SET sort_order=? WHERE id=?')->execute([$i,(int)$id]);
            }
        }elseif(isset($_POST['test'])){
            $p=test_feed($form['feed_url']);
            $msg='テスト成功: '.$p['type'].' / '.count($p['items']).'件 / 画像'.($p['has_images']?'あり':'なし').' / '.implode('、',array_map(fn($i)=>$i['title'],array_slice($p['items'],0,3)));
        }elseif(isset($_POST['save'])){
            if($form['site_name']==='') throw new RuntimeException('サイト名を入力してください。');
            if(!valid_url($form['site_url'])) throw new RuntimeException('URLを正しく入力してください。');
            if(!valid_url($form['feed_url'])) throw new RuntimeException('RSSを正しく入力してください。');

            if($form['id']!==''){
                db()->prepare('UPDATE feeds SET site_name=?,site_url=?,feed_url=?,updated_at=NOW() WHERE id=?')->execute([$form['site_name'],$form['site_url'],$form['feed_url'],(int)$form['id']]);
                $msg='更新しました。';
            }else{
                db()->prepare('INSERT INTO feeds(site_name,site_url,feed_url,memo,sort_order,feed_type,created_at,updated_at) VALUES(?,?,?,?,?,NULL,NOW(),NOW())')->execute([$form['site_name'],$form['site_url'],$form['feed_url'],'',(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM feeds')->fetchColumn()]);
                $msg='登録しました。';
                $form=['id'=>'','site_name'=>'','site_url'=>'','feed_url'=>''];
            }
        }
    }catch(Throwable $e){
        $msg='エラー: '.$e->getMessage();
    }
}

admin_header('RSS管理');
if($msg) echo '<p class="notice">'.e($msg).'</p>';

if($_SERVER['REQUEST_METHOD']!=='POST' && isset($_GET['edit'])){
    $es=db()->prepare('SELECT * FROM feeds WHERE id=?');
    $es->execute([$_GET['edit']]);
    $edit=$es->fetch();
    if($edit){
        $form=[
            'id'=>(string)$edit['id'],
            'site_name'=>(string)$edit['site_name'],
            'site_url'=>(string)($edit['site_url']??''),
            'feed_url'=>(string)$edit['feed_url'],
        ];
    }
}
?>
<div class="feeds-page admin-ui-page">
    <form method="post" class="admin-ui-card">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="id" value="<?=e($form['id'])?>">

        <div class="admin-ui-field">
            <label for="feed-site-name">サイト名</label>
            <input id="feed-site-name" name="site_name" placeholder="サイト名" value="<?=e($form['site_name'])?>" required>
        </div>

        <div class="admin-ui-field">
            <label for="site-url">URL</label>
            <input id="site-url" name="site_url" type="url" placeholder="https://example.com/" value="<?=e($form['site_url'])?>" required>
        </div>

        <div class="admin-ui-field">
            <label for="feed-url">RSS</label>
            <input id="feed-url" name="feed_url" type="url" placeholder="https://example.com/feed/" value="<?=e($form['feed_url'])?>" required>
        </div>

        <div class="admin-ui-actions">
            <button name="save" value="1"><?= $form['id']!==''?'更新':'登録' ?></button>
            <button name="test" value="1" type="submit">テスト取得</button>
        </div>
        <p class="admin-ui-note">「テスト取得」はRSSを確認するだけで、登録・更新は行いません。</p>
    </form>

<?php
$page=max(1,(int)($_GET['page']??1));
$st=db()->query('SELECT * FROM feeds ORDER BY sort_order,id LIMIT 20 OFFSET '.(($page-1)*20));
?>
    <div class="admin-ui-table-wrap">
        <table>
            <tr><th>順</th><th>サイト名</th><th>URL・RSS</th><th>最終取得</th><th>結果</th><th>エラー</th><th>操作</th></tr>
            <?php foreach($st as $r): ?>
            <tr draggable="true" data-id="<?=e($r['id'])?>">
                <td><?=e($r['sort_order'])?></td>
                <td><?=e($r['site_name'])?></td>
                <td class="admin-ui-url-cell">
                    <div class="admin-ui-url-pair"><strong>URL</strong><span><?=e($r['site_url']??'')?></span></div>
                    <div class="admin-ui-url-pair"><strong>RSS</strong><span><?=e($r['feed_url'])?></span></div>
                </td>
                <td><?=e($r['last_fetched_at'])?></td>
                <td><?=e($r['last_result'])?></td>
                <td><?=e($r['last_error'])?></td>
                <td>
                    <div class="admin-ui-row-actions">
                        <a class="button" href="?edit=<?=e($r['id'])?>">編集</a>
                        <form method="post" class="admin-ui-inline-form">
                            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                            <button class="danger" name="delete" value="<?=e($r['id'])?>">削除</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <form id="orderForm" method="post" class="admin-ui-hidden-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="order" id="order">
    </form>
    <p class="muted">行をドラッグ＆ドロップすると並び順と取得順を保存します。1ページ20件。</p>
</div>
<script>
let drag;
document.querySelectorAll('tr[draggable]').forEach(r=>{
    r.ondragstart=()=>drag=r;
    r.ondragover=e=>e.preventDefault();
    r.ondrop=()=>{
        if(drag&&drag!==r){
            r.parentNode.insertBefore(drag,r);
            document.getElementById('order').value=[...document.querySelectorAll('tr[data-id]')].map(x=>x.dataset.id).join(',');
            document.getElementById('orderForm').submit();
        }
    };
});
</script>
<?php admin_footer();
