<?php
/* SKYTÜRK — açık lisanslı görsel eşleme (Pexels → Openverse yedek). Haberlere imgUrl/imgCredit ekler. */
function skyturk_images(array &$news, int $maxItems=30, int $budgetSec=40): array {
  $secrets=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];
  $pexels=$secrets['PEXELS_KEY']??''; $start=time(); $done=0; $fail=0; $src=['pexels'=>0,'openverse'=>0];
  $get=function($url,$hdr=[]){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>array_merge(['User-Agent: SKYTURK/1.0 (news; contact@skyturk)'],$hdr)]);$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$c,$r];};
  foreach($news as &$n){
    if(!empty($n['imgUrl'])||($n['imgTries']??0)>=2) continue;
    $q=trim($n['imgQuery']??''); if(!$q){ if(empty($n['rw'])) continue; $q=trim(preg_replace('/[^a-z ]/i',' ',mb_substr($n['imgPrompt']??'',0,60))); }
    if(!$q) continue;
    if($done>=$maxItems||time()-$start>$budgetSec) break;
    $n['imgTries']=($n['imgTries']??0)+1; $ok=false;
    if($pexels){
      [$c,$r]=$get('https://api.pexels.com/v1/search?per_page=3&orientation=landscape&locale=en-US&query='.rawurlencode($q),['Authorization: '.$pexels]);
      $j=$c===200?json_decode($r,true):null;
      if(!empty($j['photos'][0])){ $p=$j['photos'][0]; $n['imgUrl']=$p['src']['large']??$p['src']['landscape']; $n['imgCredit']='Fotoğraf: '.($p['photographer']??'Pexels').' / Pexels'; $n['imgLink']=$p['url']??''; $n['imgLicense']='Pexels License'; $ok=true; $src['pexels']++; }
    }
    if(!$ok){
      [$c,$r]=$get('https://api.openverse.org/v1/images/?page_size=3&license_type=commercial&mature=false&q='.rawurlencode($q));
      $j=$c===200?json_decode($r,true):null;
      if(!empty($j['results'][0]['url'])){ $p=$j['results'][0]; $n['imgUrl']=$p['url']; $n['imgCredit']='Fotoğraf: '.($p['creator']??'Bilinmiyor').' · '.strtoupper($p['license']??'CC').' '.($p['license_version']??''); $n['imgLink']=$p['foreign_landing_url']??''; $n['imgLicense']=$p['license']??'cc'; $ok=true; $src['openverse']++; }
    }
    $ok?$done++:$fail++;
  } unset($n);
  return ['images'=>$done,'failed'=>$fail,'sources'=>$src,'pexels_key'=>$pexels?'var':'yok'];
}
