# livedoorアンテナ

livedoorアンテナは、複数のRSS/Atomフィードを収集し、保存済み記事からまとめ投稿を生成してlivedoor Blog AtomPub APIへ自動投稿する、共有サーバー向けの軽量PHPツールです。

## 機能

- 初回インストーラー（DB接続確認、テーブル作成、設定ファイル生成、初期管理者作成）
- 管理画面ログイン、ログアウト、セッション管理、CSRF対策
- RSS管理（登録、接続テスト、プレビュー、検索、ページネーション、削除、並び順管理）
- RSS/Atom取得、URL重複除外、画像URL抽出、取得エラー記録
- 取得記事管理（カードUI、検索、ソート、個別/一括削除）
- 投稿対象抽出（新着順ベース、同一RSS偏り抑制、未投稿RSS優遇）
- 投稿タイトル自動生成、本文HTML生成
- livedoor Blog AtomPub投稿（Basic認証、WSSE認証）
- Cron用RSS取得、投稿、履歴自動削除、ログ記録
- 投稿履歴管理（一覧、選択削除、全削除、保持日数削除）
- `/rss.xml` によるRSS2.0配信（最新20件固定）
- ログ閲覧画面

## 動作環境

- PHP 8.0以上推奨
- MySQL / MariaDB
- PHP PDO MySQL拡張
- Cronを設定できる共有サーバー
- livedoor Blog AtomPub APIの投稿情報

Composerや大型フレームワークは不要です。

## インストール方法

1. リポジトリをサーバーへアップロードします。
2. Web公開ディレクトリからこのリポジトリの `index.php` が表示されるように配置します。
3. ブラウザで設置URLへアクセスします。
4. `config/config.php` が存在しない場合、自動的に `/install/` へ移動します。
5. インストーラーで以下を入力します。
   - DBホスト
   - DB名
   - DBユーザー
   - DBパスワード
   - 管理者ユーザー名
   - 管理者パスワード
   - サイト名
   - サイト説明
6. インストール完了後、管理画面 `/admin/` にログインします。

`config/` と `storage/` には直アクセス防止用の `.htaccess` を同梱しています。Nginx等で運用する場合は同等のアクセス制限を設定してください。

## 初期設定

管理画面の「基本設定」から以下を設定できます。

- サイト名
- サイト説明
- RSS共通取得件数（初期値: 20）
- livedoor投稿件数（初期値: 36）
- 投稿間隔
- 投稿履歴保持日数（初期値: 90）

ツールRSS配信件数は20件固定です。

## livedoor設定

管理画面の「livedoor設定」で以下を保存します。

- ブログID
- AtomPub投稿URL
- livedoor ID
- AtomPub用パスワード / APIキー
- 認証方式（Basic / WSSE）

AtomPub投稿URLの形式は次の通りです。

```text
https://livedoor.blogcms.jp/atompub/{BLOG_NAME}/article
```

`{BLOG_NAME}` はブログURLのID部分に置き換えてください。APIキーやパスワードはパスワード入力欄として扱い、保存済み値を画面上にそのまま露出しません。

## RSS登録方法

1. 管理画面の「RSS管理」を開きます。
2. サイト名、RSS URL、メモを入力します。
3. 登録前にRSS/Atomの取得と解析を行います。
4. 成功した場合のみ登録され、形式、取得件数、画像有無、プレビュー3件が表示されます。

RSS取得時の画像URLはフィード内の情報のみ使用します。記事ページへアクセスしてOGP画像を取得したり、画像本体を保存・キャッシュ・livedoorへアップロードする処理は行いません。

## Cron設定

管理画面の「Cron設定」に、サーバー環境に合わせたPHPコマンド例が表示されます。

例:

```bash
php /path/to/livedoor-antenna/cron/fetch.php
php /path/to/livedoor-antenna/cron/post.php
```

- `cron/fetch.php`: RSS取得を実行します。
- `cron/post.php`: livedoor投稿と投稿履歴の自動削除を実行します。

シンサーバーのCron設定画面では、上記PHPコマンドを登録してください。Web URL実行が必要な環境では `/cron/fetch.php` または `/cron/post.php` へアクセスする設定も利用できます。

## 手動実行方法

管理画面の「Cron設定」から次のボタンを利用できます。

- RSS取得実行
- livedoor投稿実行

全実行ボタンはありません。RSS取得と投稿を分けて安全に実行します。

## 投稿本文HTMLについて

livedoorへ投稿する本文には、記事カード用の最小HTML構造とCSS調整用classのみを付与します。livedoorブログ側テンプレートHTML、ブログ側CSS、最終デザインはこのツールには含めていません。

## 注意事項

- `config/config.php` にはDB接続情報が保存されます。外部から直接参照できないようにしてください。
- 管理画面は必ず強固な管理者パスワードで保護してください。
- RSS URLはHTTP/HTTPSのみ登録できます。
- URL重複はDBのユニーク制約と登録処理で除外します。
- RSS取得に失敗したフィードはスキップし、他フィードの処理を継続します。
- 投稿失敗やRSS取得失敗はログと管理画面で確認できます。
- livedoor側カテゴリ投稿は行いません。
