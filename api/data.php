<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$file=__DIR__.'/data.json';
if($_SERVER['REQUEST_METHOD']==='GET'){
  if(!file_exists($file)){http_response_code(404);echo '{"error":"no data"}';exit;}
  readfile($file);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&in_array($_GET['action']??'',['comment','contact'])){
  $jin=json_decode(file_get_contents('php://input'),true)?:[]; $act=$_GET['action']; $ip=$_SERVER['REMOTE_ADDR']??''; $rl=__DIR__.'/ratelimit.json'; $R=file_exists($rl)?(json_decode(file_get_contents($rl),true)?:[]):[]; $k=$act.':'.$ip; $R=array_filter($R,fn($v)=>$v>time()-3600); if(count(array_filter(array_keys($R),fn($x)=>strpos($x,$k)===0))>=10){http_response_code(429);echo '{"error":"çok fazla istek"}';exit;} $R[$k.':'.uniqid()]=time(); file_put_contents($rl,json_encode($R));
  if(!empty($jin['website'])){echo '{"ok":true}';exit;} // bal küpü
  $cur=file_exists($file)?(json_decode(file_get_contents($file),true)?:[]):[];
  if($act==='comment'){ $t=trim(mb_substr($jin['text']??'',0,1000)); $name=trim(mb_substr($jin['name']??'Ziyaretçi',0,60)); $on=(int)($jin['on']??0); if(mb_strlen($t)<3||!$on){http_response_code(400);echo '{"error":"eksik"}';exit;}
    $cur['comments']=$cur['comments']??[]; array_unshift($cur['comments'],['id'=>(int)(microtime(true)*1000),'u'=>$name?:'Ziyaretçi','t'=>$t,'on'=>$on,'st'=>'pending','d'=>date('d.m.Y H:i'),'ip'=>substr(hash('sha256',$ip),0,12)]); $cur['comments']=array_slice($cur['comments'],0,2000); file_put_contents($file,json_encode($cur,JSON_UNESCAPED_UNICODE),LOCK_EX); echo '{"ok":true,"pending":true}'; exit; }
  if($act==='contact'){ $name=trim(mb_substr($jin['name']??'',0,80)); $mail=trim(mb_substr($jin['email']??'',0,120)); $sub=trim(mb_substr($jin['subject']??'',0,120)); $msg=trim(mb_substr($jin['message']??'',0,3000)); if(!$name||!$msg){http_response_code(400);echo '{"error":"eksik"}';exit;}
    $cur['messages']=$cur['messages']??[]; array_unshift($cur['messages'],['id'=>(int)(microtime(true)*1000),'name'=>$name,'email'=>$mail,'subject'=>$sub?:'Konu belirtilmedi','message'=>$msg,'d'=>date('d.m.Y H:i'),'read'=>false]); $cur['messages']=array_slice($cur['messages'],0,1000); file_put_contents($file,json_encode($cur,JSON_UNESCAPED_UNICODE),LOCK_EX); echo '{"ok":true}'; exit; }
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_once __DIR__.'/session.php';
  $raw=file_get_contents('php://input'); $jin=json_decode($raw,true);
  if(isset($jin['_token'])&&!isset($_SERVER['HTTP_X_TOKEN'])) $_SERVER['HTTP_X_TOKEN']=$jin['_token'];
  if(isset($jin['_session'])&&!isset($_SERVER['HTTP_X_SESSION'])) $_SERVER['HTTP_X_SESSION']=$jin['_session'];
  $need=in_array($_GET['action']??'',['secret','credit','settings'])?'settings':((($_GET['action']??'')==='gazete')?'rss':'news.edit');
  sky_require($need);
  if(($_GET['action']??'')==='settings'){ $f=__DIR__.'/data.json'; $cur=file_exists($f)?(json_decode(file_get_contents($f),true)?:[]):[]; $allowed=['siteName','slogan','heroCount','city','desc','seoTitle','seoDesc','gsc','sitemap','gnews','social','hero','cats','tagsExtra','weather','markets','standings','traffic','ads','pages','feeds','theme','smtp','security','kvkk','fixtureLeagues','authors']; $st=$cur['settings']??[]; foreach($allowed as $k) if(array_key_exists($k,$jin)) $st[$k]=is_array($jin[$k])?$jin[$k]:(is_numeric($jin[$k])?(int)$jin[$k]:trim((string)$jin[$k])); $cur['settings']=$st; file_put_contents($f,json_encode($cur,JSON_UNESCAPED_UNICODE),LOCK_EX); sky_audit('genel ayarlar',json_encode($st,JSON_UNESCAPED_UNICODE)); echo json_encode(['ok'=>true,'settings'=>$st],JSON_UNESCAPED_UNICODE); exit; }
  if(($_GET['action']??'')==='gazete'){ require_once __DIR__.'/gazete.php'; $r=skyturk_gazete_build(!empty($jin['force'])); sky_audit('gazete üretimi',json_encode($r['issue']['no']??$r)); echo json_encode($r,JSON_UNESCAPED_UNICODE); exit; }
  if(($_GET['action']??'')==='credit'){ require_once __DIR__.'/credit.php'; $c=sky_credit_set((float)($jin['balance']??0),$jin['email']??null); sky_audit('kredi güncellendi','$'.$c['balance'].' · '.$c['email']); echo json_encode(sky_credit_status()); exit; }
  if(($_GET['action']??'')==='secret'){
    $j=$jin; $allowed=['ANTHROPIC_KEY','REWRITE_MODEL','PEXELS_KEY'];
    $sec=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];
    foreach($allowed as $k) if(isset($j[$k])) $sec[$k]=trim((string)$j[$k]);
    file_put_contents(__DIR__.'/secrets.php',"<?php return ".var_export($sec,true).";\n",LOCK_EX); sky_audit('ayar değişikliği',implode(', ',array_keys(array_intersect_key($j,array_flip($allowed)))));
    echo json_encode(['ok'=>true,'has_key'=>!empty($sec['ANTHROPIC_KEY']),'model'=>$sec['REWRITE_MODEL']??'claude-haiku-4-5-20251001']);exit;
  }
  $j=$jin;
  if(!is_array($j)||!isset($j['news'])){http_response_code(400);echo '{"error":"bad json"}';exit;}
  $old=file_exists($file)?(json_decode(file_get_contents($file),true)?:[]):[]; foreach(['settings','astro','updated'] as $keep) if(isset($old[$keep])&&!isset($j[$keep])) $j[$keep]=$old[$keep]; $on=[]; foreach($old['news']??[] as $x) $on[$x['id']]=$x['st']??''; $nn=[]; foreach($j['news'] as $x) $nn[$x['id']]=$x['st']??'';
  $add=array_diff_key($nn,$on); $del=array_diff_key($on,$nn); $chg=0; foreach($nn as $k=>$v) if(isset($on[$k])&&$on[$k]!==$v) $chg++;
  $titles=[]; foreach($j['news'] as $x) if(isset($add[$x['id']])) $titles[]=mb_substr($x['t']??'',0,60);
  if($add||$del||$chg) sky_audit('haber güncelleme','eklendi '.count($add).', silindi '.count($del).', durum değişti '.$chg.($titles?' · '.implode(' | ',array_slice($titles,0,3)):''));
  if(file_exists($file)) @copy($file,__DIR__.'/data.bak.json');
  file_put_contents($file,json_encode($j,JSON_UNESCAPED_UNICODE),LOCK_EX);
  echo '{"ok":true}';exit;
}
http_response_code(405);
