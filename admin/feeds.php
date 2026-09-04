<?php
require_once __DIR__.'/../app/rss.php';
require_admin();

$perPage=20;
$page=max(1,(int)($_GET['page']??1));

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
            $pdo=db();
            $pdo->beginTransaction();
            try{
                $ids=array_map('intval',$pdo->query('SELECT id FROM feeds ORDER BY sort_order,id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN));
                $offset=($page-1)*$perPage;
                $expected=array_slice($ids,$offset,$perPage);
                $ordered=array_map('intval',explode(',',(string)$_POST['order']));
                $sorted=$ordered;
                sort($sorted);
                $expectedSorted=$expected;
                sort($expectedSorted);
                if(!$expected || $sorted!==$expectedSorted) throw new RuntimeException('サイト一覧が変更されています。画面を再読み込みして並べ替えてください。');
                array_splice($ids,$offset,count($expected),$ordered);
                $update=$pdo->prepare('UPDATE feeds SET sort_order=? WHERE id=?');
                foreach($ids as $i=>$id){
                    $update->execute([$i+1,$id]);
                }
                $pdo->commit();
                $msg='並び順と取得順を保存しました。';
            }catch(Throwable $e){
                if($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
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
                $page=max(1,(int)ceil((int)db()->query('SELECT COUNT(*) FROM feeds')->fetchColumn()/$perPage));
                $form=['id'=>'','site_name'=>'','site_url'=>'','feed_url'=>''];
            }
        }
    }catch(Throwable $e){
        $msg='エラー: '.$e->getMessage();
    }
}

$total=(int)db()->query('SELECT COUNT(*) FROM feeds')->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage));
$page=min($page,$totalPages);
$offset=($page-1)*$perPage;
$renderPagination=static function() use($page,$totalPages,$total,$offset,$perPage): void {
    echo '<nav class="admin-ui-actions" aria-label="RSS一覧のページ切り替え">';
    echo '<span>登録総数 '.e($total).'件（'.e($total?$offset+1:0).'～'.e(min($offset+$perPage,$total)).'件を表示） / '.e($page).' / '.e($totalPages).'ページ</span>';
    if($page>1){
        echo '<a class="button" href="?page=1">最初</a>';
        echo '<a class="button" href="?page='.e($page-1).'">前へ</a>';
    }
    if($page<$totalPages){
        echo '<a class="button" href="?page='.e($page+1).'">次へ</a>';
        echo '<a class="button" href="?page='.e($totalPages).'">最後</a>';
    }
    echo '</nav>';
};

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
    <form method="post" action="?page=<?=e($page)?>" class="admin-ui-card">
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
$st=db()->query('SELECT * FROM feeds ORDER BY sort_order,id LIMIT '.$perPage.' OFFSET '.$offset);
$renderPagination();
?>
    <div class="admin-ui-table-wrap">
        <table>
            <tr><th>順</th><th>サイト名</th><th>URL・RSS</th><th>最終取得</th><th>結果</th><th>エラー</th><th>操作</th></tr>
            <?php foreach($st as $r): ?>
            <tr draggable="true" data-id="<?=e($r['id'])?>">
                <td><?=e(++$offset)?></td>
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
                        <a class="button" href="?page=<?=e($page)?>&amp;edit=<?=e($r['id'])?>">編集</a>
                        <form method="post" action="?page=<?=e($page)?>" class="admin-ui-inline-form">
                            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                            <button class="danger" name="delete" value="<?=e($r['id'])?>">削除</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php $renderPagination(); ?>
    <form id="orderForm" method="post" action="?page=<?=e($page)?>" class="admin-ui-hidden-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="order" id="order">
    </form>
    <p class="muted">登録件数にアプリ上の上限はありません。一覧は1ページ20件で、「次へ」で続きが表示されます。行をドラッグ＆ドロップすると、このページ内の並び順と取得順を保存します。</p>
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
