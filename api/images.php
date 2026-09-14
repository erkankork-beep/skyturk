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
        if(preg_match('/logo|map|flag|signature|imza|poster|cover|album|screenshot|coat|arma|book|diagram|chart|svg|icon/i',$title)) $score-=3; if(preg_match('/bet|casino|gambl|poker|slot|jackpot|lottery|bahis|iddaa|kumar|alcohol|beer|wine|whisk|cigarette|tobacco/i',$title)) continue;
        if($best===null||$score>$best[0]) $best=[$score,$ii,$m,$pg['title']??''];
      }
      if($best&&$best[0]>0){ $ii=$best[1]; $m=$best[2]; $author=trim(strip_tags($m['Artist']['value']??'Wikimedia Commons')); $author=mb_substr(preg_replace('/\s+/',' ',$author),0,60); $lic=$m['LicenseShortName']['value']??'CC';
        $n['imgUrl']=$ii['thumburl']??$ii['url']; $n['imgCredit']='Fotoğraf: '.$author.' / Wikimedia Commons ('.$lic.')'; $n['imgLink']=$ii['descriptionurl']??''; $n['imgSource']='commons'; $ok=true; $src['commons']=($src['commons']??0)+1; }
    }
    if(!$ok&&$pexels){
      [$c,$r]=$get('https://api.pexels.com/v1/search?per_page=6&orientation=landscape&locale=en-US&query='.rawurlencode($q),['Authorization: '.$pexels]);
      $j=$c===200?json_decode($r,true):null;
      if($j&&!empty($j['photos'])){ $foreign=($n['cat']??'')!=='dunya'; $cands=[]; foreach($j['photos'] as $ph){ $alt=strtolower(($ph['alt']??'').' '.($ph['url']??'')); if(preg_match('/\bbet|betting|bookmaker|casino|gambl|poker|roulette|slot machine|jackpot|lottery|bahis|iddaa|kumar|blackjack|dice game|alcohol|whisk|vodka|beer|wine glass|cigarette|tobacco|vape|hookah|nargile/i',$alt)) continue; if($foreign&&preg_match('/american|usa|u\.s\.|united states|us flag|capitol|white house|washington|new york|dollar|statue of liberty|congress|nyc|london|big ben|eiffel|paris/i',$alt)) continue; $cands[]=$ph; }
        $pick=null; if($cands){ $pick=sky_pick_photo($n,$cands,$secrets); } $j['photos']=$pick?[$pick]:[]; if(!$pick) $n['imgNoMatch']=true; }
      if(!empty($j['photos'][0])){ $p=$j['photos'][0]; $n['imgUrl']=$p['src']['large']??$p['src']['landscape']; $n['imgCredit']='Fotoğraf: '.($p['photographer']??'Pexels').' / Pexels'; $n['imgLink']=$p['url']??''; $n['imgLicense']='Pexels License'; $ok=true; $src['pexels']++; }
    }
    if(!$ok&&empty($n['imgNoMatch'])){
      [$c,$r]=$get('https://api.openverse.org/v1/images/?page_size=3&license_type=commercial&mature=false&q='.rawurlencode($q));
      $j=$c===200?json_decode($r,true):null;
      if(!empty($j['results'][0]['url'])){ $p=$j['results'][0]; $n['imgUrl']=$p['url']; $n['imgCredit']='Fotoğraf: '.($p['creator']??'Bilinmiyor').' · '.strtoupper($p['license']??'CC').' '.($p['license_version']??''); $n['imgLink']=$p['foreign_landing_url']??''; $n['imgLicense']=$p['license']??'cc'; $ok=true; $src['openverse']++; }
    }
    /* 3) Kategori havuzu: Türkiye bağlamlı nötr fotoğraf (Commons) */
    if(!$ok){ $pool=sky_cat_pool($n['cat']??'gundem',$get); if($pool){ $p=$pool[array_rand($pool)]; $n['imgUrl']=$p['url']; $n['imgCredit']='Fotoğraf: '.$p['author'].' / Wikimedia Commons ('.$p['lic'].')'; $n['imgLink']=$p['page']; $n['imgSource']='pool'; $ok=true; $src['pool']=($src['pool']??0)+1; } }
    unset($n['imgNoMatch']);
    $ok?$done++:$fail++;
  } unset($n);
  return ['images'=>$done,'failed'=>$fail,'sources'=>$src,'pexels_key'=>$pexels?'var':'yok'];
}

/* Haiku ile aday fotoğraf seçimi: açıklamalar habere uyuyorsa indeksi, uymuyorsa -1 döner */
function sky_pick_photo(array $n, array $cands, array $secrets){
  $key=$secrets['ANTHROPIC_KEY']??''; if(!$key||count($cands)===1) return $cands[0]??null;
  $list=[]; foreach($cands as $k=>$ph) $list[]=$k.': '.mb_substr($ph['alt']??'(açıklama yok)',0,140);
  $prompt="Haber başlığı: ".$n['t']."\nÖzet: ".mb_substr($n['s']??'',0,200)."\n\nAşağıdaki stok fotoğraf açıklamalarından bu habere TEMSİLİ görsel olarak en uygun olanın numarasını ver. Hiçbiri uygun değilse ya da yanıltıcıysa (başka ülke simgesi, alakasız nesne, yanlış bağlam) -1 yaz. KESİN YASAK: bahis, kumar, casino, poker, slot, piyango, alkol, sigara, silah, uyuşturucu içeren ya da ima eden fotoğrafları asla seçme (Türkiye'de yayın yasağı); böyle bir aday varsa onu seçme, gerekirse -1 yaz. Sadece sayı yaz.\n\n".implode("\n",$list);
  $ch=curl_init('https://api.anthropic.com/v1/messages'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode(['model'=>$secrets['REWRITE_MODEL']??'claude-haiku-4-5-20251001','max_tokens'=>5,'messages'=>[['role'=>'user','content'=>$prompt]]],JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01']]);
  $resp=curl_exec($ch); curl_close($ch); $j=json_decode((string)$resp,true); $t=trim($j['content'][0]['text']??'');
  if(!preg_match('/-?\d+/',$t,$m)) return $cands[0]; $idx=(int)$m[0]; if($idx<0) return null; return $cands[$idx]??$cands[0];
}
/* Kategori başına Commons havuzu (24 saat önbellek) */
function sky_cat_pool(string $cat, callable $get): array {
  $terms=['politika'=>['Grand National Assembly of Turkey building','Ankara Turkey government building'],'gundem'=>['Istanbul cityscape Turkey','Ankara city Turkey'],'son-dakika'=>['Istanbul street Turkey','Turkish police car'],'ekonomi'=>['Borsa Istanbul','Turkish lira banknotes'],'spor'=>['Turkish football stadium','Süper Lig match'],'saglik'=>['Turkey hospital building','hospital corridor'],'egitim'=>['Turkish school classroom','Istanbul University building'],'teknoloji'=>['data center servers','technology circuit board'],'kultur-sanat'=>['Istanbul museum interior','Turkish theatre stage'],'yasam'=>['Istanbul Bosphorus daily life','Turkish tea bazaar'],'magazin'=>['red carpet event','Istanbul concert stage'],'dunya'=>['United Nations headquarters','world map globe'],'genel'=>['Turkey landscape','Istanbul skyline']];
  $q=$terms[$cat]??$terms['genel']; $cf=__DIR__.'/pool-'.$cat.'.json'; if(file_exists($cf)&&time()-filemtime($cf)<86400){ $p=json_decode(file_get_contents($cf),true); if($p) return $p; }
  $pool=[]; foreach($q as $term){ [$c,$r]=$get('https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrnamespace=6&gsrlimit=10&gsrsearch='.rawurlencode($term.' filetype:bitmap').'&prop=imageinfo&iiprop=url|extmetadata|mime|size&iiurlwidth=1200'); $j=$c===200?json_decode($r,true):null;
    foreach(($j['query']['pages']??[]) as $pg){ $ii=$pg['imageinfo'][0]??null; if(!$ii) continue; $m=$ii['extmetadata']??[]; $lic=$m['LicenseShortName']['value']??''; if(!preg_match('/cc0|cc by|public domain/i',$lic)) continue; if(!in_array($ii['mime']??'',['image/jpeg']) || ($ii['width']??0)<900 || ($ii['width']??0)<($ii['height']??1)) continue; if(preg_match('/logo|map|flag|diagram|svg|chart|coat|arma|bet|casino|gambl|poker|slot|lottery|alcohol|beer|wine|cigarette/i',$pg['title']??'')) continue;
      $pool[]=['url'=>$ii['thumburl']??$ii['url'],'author'=>mb_substr(trim(preg_replace('/\s+/',' ',strip_tags($m['Artist']['value']??'Wikimedia Commons'))),0,60),'lic'=>$lic,'page'=>$ii['descriptionurl']??'']; } }
  $pool=array_slice($pool,0,16); if($pool) file_put_contents($cf,json_encode($pool,JSON_UNESCAPED_UNICODE)); return $pool;
}
