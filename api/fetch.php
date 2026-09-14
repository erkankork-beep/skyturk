<?php
/* SKYTÜRK — RSS otomasyonu.
   Cron:  /usr/local/bin/php /home/KULLANICI/public_html/api/fetch.php
   Elle:  https://alanadi/api/fetch.php?key=TOKEN                      */
require_once __DIR__.'/config.php';
$cli = PHP_SAPI==='cli';
if(!$cli){ header('Content-Type: application/json; charset=utf-8'); require_once __DIR__.'/session.php'; sky_require('rss'); sky_audit('rss tetikleme','elle'); }
date_default_timezone_set('Europe/Istanbul');
@ignore_user_abort(true); @set_time_limit(180);
$file=__DIR__.'/data.json'; $log=__DIR__.'/fetch.log';
$feeds=require __DIR__.'/feeds.php';
$KEEP_DAYS=7; $MAX_ITEMS=600; $PER_FEED=25;
$PER_CAT=3; $MAX_AGE_H=24; $DAILY_BUDGET=500; // tur başına kategori başına en fazla 3 yeni haber; 24 saatten eski alınmaz; günlük özgünleştirme tavanı
$catNew=[]; $dayKey=date('Y-m-d'); $budgetFile=__DIR__.'/budget.json'; $budget=file_exists($budgetFile)?(json_decode(file_get_contents($budgetFile),true)?:[]):[]; $usedToday=(int)($budget[$dayKey]??0);

$data=file_exists($file)?json_decode(file_get_contents($file),true):null;
if(!is_array($data)) $data=['news'=>[],'polls'=>null];
$news=$data['news']??[];
$seen=[]; foreach($news as $n){ if(!empty($n['src'])) $seen[$n['src']]=true; }

function get($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>4,
    CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; SKYTURK-RSS/1.0)',CURLOPT_SSL_VERIFYPEER=>true]);
  $r=curl_exec($ch); curl_close($ch); return $r?:false;
}
function clean($s){ $s=html_entity_decode(strip_tags((string)$s),ENT_QUOTES|ENT_HTML5,'UTF-8'); return trim(preg_replace('/\s+/u',' ',$s)); }
function cut($s,$n){ return mb_strlen($s)>$n ? mb_substr($s,0,$n-1).'…' : $s; }

$added=0; $errors=[]; $perFeed=[];
foreach($feeds as [$url,$cat,$srcName]){
  $xml=get($url); if(!$xml){ $errors[]=$url; $perFeed[$url]='ERİŞİLEMEDİ'; continue; }
  libxml_use_internal_errors(true);
  $doc=simplexml_load_string($xml); if(!$doc){ $errors[]=$url; $perFeed[$url]='XML DEĞİL'; continue; }
  $items=$doc->channel->item ?? $doc->entry ?? [];
  $i=0; $new=0;
  foreach($items as $it){
    if(++$i>$PER_FEED) break;
    $link=clean($it->link['href'] ?? $it->link ?? '');
    $title=clean($it->title ?? ''); if(!$link||!$title) continue;
    if(isset($seen[$link])) continue;
    $pub0=strtotime((string)($it->pubDate ?? $it->published ?? $it->updated ?? '')) ?: time();
    if(time()-$pub0>$MAX_AGE_H*3600) continue;                 // eski haber
    if(($catNew[$cat]??0)>=$PER_CAT) continue;                  // bu kategori bu turda doldu
    if($usedToday+$added>=$DAILY_BUDGET) break 2;               // günlük tavan
    $desc=cut(clean($it->description ?? $it->summary ?? $it->content ?? ''),280);
    $enc=$it->children('http://purl.org/rss/1.0/modules/content/')->encoded ?? null; $full=$enc?clean((string)$enc):'';
    $pub=strtotime((string)($it->pubDate ?? $it->published ?? $it->updated ?? '')) ?: time();
    $news[]=[
      'id'=>abs(crc32($link))%900000000+100000000,
      'cat'=>$cat,'t'=>cut($title,160),'s'=>$desc,
      'd'=>date('d.m.Y H:i',$pub),'ts'=>$pub,
      'by'=>$srcName,'v'=>0,'st'=>'Yayında','tags'=>[],
      'auto'=>true,'src'=>$link,'srcName'=>$srcName,'fullLen'=>mb_strlen($full)
    ];
    $seen[$link]=true; $added++; $new++; $catNew[$cat]=($catNew[$cat]??0)+1;
  }
  $perFeed[$url]=$new.' yeni / '.count($items).' toplam';
}
/* YouTube videoları */
$vfeeds=file_exists(__DIR__.'/videos.php')?(include __DIR__.'/videos.php'):[]; $vadded=0;
foreach($vfeeds as [$url,$cat,$srcName]){
  $xml=get($url); if(!$xml){ $perFeed[$url]='ERİŞİLEMEDİ'; continue; }
  libxml_use_internal_errors(true); $doc=simplexml_load_string($xml); if(!$doc){ $perFeed[$url]='XML DEĞİL'; continue; }
  $i=0; $new=0;
  foreach($doc->entry as $e){
    if(++$i>10) break;
    $yt=$e->children('http://www.youtube.com/xml/schemas/2015'); $vid=(string)($yt->videoId??''); if(!$vid) continue;
    $link='https://www.youtube.com/watch?v='.$vid; if(isset($seen[$link])) continue;
    $media=$e->children('http://search.yahoo.com/mrss/'); $desc=cut(clean((string)($media->group->description??'')),240);
    $pub=strtotime((string)$e->published)?:time();
    $news[]=['id'=>abs(crc32($link))%900000000+100000000,'cat'=>$cat,'t'=>cut(clean((string)$e->title),160),'s'=>$desc,'d'=>date('d.m.Y H:i',$pub),'ts'=>$pub,
      'by'=>$srcName,'v'=>0,'st'=>'Yayında','tags'=>['video'],'auto'=>true,'src'=>$link,'srcName'=>$srcName.' / YouTube','video'=>true,'ytId'=>$vid,
      'imgUrl'=>'https://i.ytimg.com/vi/'.$vid.'/hqdefault.jpg','imgCredit'=>'Görsel: '.$srcName.' / YouTube','imgLink'=>$link,'rw'=>true];
    $seen[$link]=true; $added++; $new++; $vadded++;
  }
  $perFeed[$url]=$new.' yeni video / '.$i.' toplam';
}
/* Temizlik: otomatik haberlerde eski olanları at, elle girilenleri koru */
$cut=time()-$KEEP_DAYS*86400;
$news=array_values(array_filter($news,fn($n)=>empty($n['auto'])||(($n['ts']??time())>=$cut)));
usort($news,function($a,$b){ return tsOf($b)<=>tsOf($a); });
function tsOf($n){ if(isset($n['ts']))return $n['ts']; if(preg_match('/(\d\d)\.(\d\d)\.(\d{4}) (\d\d):(\d\d)/',$n['d']??'',$m)) return mktime($m[4],$m[5],0,$m[2],$m[1],$m[3]); return 0; }
$news=array_slice($news,0,$MAX_ITEMS);
require_once __DIR__.'/rewrite.php'; $rw=skyturk_rewrite($news,max(20,min(40,$added)),80); // yeni gelenler + birikim varsa en az 20
$budget=[$dayKey=>$usedToday+($rw['rewritten']??0)]; file_put_contents($budgetFile,json_encode($budget)); $rw['gunluk_kullanim']=$budget[$dayKey].'/'.$DAILY_BUDGET;
require_once __DIR__.'/images.php'; $im=skyturk_images($news,max(20,min(40,$added)),40); $rw['images']=$im;
$data['news']=$news; $rw['astro']=skyturk_astro($data);
if((int)date('G')>=9){ $iss=__DIR__.'/../gazete/issues.json'; $have=false; if(file_exists($iss)) foreach(json_decode(file_get_contents($iss),true)?:[] as $is) if(($is['date']??'')===date('Y-m-d')) $have=true;
  if(!$have){ file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE),LOCK_EX); require_once __DIR__.'/gazete.php'; $rw['gazete']=skyturk_gazete_build(); } }
require_once __DIR__.'/fixtures.php'; $fx=__DIR__.'/fixtures.json'; if(!file_exists($fx)||time()-filemtime($fx)>1800) $rw['fikstur']=skyturk_fixtures();
require_once __DIR__.'/credit.php'; $rw['kredi']=sky_credit_spend((float)($rw['est_cost_usd']??0)+(!empty($rw['astro']['ok'])?0.01:0),$rw['api_error']??null);
$data['news']=$news; $data['updated']=date('c');
if(file_exists($file)) @copy($file,__DIR__.'/data.bak.json');
file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE),LOCK_EX);
$msg=date('d.m.Y H:i').' — eklendi: '.$added.', toplam: '.count($news).' | özgünleştirme: '.json_encode($rw,JSON_UNESCAPED_UNICODE).($errors?' | hata: '.implode(' ',$errors):'');
file_put_contents($log,$msg."\n".substr((string)@file_get_contents($log),0,20000));
echo $cli?$msg."\n":json_encode(['ok'=>true,'added'=>$added,'total'=>count($news),'errors'=>$errors,'feeds'=>$perFeed,'rewrite'=>$rw],JSON_UNESCAPED_UNICODE);
