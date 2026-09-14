<?php
/* SKYTÜRK — ziyaret sayacı + anomali dedektörü + bal küpü (çerezsiz; IP+UA günlük özet) */
header('Content-Type: application/json'); header('Cache-Control: no-store');
$ip=$_SERVER['REMOTE_ADDR']??''; $ua=$_SERVER['HTTP_USER_AGENT']??''; $d=date('Y-m-d'); $h=substr(hash('sha256',$ip.'|'.$ua.'|'.$d),0,16); $iph=substr(hash('sha256',$ip.'|'.$d),0,10);
$isBot=preg_match('/bot|crawl|spider|slurp|curl|wget|python|scrapy|httpclient|headless|phantom|selenium|java\/|go-http|libwww|okhttp/i',$ua)||$ua==='';
$f=__DIR__.'/stats.json'; $fp=fopen($f,'c+'); if(!$fp){echo '{"ok":false}';exit;} flock($fp,LOCK_EX);
$raw=stream_get_contents($fp); $s=json_decode($raw?:'{}',true)?:[];
/* bal küpü: yalnızca botların tıkladığı görünmez bağlantı */
if(isset($_GET['hp'])){ $s['_block']=$s['_block']??[]; $s['_block'][$iph]=time(); }
if(isset($s['_block'][$iph])&&$s['_block'][$iph]>time()-86400){ ftruncate($fp,0); rewind($fp); fwrite($fp,json_encode($s)); flock($fp,LOCK_UN); fclose($fp); echo '{"ok":false,"blocked":true}'; exit; }
if(!isset($s[$d])) $s[$d]=['hits'=>0,'u'=>[]];
if(!$isBot){ $s[$d]['hits']++; $s[$d]['u'][$h]=1; }
/* dakikalık kova (son 120 dk): hits, tekil, bot, en yoğun IP */
$mk=(string)(floor(time()/60)*60); $s['_m']=$s['_m']??[]; $m=&$s['_m']; if(!isset($m[$mk])) $m[$mk]=['h'=>0,'b'=>0,'u'=>[],'ip'=>[]];
$m[$mk]['h']++; if($isBot) $m[$mk]['b']++; $m[$mk]['u'][$h]=1; $m[$mk]['ip'][$iph]=($m[$mk]['ip'][$iph]??0)+1;
foreach(array_keys($m) as $k) if((int)$k<time()-120*60) unset($m[$k]);
foreach(array_keys($s) as $k){ if($k[0]==='_') continue; if($k<date('Y-m-d',strtotime('-45 days'))) unset($s[$k]); elseif($k!==$d&&isset($s[$k]['u'])&&is_array($s[$k]['u'])){ $s[$k]['uniq']=count($s[$k]['u']); unset($s[$k]['u']); } }
/* anomali değerlendirmesi (her 60 sn'de bir) */
$s['_evalN']=($s['_evalN']??0)+1; if(($s['_eval']??0)<time()-60||$s['_evalN']>=40){ $s['_eval']=time(); $s['_evalN']=0; $now=time(); $last5=0; $prev=[]; $ipAgg=[]; $bot5=0;
  foreach($m as $k=>$v){ $age=$now-(int)$k; if($age<=300){ $last5+=$v['h']; $bot5+=$v['b']; foreach($v['ip'] as $i=>$c) $ipAgg[$i]=($ipAgg[$i]??0)+$c; } elseif($age<=3900){ $prev[]=$v['h']; } }
  $base=0; if($prev){ sort($prev); $base=$prev[(int)(count($prev)/2)]*5; } $base=max($base,10);
  arsort($ipAgg); $topIp=$ipAgg?reset($ipAgg):0; $topShare=$last5>0?$topIp/$last5:0;
  $why=[]; if($last5>200&&$last5>$base*5) $why[]='trafik sıçraması: 5 dk\'da '.$last5.' istek (taban '.$base.')'; if($last5>100&&$topShare>0.4) $why[]='tek IP %'.round($topShare*100).' pay ('.$topIp.' istek)'; if($last5>100&&$bot5/$last5>0.5) $why[]='bot oranı %'.round($bot5/$last5*100);
  $s['_anom']=['t'=>$now,'last5'=>$last5,'base'=>$base,'topShare'=>round($topShare,2),'bot'=>$bot5,'why'=>$why];
  if($why){ if(($s['_anomAlert']??0)<$now-1800){ $s['_anomAlert']=$now; $s['_anomLog']=array_slice(array_merge([['t'=>date('c'),'why'=>$why,'last5'=>$last5]],$s['_anomLog']??[]),0,50);
    @require_once __DIR__.'/credit.php'; if(function_exists('sky_credit_load')){ $c=sky_credit_load(); if(!empty($c['email'])) sky_credit_mail($c,'SKYTÜRK — şüpheli trafik uyarısı',"Son 5 dakikada olağan dışı trafik:\n- ".implode("\n- ",$why)."\n\nCMS > Kontrol Paneli'nde ayrıntı var. Saldırı sürüyorsa Cloudflare 'Under Attack' modunu açın."); } }
    if($topShare>0.4&&$last5>100){ $s['_block']=$s['_block']??[]; $s['_block'][array_key_first($ipAgg)]=$now; } }
}
ftruncate($fp,0); rewind($fp); fwrite($fp,json_encode($s)); fflush($fp); flock($fp,LOCK_UN); fclose($fp);
echo '{"ok":true}';
