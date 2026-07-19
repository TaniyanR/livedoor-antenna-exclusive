<?php
require_once __DIR__.'/../app/bootstrap.php';

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

admin_header('投稿管理');
if($notice) echo '<p class="notice">'.e($notice).'</p>';
if($error) echo '<p class="notice error">'.e($error).'</p>';

$articles=db()->query('SELECT a.id,a.title,a.url,a.published_at,a.fetched_at,f.site_name FROM articles a JOIN feeds f ON f.id=a.feed_id WHERE a.last_post_id IS NULL ORDER BY COALESCE(a.published_at,a.fetched_at) DESC LIMIT 100')->fetchAll();
$history=db()->query('SELECT * FROM post_history ORDER BY posted_at DESC LIMIT 100')->fetchAll();
?>
<div class="post-management-page admin-ui-page">
    <section class="admin-ui-card">
        <h3>未投稿記事</h3>
        <p class="admin-ui-note">自動投稿前の記事です。不要な記事だけ選択して削除できます。</p>

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
                                <th>日時</th>
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
                    <button class="danger" name="delete_articles" value="1">選択した未投稿記事を削除</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="admin-ui-card">
        <h3>投稿履歴</h3>
        <p class="admin-ui-note">livedoorへの投稿結果です。成功・失敗と投稿先URLを確認できます。</p>

        <?php if(!$history): ?>
            <p class="muted">投稿履歴はありません。</p>
        <?php else: ?>
            <form method="post" class="admin-ui-plain-form">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <div class="admin-ui-table-wrap">
                    <table class="post-management-table">
                        <thead>
                            <tr>
                                <th class="post-management-check"></th>
                                <th>投稿日時</th>
                                <th>投稿タイトル</th>
                                <th>livedoor記事URL</th>
                                <th>件数</th>
                                <th>結果</th>
                                <th>エラー</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($history as $post): ?>
                            <tr>
                                <td><input type="checkbox" name="history_ids[]" value="<?=e($post['id'])?>" aria-label="削除対象にする"></td>
                                <td><?=e($post['posted_at'])?></td>
                                <td><?=e($post['main_title'])?></td>
                                <td>
                                    <?php if($post['livedoor_url']): ?>
                                        <a href="<?=e($post['livedoor_url'])?>" target="_blank" rel="noopener noreferrer"><?=e($post['livedoor_url'])?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?=e($post['article_count'])?></td>
                                <td><?=e($post['result'])?></td>
                                <td><?=e($post['error'])?></td>
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
</div>
<?php admin_footer();
