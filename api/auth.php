<?php
/* SKYTÜRK — giriş, oturum, kullanıcı yönetimi */
require_once __DIR__.'/config.php'; require_once __DIR__.'/session.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$in=json_decode(file_get_contents('php://input'),true)?:[]; $act=$_GET['action']??($in['action']??'');
$pub=fn($u)=>['username'=>$u['username'],'name'=>$u['name'],'role'=>$u['role'],'active'=>!empty($u['active']),'created'=>$u['created']??null,'last'=>$u['last']??null,'perms'=>SKY_ROLES[$u['role']]??[]];
switch($act){
 case 'status': $users=sky_users(); $me=sky_user(); echo json_encode(['setup_needed'=>count($users)===0,'user'=>$me?$pub($me):null,'roles'=>array_keys(SKY_ROLES)]); break;
 case 'setup': // ilk yönetici: yayın anahtarı gerekir
   if(count(sky_users())>0){http_response_code(400);echo '{"error":"kurulum tamamlanmış"}';break;}
   if(!sky_master()){http_response_code(403);echo '{"error":"yayın anahtarı gerekli"}';break;}
   $un=preg_replace('/[^a-z0-9._-]/','',mb_strtolower(trim($in['username']??''))); $pw=(string)($in['password']??'');
   if(strlen($un)<3||strlen($pw)<8){http_response_code(400);echo '{"error":"kullanıcı adı en az 3, şifre en az 8 karakter"}';break;}
   sky_save_users([['username'=>$un,'name'=>trim($in['name']??$un),'role'=>'yonetici','hash'=>password_hash($pw,PASSWORD_DEFAULT),'active'=>true,'created'=>date('c')]]);
   echo '{"ok":true}'; break;
 case 'login':
   $un=mb_strtolower(trim($in['username']??'')); $pw=(string)($in['password']??''); usleep(300000);
   $users=sky_users(); $found=null; foreach($users as &$u){ if($u['username']===$un&&!empty($u['active'])&&password_verify($pw,$u['hash'])){$u['last']=date('c');$found=$u;} } unset($u);
   if(!$found){http_response_code(401);echo '{"error":"Kullanıcı adı veya şifre hatalı"}';break;}
   sky_save_users($users); $s=sky_sessions(); $tok=bin2hex(random_bytes(24)); $s[$tok]=['user'=>$un,'exp'=>time()+12*3600,'ip'=>$_SERVER['REMOTE_ADDR']??'']; sky_save_sessions($s);
   echo json_encode(['ok'=>true,'session'=>$tok,'user'=>$pub($found)]); break;
 case 'logout': $tok=$_SERVER['HTTP_X_SESSION']??''; $s=sky_sessions(); unset($s[$tok]); sky_save_sessions($s); echo '{"ok":true}'; break;
 case 'users': sky_require('users'); echo json_encode(array_map($pub,sky_users()),JSON_UNESCAPED_UNICODE); break;
 case 'user_save': sky_require('users');
   $users=sky_users(); $un=preg_replace('/[^a-z0-9._-]/','',mb_strtolower(trim($in['username']??''))); $role=in_array($in['role']??'',array_keys(SKY_ROLES))?$in['role']:'izleyici';
   if(strlen($un)<3){http_response_code(400);echo '{"error":"kullanıcı adı en az 3 karakter"}';break;}
   $idx=null; foreach($users as $i=>$u) if($u['username']===$un) $idx=$i;
   if($idx===null){ if(strlen((string)($in['password']??''))<8){http_response_code(400);echo '{"error":"yeni kullanıcı için en az 8 karakterli şifre"}';break;}
     $users[]=['username'=>$un,'name'=>trim($in['name']??$un),'role'=>$role,'hash'=>password_hash($in['password'],PASSWORD_DEFAULT),'active'=>true,'created'=>date('c')]; }
   else { $users[$idx]['name']=trim($in['name']??$users[$idx]['name']); $users[$idx]['role']=$role; if(isset($in['active'])) $users[$idx]['active']=(bool)$in['active'];
     if(!empty($in['password'])){ if(strlen($in['password'])<8){http_response_code(400);echo '{"error":"şifre en az 8 karakter"}';break;} $users[$idx]['hash']=password_hash($in['password'],PASSWORD_DEFAULT); } }
   // en az bir aktif yönetici kalsın
   if(!count(array_filter($users,fn($u)=>$u['role']==='yonetici'&&!empty($u['active'])))){http_response_code(400);echo '{"error":"en az bir aktif yönetici kalmalı"}';break;}
   sky_save_users($users); echo json_encode(['ok'=>true,'users'=>array_map($pub,$users)],JSON_UNESCAPED_UNICODE); break;
 case 'user_delete': sky_require('users');
   $un=$in['username']??''; $users=array_values(array_filter(sky_users(),fn($u)=>$u['username']!==$un));
   if(!count(array_filter($users,fn($u)=>$u['role']==='yonetici'&&!empty($u['active'])))){http_response_code(400);echo '{"error":"en az bir aktif yönetici kalmalı"}';break;}
   sky_save_users($users); $s=sky_sessions(); foreach($s as $k=>$v) if($v['user']===$un) unset($s[$k]); sky_save_sessions($s); echo '{"ok":true}'; break;
 case 'password': $me=sky_user(); if(!$me){http_response_code(401);echo '{"error":"giriş gerekli"}';break;}
   if(strlen((string)($in['password']??''))<8){http_response_code(400);echo '{"error":"şifre en az 8 karakter"}';break;}
   $users=sky_users(); foreach($users as &$u) if($u['username']===$me['username']) $u['hash']=password_hash($in['password'],PASSWORD_DEFAULT); unset($u); sky_save_users($users); echo '{"ok":true}'; break;
 default: http_response_code(400); echo '{"error":"bilinmeyen işlem"}';
}
