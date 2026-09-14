<?php date_default_timezone_set("Europe/Istanbul");
/* SKYTÜRK — sistem araçları (yetki: settings): önbellek temizle, yedek indir, günlükler */
require_once __DIR__.'/config.php'; require_once __DIR__.'/session.php'; sky_require('settings');
$act=$_GET['action']??'info'; $root=dirname(__DIR__);
if($act==='backup'){ header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="skyturk-yedek-'.date('Ymd-His').'.json"'); readfile(__DIR__.'/data.json'); sky_audit('yedek indirme'); exit; }
header('Content-Type: application/json; charset=utf-8');
if($act==='clearcache'){ $n=0; foreach(glob($root.'/gazete/img/*.jpg') as $f){ unlink($f); $n++; } foreach(glob(__DIR__.'/lib/font/unifont/*.dat') as $f){} sky_audit('önbellek temizleme',$n.' dosya'); echo json_encode(['ok'=>true,'deleted'=>$n]); exit; }
if($act==='logs'){ echo json_encode(['fetch'=>array_slice(explode("\n",(string)@file_get_contents(__DIR__.'/fetch.log')),0,30),'mcp'=>array_slice(explode("\n",(string)@file_get_contents(__DIR__.'/mcp.log')),0,30)],JSON_UNESCAPED_UNICODE); exit; }
if($act==='info'){ $d=json_decode(@file_get_contents(__DIR__.'/data.json'),true)?:[]; echo json_encode(['php'=>PHP_VERSION,'imagick'=>class_exists('Imagick'),'gd'=>function_exists('imagecreatetruecolor'),'data_mb'=>round(filesize(__DIR__.'/data.json')/1048576,2),'news'=>count($d['news']??[]),'media_files'=>count(glob($root.'/media/*')),'gazete_files'=>count(glob($root.'/gazete/*.pdf')),'img_cache'=>count(glob($root.'/gazete/img/*.jpg')),'disk_free_gb'=>round(@disk_free_space($root)/1073741824,1),'time'=>date('c')]); exit; }
