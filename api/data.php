<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$file=__DIR__.'/data.json';
if($_SERVER['REQUEST_METHOD']==='GET'){
  if(!file_exists($file)){http_response_code(404);echo '{"error":"no data"}';exit;}
  readfile($file);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_once __DIR__.'/session.php';
  $raw=file_get_contents('php://input'); $jin=json_decode($raw,true);
  if(isset($jin['_token'])&&!isset($_SERVER['HTTP_X_TOKEN'])) $_SERVER['HTTP_X_TOKEN']=$jin['_token'];
  if(isset($jin['_session'])&&!isset($_SERVER['HTTP_X_SESSION'])) $_SERVER['HTTP_X_SESSION']=$jin['_session'];
  $need=(($_GET['action']??'')==='secret')?'settings':'news.edit';
  sky_require($need);
  if(($_GET['action']??'')==='secret'){
    $j=$jin; $allowed=['ANTHROPIC_KEY','REWRITE_MODEL','PEXELS_KEY'];
    $sec=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];
    foreach($allowed as $k) if(isset($j[$k])) $sec[$k]=trim((string)$j[$k]);
    file_put_contents(__DIR__.'/secrets.php',"<?php return ".var_export($sec,true).";\n",LOCK_EX);
    echo json_encode(['ok'=>true,'has_key'=>!empty($sec['ANTHROPIC_KEY']),'model'=>$sec['REWRITE_MODEL']??'claude-haiku-4-5-20251001']);exit;
  }
  $j=$jin;
  if(!is_array($j)||!isset($j['news'])){http_response_code(400);echo '{"error":"bad json"}';exit;}
  if(file_exists($file)) @copy($file,__DIR__.'/data.bak.json');
  file_put_contents($file,json_encode($j,JSON_UNESCAPED_UNICODE),LOCK_EX);
  echo '{"ok":true}';exit;
}
http_response_code(405);
