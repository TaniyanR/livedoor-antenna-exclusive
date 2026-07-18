<?php
require_once __DIR__.'/../app/rss.php';

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        if(isset($_POST['delete'])){
            db()->prepare('DELETE FROM feeds WHERE id=?')->execute([$_POST['delete']]);
        }elseif(isset($_POST['order'])){
            foreach(explode(',',$_POST['order']) as $i=>$id){
                db()->prepare('UPDATE feeds SET sort_order=? WHERE id=?')->execute([$i,(int)$id]);
            }
        }else{
            $p=test_feed($_POST['feed_url']);
            if(isset($_POST['id'])&&$_POST['id']){
                db()->prepare('UPDATE feeds SET site_name=?,feed_url=?,memo=?,feed_type=?,updated_at=NOW() WHERE id=?')->execute([$_POST['site_name'],$_POST['feed_url'],$_POST['memo'],$p['type'],$_POST['id']]);
            }else{
                db()->prepare('INSERT INTO feeds(site_name,feed_url,memo,sort_order,feed_type,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())')->execute([$_POST['site_name'],$_POST['feed_url'],$_POST['memo'],(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM feeds')->fetchColumn(),$p['type']]);
            }
            $msg='テスト成功: '.$p['type'].' / '.count($p['items']).'件 / 画像'.($p['has_images']?'あり':'なし').' / '.implode('、',array_map(fn($i)=>$i['title'],array_slice($p['items'],0,3)));
        }
    }catch(Throwable $e){
        $msg='エラー: '.$e->getMessage();
    }
}

admin_header('RSS管理');
if($msg) echo '<p class="notice">'.e($msg).'</p>';

$edit=null;
if(isset($_GET['edit'])){
    $es=db()->prepare('SELECT * FROM feeds WHERE id=?');
    $es->execute([$_GET['edit']]);
    $edit=$es->fetch();
}
?>
<div class="feeds-page admin-ui-page">
    <form method="post" class="admin-ui-card">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="id" value="<?=e($edit['id']??'')?>">

        <div class="admin-ui-field">
            <label for="feed-site-name">サイト名</label>
            <input id="feed-site-name" name="site_name" placeholder="サイト名" value="<?=e($edit['site_name']??'')?>" required>
        </div>

        <div class="admin-ui-field">
            <label for="feed-url">RSS URL</label>
            <input id="feed-url" name="feed_url" placeholder="RSS URL" value="<?=e($edit['feed_url']??'')?>" required>
        </div>

        <div class="admin-ui-field">
            <label for="feed-memo">メモ</label>
            <textarea id="feed-memo" name="memo" placeholder="メモ"><?=e($edit['memo']??'')?></textarea>
        </div>

        <div class="admin-ui-actions">
            <button><?= $edit?'更新・テスト取得':'登録・テスト取得' ?></button>
        </div>
    </form>

<?php
$page=max(1,(int)($_GET['page']??1));
$st=db()->query('SELECT * FROM feeds ORDER BY sort_order,id LIMIT 20 OFFSET '.(($page-1)*20));
?>
    <div class="admin-ui-table-wrap">
        <table>
            <tr><th>順</th><th>サイト名</th><th>RSS URL</th><th>メモ</th><th>最終取得</th><th>結果</th><th>エラー</th><th>操作</th></tr>
            <?php foreach($st as $r): ?>
            <tr draggable="true" data-id="<?=e($r['id'])?>">
                <td><?=e($r['sort_order'])?></td>
                <td><?=e($r['site_name'])?></td>
                <td class="admin-ui-url-cell"><?=e($r['feed_url'])?></td>
                <td><?=e($r['memo'])?></td>
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
