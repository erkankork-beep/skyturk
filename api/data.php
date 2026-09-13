<?php
require __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$file=__DIR__.'/data.json';
if($_SERVER['REQUEST_METHOD']==='GET'){
  if(!file_exists($file)){http_response_code(404);echo '{"error":"no data"}';exit;}
  readfile($file);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $tok=$_SERVER['HTTP_X_TOKEN']??'';
  if(!hash_equals(SKYTURK_TOKEN,$tok)){http_response_code(403);echo '{"error":"forbidden"}';exit;}
  $body=file_get_contents('php://input');
  $j=json_decode($body,true);
  if(!is_array($j)||!isset($j['news'])){http_response_code(400);echo '{"error":"bad json"}';exit;}
  if(file_exists($file)) @copy($file,__DIR__.'/data.bak.json');
  file_put_contents($file,json_encode($j,JSON_UNESCAPED_UNICODE),LOCK_EX);
  echo '{"ok":true}';exit;
}
http_response_code(405);
