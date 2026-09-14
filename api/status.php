<?php
/* SKYTÜRK — panel sayaçları: kuyruk, disk, sunucu, trafik */
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$data=file_exists(__DIR__.'/data.json')?json_decode(file_get_contents(__DIR__.'/data.json'),true):['news'=>[]];
$n=$data['news']??[];
$pendRw=count(array_filter($n,fn($x)=>!empty($x['auto'])&&empty($x['rw'])&&($x['rwTries']??0)<2));
$pendImg=count(array_filter($n,fn($x)=>empty($x['imgUrl'])&&!empty($x['rw'])&&($x['imgTries']??0)<2));
function dirsize($d){$s=0;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,FilesystemIterator::SKIP_DOTS)) as $f){$s+=$f->getSize();}return $s;}
$root=dirname(__DIR__); $used=0; try{$used=dirsize($root);}catch(Throwable $e){}
$quotaGb=50; $load=function_exists('sys_getloadavg')?sys_getloadavg():[0,0,0];
$stats=file_exists(__DIR__.'/stats.json')?(json_decode(file_get_contents(__DIR__.'/stats.json'),true)?:[]):[];
$days=[]; for($i=6;$i>=0;$i--){$k=date('Y-m-d',strtotime("-$i days"));$row=$stats[$k]??null;$days[]=['d'=>$k,'hits'=>$row['hits']??0,'uniq'=>isset($row['u'])?count($row['u']):($row['uniq']??0)];}
$today=end($days);
require_once __DIR__.'/credit.php';
echo json_encode([
 'credit'=>sky_credit_status(),
 'pending_rewrite'=>$pendRw,'pending_images'=>$pendImg,'total_news'=>count($n),
 'rewritten'=>count(array_filter($n,fn($x)=>!empty($x['rw'])&&empty($x['video']))),'with_image'=>count(array_filter($n,fn($x)=>!empty($x['imgUrl']))),
 'disk_used_mb'=>round($used/1048576,1),'disk_quota_gb'=>$quotaGb,'disk_pct'=>round($used/($quotaGb*1073741824)*100,2),
 'server'=>['php'=>PHP_VERSION,'load'=>round($load[0],2),'mem_mb'=>round(memory_get_usage(true)/1048576,1),'time'=>date('H:i'),'ok'=>$load[0]<4,'last_fetch'=>$data['updated']??null],
 'traffic'=>['today_hits'=>$today['hits'],'today_uniq'=>$today['uniq'],'days'=>$days,'week_hits'=>array_sum(array_column($days,'hits'))]
],JSON_UNESCAPED_UNICODE);
