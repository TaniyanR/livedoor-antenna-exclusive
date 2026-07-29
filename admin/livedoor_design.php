<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

if (!installed() || !database_available()) {
    redirect('/install/');
}
require_admin();

$designFiles = [
    [
        'id' => 'livedoor-css',
        'title' => 'CSS',
        'description' => 'livedoor Blogの「CSS」欄へ全文を貼り付けます。',
        'file' => 'style.css',
        'rows' => 28,
    ],
    [
        'id' => 'livedoor-top',
        'title' => 'トップページHTML',
        'description' => 'livedoor Blogの「トップページ」欄へ全文を貼り付けます。',
        'file' => 'top.html',
        'rows' => 28,
    ],
    [
        'id' => 'livedoor-article',
        'title' => '個別記事ページHTML',
        'description' => 'livedoor Blogの「個別記事ページ」欄へ全文を貼り付けます。',
        'file' => 'article.html',
        'rows' => 28,
    ],
    [
        'id' => 'livedoor-category',
        'title' => 'カテゴリアーカイブHTML',
        'description' => 'livedoor Blogの「カテゴリアーカイブ」欄へ全文を貼り付けます。',
        'file' => 'category.html',
        'rows' => 28,
    ],
    [
        'id' => 'livedoor-monthly',
        'title' => '月別アーカイブHTML',
        'description' => 'livedoor Blogの「月別アーカイブ」欄へ全文を貼り付けます。',
        'file' => 'monthly.html',
        'rows' => 28,
    ],
];

$designDirectory = APP_ROOT . '/resources/livedoor-design';
foreach ($designFiles as &$design) {
    $path = $designDirectory . '/' . $design['file'];
    $design['code'] = is_file($path) ? (string)file_get_contents($path) : '';
}
unset($design);

admin_header('livedoorデザイン');
?>
<div class="admin-ui-page livedoor-design-page">

  <section class="admin-ui-card livedoor-design-guide">
    <h3>livedoor Blogへ貼り付けるデザインコード</h3>
    <p class="admin-ui-note">
      この画面はコードの保管とコピー専用です。ここで編集してもlivedoor Blogの表示は変わりません。
      貼り付ける前に、livedoor Blog側の「デザイン保存」で現在のデザインを保存してください。
    </p>
    <p class="notice">
      各欄は必ず「全文」をコピーし、同じ名前の欄へ貼り付けてください。
      CSSとHTMLを別の欄へ貼り付けると、テンプレート文法エラーや表示崩れの原因になります。
    </p>
  </section>

  <?php foreach ($designFiles as $design): ?>
    <section class="admin-ui-card livedoor-design-card">
      <div class="livedoor-design-heading">
        <div>
          <h3><?=e($design['title'])?></h3>
          <p class="admin-ui-note"><?=e($design['description'])?></p>
        </div>
        <button
          type="button"
          class="livedoor-design-copy"
          data-copy-target="<?=e($design['id'])?>"
        >全文をコピー</button>
      </div>

      <?php if ($design['code'] === ''): ?>
        <p class="notice error">コードファイルを読み込めませんでした。サーバー上の設置ファイルを確認してください。</p>
      <?php else: ?>
        <textarea
          id="<?=e($design['id'])?>"
          class="livedoor-design-code"
          rows="<?=e($design['rows'])?>"
          readonly
          spellcheck="false"
          wrap="off"
        ><?=e($design['code'])?></textarea>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>

</div>

<script>
(function () {
  function fallbackCopy(textarea) {
    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);
    return document.execCommand('copy');
  }

  document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    button.addEventListener('click', function () {
      var textarea = document.getElementById(button.getAttribute('data-copy-target'));
      if (!textarea) {
        return;
      }

      var originalText = button.textContent;
      var copied = function () {
        button.textContent = 'コピーしました';
        button.classList.add('is-copied');
        window.setTimeout(function () {
          button.textContent = originalText;
          button.classList.remove('is-copied');
        }, 1800);
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textarea.value).then(copied).catch(function () {
          if (fallbackCopy(textarea)) {
            copied();
          }
        });
        return;
      }

      if (fallbackCopy(textarea)) {
        copied();
      }
    });
  });
})();
</script>
<?php admin_footer(); ?>
