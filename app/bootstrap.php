<?php
declare(strict_types=1);
const APP_ROOT = __DIR__ . '/..';
const CONFIG_FILE = APP_ROOT . '/config/config.php';
const LOG_FILE = APP_ROOT . '/storage/logs/app.log';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
function installed(): bool { return is_file(CONFIG_FILE); }
function app_config(): array { return installed() ? require CONFIG_FILE : []; }
function db(): PDO { static $pdo; if ($pdo) return $pdo; $config=app_config(); $c=$config['db']??[]; if(!empty($config['auto_setup'])){ $server=new PDO('mysql:host='.$c['host'].';charset=utf8mb4',$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); $server->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`','``',$c['name']).'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); } $dsn='mysql:host='.$c['host'].';dbname='.$c['name'].';charset=utf8mb4'; $pdo=new PDO($dsn,$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); if(!empty($config['auto_setup'])) auto_setup($pdo,$config); return $pdo; }
function auto_setup(PDO $pdo,array $config): void { require_once __DIR__.'/schema.php'; create_schema($pdo); seed_settings($config['settings']??[]); $admin=$config['admin']??[]; if(!empty($admin['username'])&&!empty($admin['password'])){ $exists=$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn(); if((int)$exists===0){ $st=$pdo->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,NOW())'); $st->execute([(string)$admin['username'],password_hash((string)$admin['password'],PASSWORD_DEFAULT)]); } } }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_path(): string { $script=(string)($_SERVER['SCRIPT_NAME']??''); foreach(['/admin/','/install/','/cron/'] as $dir){ $pos=strpos($script,$dir); if($pos!==false) return rtrim(substr($script,0,$pos),'/'); } return rtrim(str_replace('\\','/',dirname($script)),'/'); }
function app_url(string $path): string { return base_path().'/'.ltrim($path,'/'); }
function request_scheme(): string { $https=strtolower((string)($_SERVER['HTTPS']??'')); return $https!==''&&$https!=='off'?'https':'http'; }
function app_absolute_url(string $path): string { return request_scheme().'://'.($_SERVER['HTTP_HOST']??'localhost').app_url($path); }
function redirect(string $url): never { header('Location: '.(str_starts_with($url,'/')?app_url($url):$url)); exit; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if(($_POST['csrf']??'')!==($_SESSION['csrf']??'')){ http_response_code(400); exit('CSRF token mismatch'); } }
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void { if(!is_admin()) redirect('/admin/login.php'); }
function setting(string $key, $default=null){ $st=db()->prepare('SELECT value FROM settings WHERE name=?'); $st->execute([$key]); $v=$st->fetchColumn(); return $v===false?$default:$v; }
function set_setting(string $key, string $value): void { $st=db()->prepare('INSERT INTO settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)'); $st->execute([$key,$value]); }
function log_event(string $level,string $message,string $context=''): void { @file_put_contents(LOG_FILE, '['.date('c')."] $level $message $context\n", FILE_APPEND); if(installed()){ try{ $st=db()->prepare('INSERT INTO logs(level,message,context,created_at) VALUES(?,?,?,NOW())'); $st->execute([$level,$message,$context]); }catch(Throwable $e){} } }
function valid_url(string $url): bool { return (bool)filter_var($url,FILTER_VALIDATE_URL) && in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true); }
function http_get(string $url): string { if(!valid_url($url)) throw new RuntimeException('URLが不正です'); $ctx=stream_context_create(['http'=>['timeout'=>15,'user_agent'=>'livedoor-antenna/1.0']]); $s=@file_get_contents($url,false,$ctx); if($s===false) throw new RuntimeException('取得に失敗しました'); return $s; }
function admin_header(string $title): void { require_admin(); $site=e(setting('site_name','livedoorアンテナ')); echo "<!doctype html><html lang=ja><head><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'><link rel=stylesheet href='".app_url('/assets/admin.css')."'><title>".e($title)." - $site</title></head><body><aside><h1>$site</h1><nav>"; foreach(['index.php'=>'ダッシュボード','feeds.php'=>'RSS管理','articles.php'=>'取得記事管理','posts.php'=>'投稿履歴','settings.php'=>'基本設定','livedoor.php'=>'livedoor設定','cron.php'=>'Cron設定','logs.php'=>'ログ','logout.php'=>'ログアウト'] as $u=>$l) echo "<a href='".app_url('/admin/'.$u)."'>".e($l)."</a>"; echo "</nav></aside><main><h2>".e($title)."</h2>"; }
function admin_footer(): void { echo '</main></body></html>'; }
