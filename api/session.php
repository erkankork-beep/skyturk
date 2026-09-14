<?php
/* SKYTÜRK — oturum ve yetki yardımcıları */
const SKY_ROLES=[
  'yonetici'=>['*'],
  'editor'=>['news.view','news.create','news.edit','news.publish','news.delete','headline','polls','comments','rss','media'],
  'muhabir'=>['news.view','news.create','news.edit','media'],
  'moderator'=>['news.view','comments'],
  'izleyici'=>['news.view'],
];
function sky_users(){$f=__DIR__.'/users.json';return file_exists($f)?(json_decode(file_get_contents($f),true)?:[]):[];}
function sky_save_users($u){file_put_contents(__DIR__.'/users.json',json_encode(array_values($u),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);}
function sky_sessions(){$f=__DIR__.'/sessions.json';$s=file_exists($f)?(json_decode(file_get_contents($f),true)?:[]):[];$now=time();foreach($s as $k=>$v)if(($v['exp']??0)<$now)unset($s[$k]);return $s;}
function sky_save_sessions($s){file_put_contents(__DIR__.'/sessions.json',json_encode($s),LOCK_EX);}
function sky_master(){ $t=$_SERVER['HTTP_X_TOKEN']??($_GET['key']??''); return defined('SKYTURK_TOKEN')&&$t!==''&&hash_equals(SKYTURK_TOKEN,(string)$t); }
function sky_user(){
  static $u=null; if($u!==null) return $u?:null;
  $tok=$_SERVER['HTTP_X_SESSION']??($_GET['session']??''); if(!$tok){$u=false;return null;}
  $s=sky_sessions(); if(!isset($s[$tok])){$u=false;return null;}
  foreach(sky_users() as $x) if($x['username']===$s[$tok]['user']&&!empty($x['active'])){ $u=$x; return $u; }
  $u=false; return null;
}
function sky_can($perm){ if(sky_master()) return true; $u=sky_user(); if(!$u) return false; $p=SKY_ROLES[$u['role']]??[]; return in_array('*',$p)||in_array($perm,$p); }
function sky_require($perm){ if(!sky_can($perm)){ http_response_code(403); echo json_encode(['error'=>'forbidden','need'=>$perm,'user'=>sky_user()['username']??null]); exit; } }
