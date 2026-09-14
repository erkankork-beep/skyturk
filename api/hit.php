<?php
/* SKYTÜRK — basit ziyaret sayacı (çerezsiz; IP+UA günlük özet, kişisel veri saklanmaz) */
header('Content-Type: application/json'); header('Cache-Control: no-store');
$f=__DIR__.'/stats.json'; $d=date('Y-m-d'); $h=substr(hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.($_SERVER['HTTP_USER_AGENT']??'').'|'.$d),0,16);
$fp=fopen($f,'c+'); if(!$fp){echo '{"ok":false}';exit;} flock($fp,LOCK_EX);
$raw=stream_get_contents($fp); $s=json_decode($raw?:'{}',true)?:[];
if(!isset($s[$d])) $s[$d]=['hits'=>0,'u'=>[]];
$s[$d]['hits']++; $s[$d]['u'][$h]=1;
foreach(array_keys($s) as $k){ if($k<date('Y-m-d',strtotime('-45 days'))) unset($s[$k]); elseif($k!==$d&&isset($s[$k]['u'])&&is_array($s[$k]['u'])){ $s[$k]['uniq']=count($s[$k]['u']); unset($s[$k]['u']); } }
ftruncate($fp,0); rewind($fp); fwrite($fp,json_encode($s)); fflush($fp); flock($fp,LOCK_UN); fclose($fp);
echo '{"ok":true}';
