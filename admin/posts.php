<?php
require_once __DIR__.'/../app/bootstrap.php';

if(!installed() || !database_available()){
    redirect('/install/');
}
require_admin();

$notice='';
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $pdo=db();
    $pdo->beginTransaction();
    try{
        if(isset($_POST['delete_articles']) && !empty($_POST['article_ids'])){
            $ids=array_values(array_filter(array_map('intval',(array)$_POST['article_ids'])));
            foreach($ids as $id){
                $pdo->prepare('DELETE FROM post_items WHERE article_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM articles WHERE id=? AND last_post_id IS NULL')->execute([$id]);
            }
            $notice=count($ids).'件の未投稿記事を削除しました。';
        }elseif(isset($_POST['delete_history']) && !empty($_POST['history_ids'])){
            $ids=array_values(array_filter(array_map('intval',(array)$_POST['history_ids'])));
            if($ids){
                $in=implode(',',array_fill(0,count($ids),'?'));
                $pdo->prepare("DELETE FROM post_items WHERE post_id IN ($in)")->execute($ids);
                $pdo->prepare("DELETE FROM post_history WHERE id IN ($in)")->execute($ids);
            }
            $notice=count($ids).'件の投稿履歴を削除しました。';
        }
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        $error='削除処理に失敗しました。';
        log_event('error','投稿管理削除失敗',$e->getMessage());
    }
}

$pdo=db();
$postId=filter_input(INPUT_GET,'post_id',FILTER_VALIDATE_INT);
$postId=$postId && $postId>0?(int)$postId:0;
$selectedPost=null;
$selectedItems=[];

if($postId){
    $statement=$pdo->prepare('SELECT * FROM post_history WHERE id=? LIMIT 1');
    $statement->execute([$postId]);
    $selectedPost=$statement->fetch()?:null;
    if($selectedPost){
        $statement=$pdo->prepare('SELECT a.id,a.title,a.url,a.image_url,a.published_at,a.fetched_at,f.site_name FROM post_items pi JOIN articles a ON a.id=pi.article_id JOIN feeds f ON f.id=a.feed_id WHERE pi.post_id=? ORDER BY a.id ASC');
        $statement->execute([$postId]);
        $selectedItems=$statement->fetchAll();
    }else{
        $error='指定された投稿履歴が見つかりません。';
    }
}

$articles=$pdo->query('SELECT a.id,a.title,a.url,a.published_at,a.fetched_at,f.site_name FROM articles a JOIN feeds f ON f.id=a.feed_id WHERE a.last_post_id IS NULL ORDER BY COALESCE(a.published_at,a.fetched_at) DESC LIMIT 100')->fetchAll();
$history=$pdo->query('SELECT * FROM post_history ORDER BY posted_at DESC LIMIT 100')->fetchAll();

admin_header('投稿管理');
if($notice) echo '<p class="notice">'.e($notice).'</p>';
if($error) echo '<p class="notice error">'.e($error).'</p>';
?>
<div class="post-management-page admin-ui-page">
    <?php if($selectedPost): ?>
        <section class="admin-ui-card post-detail-card">
            <div class="post-management-heading">
                <div>
                    <a class="post-management-back" href="<?=e(app_url('/admin/posts.php'))?>">&larr; 投稿一覧へ戻る</a>
                    <h3><?=e($selectedPost['main_title'])?></h3>
                </div>
                <?php if($selectedPost['livedoor_url']): ?>
                    <a class="button post-management-open" href="<?=e(livedoor_public_article_url($selectedPost['livedoor_url']))?>" target="_blank" rel="noopener noreferrer">公開ページ <span aria-hidden="true">↗</span></a>
                <?php endif; ?>
            </div>

            <dl class="post-detail-summary">
                <div><dt>投稿日時</dt><dd><?=e($selectedPost['posted_at'])?></dd></div>
                <div><dt>掲載件数</dt><dd><?=e($selectedPost['article_count'])?>件</dd></div>
                <div><dt>投稿結果</dt><dd><span class="post-result post-result-<?=strtolower(e($selectedPost['result']))?>"><?=e($selectedPost['result'])?></span></dd></div>
            </dl>

            <h4>この投稿に掲載した記事</h4>
            <?php if(!$selectedItems): ?>
                <p class="muted">掲載記事の記録がありません。</p>
            <?php else: ?>
                <div class="post-item-grid">
                    <?php foreach($selectedItems as $item): ?>
                        <a class="post-item-card" href="<?=e($item['url'])?>" target="_blank" rel="noopener noreferrer">
                            <span class="post-item-image">
                                <?php if($item['image_url']): ?>
                                    <img src="<?=e($item['image_url'])?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span>画像なし</span>
                                <?php endif; ?>
                            </span>
                            <span class="post-item-body">
                                <span class="post-item-site"><?=e($item['site_name'])?></span>
                                <strong><?=e($item['title'])?></strong>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="admin-ui-card">
            <h3>投稿したlivedoor記事</h3>
            <p class="admin-ui-note">メインタイトルをクリックすると、その投稿に掲載した全記事を確認できます。</p>

            <?php if(!$history): ?>
                <p class="muted">投稿履歴はありません。</p>
            <?php else: ?>
                <form method="post" class="admin-ui-plain-form">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <div class="admin-ui-table-wrap">
                        <table class="post-management-table post-history-table">
                            <thead>
                                <tr>
                                    <th class="post-management-check"></th>
                                    <th>投稿日時</th>
                                    <th>メイン記事</th>
                                    <th>掲載件数</th>
                                    <th>結果</th>
                                    <th>公開ページ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($history as $post): ?>
                                <tr>
                                    <td><input type="checkbox" name="history_ids[]" value="<?=e($post['id'])?>" aria-label="削除対象にする"></td>
                                    <td class="post-management-date"><?=e($post['posted_at'])?></td>
                                    <td class="post-management-main-title">
                                        <?php if($post['result']==='OK'): ?>
                                            <a href="<?=e(app_url('/admin/posts.php?post_id='.(int)$post['id']))?>"><?=e($post['main_title'])?></a>
                                            <small>掲載した記事をすべて見る</small>
                                        <?php else: ?>
                                            <span><?=e($post['main_title'])?></span>
                                            <?php if($post['error']): ?><small class="post-management-error"><?=e($post['error'])?></small><?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?=e($post['article_count'])?>件</td>
                                    <td><span class="post-result post-result-<?=strtolower(e($post['result']))?>"><?=e($post['result'])?></span></td>
                                    <td>
                                        <?php if($post['livedoor_url']): ?>
                                            <a class="button post-management-open" href="<?=e(livedoor_public_article_url($post['livedoor_url']))?>" target="_blank" rel="noopener noreferrer">公開ページ <span aria-hidden="true">↗</span></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="admin-ui-actions">
                        <button class="danger" name="delete_history" value="1">選択した投稿履歴を削除</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="admin-ui-card">
            <h3>次回の投稿候補</h3>
            <p class="admin-ui-note">RSSから取得済みで、まだlivedoorへ投稿していない記事です。</p>

            <?php if(!$articles): ?>
                <p class="muted">未投稿記事はありません。</p>
            <?php else: ?>
                <form method="post" class="admin-ui-plain-form">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <div class="admin-ui-table-wrap">
                        <table class="post-management-table">
                            <thead>
                                <tr>
                                    <th class="post-management-check"></th>
                                    <th>サイト名</th>
                                    <th>記事タイトル・個別リンク</th>
                                    <th>取得日時</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($articles as $article): ?>
                                <tr>
                                    <td><input type="checkbox" name="article_ids[]" value="<?=e($article['id'])?>" aria-label="削除対象にする"></td>
                                    <td><?=e($article['site_name'])?></td>
                                    <td><a href="<?=e($article['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($article['title'])?></a></td>
                                    <td><?=e($article['published_at']?:$article['fetched_at'])?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="admin-ui-actions">
                        <button class="danger" name="delete_articles" value="1">選択した投稿候補を削除</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php admin_footer();
