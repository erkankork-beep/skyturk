<?php
/* SKYTÜRK — medya yükleme/listeleme/silme (yetki: media) */
require_once __DIR__.'/config.php'; require_once __DIR__.'/session.php';
header('Content-Type: application/json; charset=utf-8');
$dir=dirname(__DIR__).'/media'; if(!is_dir($dir)) @mkdir($dir,0755,true); $act=$_GET['action']??'list';
if($act==='list'){ sky_require('media'); $out=[]; foreach(glob($dir.'/*.{jpg,jpeg,png,gif,webp,pdf,mp4}',GLOB_BRACE) as $f){ $out[]=['name'=>basename($f),'url'=>'media/'.basename($f),'size'=>filesize($f),'t'=>filemtime($f)]; } usort($out,fn($a,$b)=>$b['t']<=>$a['t']); echo json_encode(['files'=>$out,'used_mb'=>round(array_sum(array_column($out,'size'))/1048576,2)],JSON_UNESCAPED_UNICODE); exit; }
if($act==='upload'){ sky_require('media'); if(empty($_FILES['file'])){http_response_code(400);echo '{"error":"dosya yok"}';exit;} $f=$_FILES['file']; if($f['size']>15*1048576){http_response_code(400);echo '{"error":"15 MB üstü"}';exit;}
  $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)); if(!in_array($ext,['jpg','jpeg','png','gif','webp','pdf','mp4'])){http_response_code(400);echo '{"error":"izin verilmeyen tür"}';exit;}
  $mime=mime_content_type($f['tmp_name']); if(in_array($ext,['jpg','jpeg','png','gif','webp'])&&strpos($mime,'image/')!==0){http_response_code(400);echo '{"error":"geçersiz görsel"}';exit;}
  $name=date('Ymd-His').'-'.preg_replace('/[^a-z0-9]+/','-',strtolower(pathinfo($f['name'],PATHINFO_FILENAME))).'.'.$ext; if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)){http_response_code(500);echo '{"error":"yazılamadı"}';exit;}
  if(in_array($ext,['jpg','jpeg','png','webp'])&&function_exists('imagecreatefromstring')){ $im=@imagecreatefromstring(file_get_contents($dir.'/'.$name)); if($im&&imagesx($im)>1600){ $r=1600/imagesx($im); $t=imagecreatetruecolor(1600,(int)(imagesy($im)*$r)); imagecopyresampled($t,$im,0,0,0,0,1600,(int)(imagesy($im)*$r),imagesx($im),imagesy($im)); if($ext==='png') imagepng($t,$dir.'/'.$name); else imagejpeg($t,$dir.'/'.$name,86); } }
  sky_audit('medya yükleme',$name); echo json_encode(['ok'=>true,'url'=>'media/'.$name,'name'=>$name]); exit; }
if($act==='delete'){ sky_require('media'); $jin=json_decode(file_get_contents('php://input'),true)?:[]; $n=basename($jin['name']??''); if($n&&file_exists($dir.'/'.$n)){ unlink($dir.'/'.$n); sky_audit('medya silme',$n); echo '{"ok":true}'; } else { http_response_code(404); echo '{"error":"yok"}'; } exit; }
http_response_code(400); echo '{"error":"işlem"}';
