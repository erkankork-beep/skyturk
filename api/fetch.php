<?php
/* SKYTÜRK — RSS otomasyonu.
   Cron:  /usr/local/bin/php /home/KULLANICI/public_html/api/fetch.php
   Elle:  https://alanadi/api/fetch.php?key=TOKEN                      */
require_once __DIR__.'/config.php';
$cli = PHP_SAPI==='cli';
if(!$cli){ header('Content-Type: application/json; charset=utf-8');
  if(!hash_equals(SKYTURK_TOKEN, $_GET['key']??'')){http_response_code(403);echo '{"error":"forbidden"}';exit;} }
date_default_timezone_set('Europe/Istanbul');
$file=__DIR__.'/data.json'; $log=__DIR__.'/fetch.log';
$feeds=require __DIR__.'/feeds.php';
$KEEP_DAYS=7; $MAX_ITEMS=400; $PER_FEED=25;

$data=file_exists($file)?json_decode(file_get_contents($file),true):null;
if(!is_array($data)) $data=['news'=>[],'polls'=>null];
$news=$data['news']??[];
$seen=[]; foreach($news as $n){ if(!empty($n['src'])) $seen[$n['src']]=true; }

function get($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_TIMEOUT=>15,
    CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; SKYTURK-RSS/1.0)',CURLOPT_SSL_VERIFYPEER=>true]);
  $r=curl_exec($ch); curl_close($ch); return $r?:false;
}
function clean($s){ $s=html_entity_decode(strip_tags((string)$s),ENT_QUOTES|ENT_HTML5,'UTF-8'); return trim(preg_replace('/\s+/u',' ',$s)); }
function cut($s,$n){ return mb_strlen($s)>$n ? mb_substr($s,0,$n-1).'…' : $s; }

$added=0; $errors=[];
foreach($feeds as [$url,$cat,$srcName]){
  $xml=get($url); if(!$xml){ $errors[]=$url; continue; }
  libxml_use_internal_errors(true);
  $doc=simplexml_load_string($xml); if(!$doc){ $errors[]=$url; continue; }
  $items=$doc->channel->item ?? $doc->entry ?? [];
  $i=0;
  foreach($items as $it){
    if(++$i>$PER_FEED) break;
    $link=clean($it->link['href'] ?? $it->link ?? '');
    $title=clean($it->title ?? ''); if(!$link||!$title) continue;
    if(isset($seen[$link])) continue;
    $desc=cut(clean($it->description ?? $it->summary ?? $it->content ?? ''),280);
    $pub=strtotime((string)($it->pubDate ?? $it->published ?? $it->updated ?? '')) ?: time();
    $news[]=[
      'id'=>abs(crc32($link))%900000000+100000000,
      'cat'=>$cat,'t'=>cut($title,160),'s'=>$desc,
      'd'=>date('d.m.Y H:i',$pub),'ts'=>$pub,
      'by'=>$srcName,'v'=>0,'st'=>'Yayında','tags'=>[],
      'auto'=>true,'src'=>$link,'srcName'=>$srcName
    ];
    $seen[$link]=true; $added++;
  }
}
/* Temizlik: otomatik haberlerde eski olanları at, elle girilenleri koru */
$cut=time()-$KEEP_DAYS*86400;
$news=array_values(array_filter($news,fn($n)=>empty($n['auto'])||(($n['ts']??time())>=$cut)));
usort($news,function($a,$b){ return tsOf($b)<=>tsOf($a); });
function tsOf($n){ if(isset($n['ts']))return $n['ts']; if(preg_match('/(\d\d)\.(\d\d)\.(\d{4}) (\d\d):(\d\d)/',$n['d']??'',$m)) return mktime($m[4],$m[5],0,$m[2],$m[1],$m[3]); return 0; }
$news=array_slice($news,0,$MAX_ITEMS);
$data['news']=$news; $data['updated']=date('c');
if(file_exists($file)) @copy($file,__DIR__.'/data.bak.json');
file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE),LOCK_EX);
$msg=date('d.m.Y H:i').' — eklendi: '.$added.', toplam: '.count($news).($errors?' | hata: '.implode(' ',$errors):'');
file_put_contents($log,$msg."\n".substr((string)@file_get_contents($log),0,20000));
echo $cli?$msg."\n":json_encode(['ok'=>true,'added'=>$added,'total'=>count($news),'errors'=>$errors],JSON_UNESCAPED_UNICODE);
