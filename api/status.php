<?php date_default_timezone_set("Europe/Istanbul");
/* SKYTÜRK — panel sayaçları: kuyruk, disk, sunucu, trafik */
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$data=file_exists(__DIR__.'/data.json')?json_decode(file_get_contents(__DIR__.'/data.json'),true):['news'=>[]];
$n=$data['news']??[];
$pendRw=count(array_filter($n,fn($x)=>!empty($x['auto'])&&empty($x['rw'])&&($x['rwTries']??0)<2));
$pendImg=count(array_filter($n,fn($x)=>empty($x['imgUrl'])&&!empty($x['rw'])&&($x['imgTries']??0)<2));
function dirsize($d){$s=0;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,FilesystemIterator::SKIP_DOTS)) as $f){$s+=$f->getSize();}return $s;}
$root=dirname(__DIR__); $used=0; try{$used=dirsize($root);}catch(Throwable $e){} if($used<1000000&&function_exists('shell_exec')){ $du=@shell_exec('du -sk '.escapeshellarg($root).' 2>/dev/null'); if($du&&preg_match('/^(\d+)/',$du,$dm)) $used=(int)$dm[1]*1024; }
$quotaGb=50; $load=function_exists('sys_getloadavg')?sys_getloadavg():[0,0,0]; $t0=microtime(true); $cores=1; if(is_readable('/proc/cpuinfo')){ $cores=max(1,preg_match_all('/^processor\s*:/m',(string)@file_get_contents('/proc/cpuinfo'))); } $cpuPct=min(100,round($load[0]/$cores*100));
$lastFetch=isset($data['updated'])?strtotime($data['updated']):0; $fetchAgeMin=$lastFetch?round((time()-$lastFetch)/60):null;
$dataMb=file_exists(__DIR__.'/data.json')?round(filesize(__DIR__.'/data.json')/1048576,2):0;
$stats=file_exists(__DIR__.'/stats.json')?(json_decode(file_get_contents(__DIR__.'/stats.json'),true)?:[]):[];
$days=[]; for($i=6;$i>=0;$i--){$k=date('Y-m-d',strtotime("-$i days"));$row=(isset($stats[$k])&&is_array($stats[$k]))?$stats[$k]:null;$days[]=['d'=>$k,'hits'=>$row['hits']??0,'uniq'=>isset($row['u'])?count($row['u']):($row['uniq']??0)];}
$today=end($days); $anom=$stats['_anom']??null; $anomLog=$stats['_anomLog']??[]; $blocked=count(array_filter($stats['_block']??[],fn($t)=>$t>time()-86400));
require_once __DIR__.'/config.php'; require_once __DIR__.'/session.php'; require_once __DIR__.'/credit.php';
$bud=file_exists(__DIR__.'/budget.json')?(json_decode(file_get_contents(__DIR__.'/budget.json'),true)?:[]):[]; $used=(int)($bud[date('Y-m-d')]??0); $cap=400; if(preg_match('/\$DAILY_BUDGET=(\d+);/',(string)@file_get_contents(__DIR__.'/fetch.php'),$mm)) $cap=(int)$mm[1];
echo json_encode([
 'budget'=>['used'=>$used,'cap'=>$cap],
 'credit'=>sky_can('settings')?sky_credit_status():null,
 'pending_rewrite'=>$pendRw,'pending_images'=>$pendImg,'total_news'=>count($n),
 'rewritten'=>count(array_filter($n,fn($x)=>!empty($x['rw'])&&empty($x['video']))),'with_image'=>count(array_filter($n,fn($x)=>!empty($x['imgUrl']))),
 'disk_used_mb'=>round($used/1048576,1),'disk_quota_gb'=>$quotaGb,'disk_pct'=>round($used/($quotaGb*1073741824)*100,2),
 'server'=>(function()use($load,$t0,$fetchAgeMin,$dataMb,$data){ $resp=round((microtime(true)-$t0)*1000); $cores=1; if(is_readable('/proc/cpuinfo')){ $c=preg_match_all('/^processor\s*:/m',(string)@file_get_contents('/proc/cpuinfo')); if($c>0) $cores=$c; } $cpuPct=min(100,round($load[0]/max(1,$cores)*100)); $cronOk=$fetchAgeMin!==null&&$fetchAgeMin<=20; $ok=$resp<1500&&$cronOk&&$dataMb<8;
   return ['php'=>PHP_VERSION,'host_load'=>round($load[0],2),'cores'=>$cores,'cpu_pct'=>$cpuPct,'load5'=>round($load[1]??0,2),'load15'=>round($load[2]??0,2),'cores'=>$GLOBALS['cores']??1,'cpu_pct'=>$GLOBALS['cpuPct']??0,'load5'=>round($load[1]??0,2),'load15'=>round($load[2]??0,2),'resp_ms'=>$resp,'data_mb'=>$dataMb,'cron_age_min'=>$fetchAgeMin,'cron_ok'=>$cronOk,'mem_mb'=>round(memory_get_usage(true)/1048576,1),'time'=>date('H:i'),'ok'=>$ok,'last_fetch'=>$data['updated']??null,
     'note'=>$ok?'Sağlıklı':(!$cronOk?'Cron gecikti ('.$fetchAgeMin.' dk)':($resp>=1500?'Yavaş yanıt':'Veri dosyası büyük'))]; })(),
 'traffic'=>['today_hits'=>$today['hits'],'today_uniq'=>$today['uniq'],'days'=>$days,'week_hits'=>array_sum(array_column($days,'hits')),'anomaly'=>$anom,'anomaly_log'=>array_slice($anomLog,0,10),'blocked_ips'=>$blocked]
],JSON_UNESCAPED_UNICODE);
