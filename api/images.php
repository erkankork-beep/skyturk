<?php date_default_timezone_set("Europe/Istanbul");
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
    /* 1) Kişi haberi: Wikimedia Commons'ta CC lisanslı fotoğraf */
    $subject=!empty($n['person'])?$n['person']:(!empty($n['entity'])?$n['entity']:'');
    if($subject){
      $person=$subject; $api='https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrnamespace=6&gsrlimit=8&gsrsearch='.rawurlencode($person.' filetype:bitmap').'&prop=imageinfo&iiprop=url|extmetadata|mime|size&iiurlwidth=1200';
      [$c,$r]=$get($api); $j=$c===200?json_decode($r,true):null; $best=null;
      foreach(($j['query']['pages']??[]) as $pg){ $ii=$pg['imageinfo'][0]??null; if(!$ii) continue; $m=$ii['extmetadata']??[]; $lic=strtolower($m['LicenseShortName']['value']??''); $mime=$ii['mime']??'';
        if(!preg_match('/^(cc0|cc by|cc by-sa|cc-by|cc-by-sa|public domain|pd)/i',$lic)&&stripos($lic,'cc by')===false&&stripos($lic,'public domain')===false&&stripos($lic,'cc0')===false) continue;
        if(!in_array($mime,['image/jpeg','image/png'])||($ii['width']??0)<500) continue;
        $title=mb_strtolower($pg['title']??''); $pl=mb_strtolower($person); $score=0; foreach(preg_split('/\s+/u',$pl) as $w) if($w&&mb_strpos($title,$w)!==false) $score++;
        if(preg_match('/logo|map|flag|signature|imza|poster|cover|album|screenshot|coat|arma|book|diagram|chart|svg|icon/i',$title)) $score-=3;
        if($best===null||$score>$best[0]) $best=[$score,$ii,$m,$pg['title']??''];
      }
      if($best&&$best[0]>0){ $ii=$best[1]; $m=$best[2]; $author=trim(strip_tags($m['Artist']['value']??'Wikimedia Commons')); $author=mb_substr(preg_replace('/\s+/',' ',$author),0,60); $lic=$m['LicenseShortName']['value']??'CC';
        $n['imgUrl']=$ii['thumburl']??$ii['url']; $n['imgCredit']='Fotoğraf: '.$author.' / Wikimedia Commons ('.$lic.')'; $n['imgLink']=$ii['descriptionurl']??''; $n['imgSource']='commons'; $ok=true; $src['commons']=($src['commons']??0)+1; }
    }
    if(!$ok&&$pexels){
      [$c,$r]=$get('https://api.pexels.com/v1/search?per_page=6&orientation=landscape&locale=en-US&query='.rawurlencode($q),['Authorization: '.$pexels]);
      $j=$c===200?json_decode($r,true):null;
      if($j&&!empty($j['photos'])){ $qw=array_filter(preg_split('/\W+/',strtolower($q)),fn($w)=>strlen($w)>2); $foreign=($n['cat']??'')!=='dunya'; $best=null; foreach($j['photos'] as $ph){ $alt=strtolower($ph['alt']??''); if($foreign&&preg_match('/american|usa|u\.s\.|united states|us flag|capitol|white house|washington|new york|dollar|statue of liberty|congress|nyc|london|big ben|eiffel|paris/i',$alt)) continue; $sc=0; foreach($qw as $w) if(strpos($alt,$w)!==false) $sc++; if($best===null||$sc>$best[0]) $best=[$sc,$ph]; } if($best){ $j['photos']=[$best[1]]; } else { $j['photos']=[]; } }
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
