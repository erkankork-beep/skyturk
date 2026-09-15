<?php date_default_timezone_set("Europe/Istanbul");
/* SKYTÜRK — Sosyal medya: haber kartı üretimi (GD) + Instagram API (Instagram Login) gönderimi */
function sky_social_conf(){ $d=json_decode(@file_get_contents(__DIR__.'/data.json'),true); $s=$d['settings']['social']??[]; $sec=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[]; $s['igToken']=$sec['IG_TOKEN']??''; $s['igAppSecret']=$sec['IG_APP_SECRET']??''; return $s; }
function trUpS($s){ return mb_strtoupper(str_replace(['i','ı'],['İ','I'],$s)); }

/* 1080×1080 haber kartı: üstte fotoğraf, altta lacivert blok + kategori etiketi + başlık, SKYTÜRK imzası */
function sky_make_card(array $n, string $outPath): bool {
  if(!function_exists('imagecreatetruecolor')) return false;
  $S=1080; $H=1350; $im=imagecreatetruecolor($S,$H); $navy=imagecolorallocate($im,11,42,107); $navy2=imagecolorallocate($im,20,33,61); $white=imagecolorallocate($im,255,255,255); $red=imagecolorallocate($im,232,38,44); $yel=imagecolorallocate($im,255,212,0); $sky=imagecolorallocate($im,120,200,255); $grey=imagecolorallocate($im,200,210,230);
  imagefilledrectangle($im,0,0,$S,$H,$navy2);
  $F=__DIR__.'/lib/font/unifont/'; $fT=$F.'DejaVuSansCondensed-Bold.ttf'; $fB=$F.'DejaVuSans.ttf'; $fBB=$F.'DejaVuSans-Bold.ttf';
  /* fotoğraf (üst %58) */
  $ph=(int)($H*0.56); $img=null;
  if(!empty($n['imgUrl'])){ $ch=curl_init($n['imgUrl']); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_USERAGENT=>'SKYTURK/1.0 (news; contact@skyturk)']); $bin=curl_exec($ch); curl_close($ch); if($bin) $img=@imagecreatefromstring($bin); }
  if($img){ $w=imagesx($img); $h=imagesy($img); $r=max($S/$w,$ph/$h); $cw=(int)($S/$r); $chh=(int)($ph/$r); $sy=(int)(($h-$chh)*0.12); /* dikey kırpma üste yakın: yüzler kesilmesin */ imagecopyresampled($im,$img,0,0,(int)(($w-$cw)/2),$sy,$S,$ph,$cw,$chh); imagedestroy($img); }
  else { for($y=0;$y<$ph;$y+=2){ $t=$y/$ph; $col=imagecolorallocate($im,(int)(11+30*$t),(int)(79-30*$t),(int)(158-60*$t)); imagefilledrectangle($im,0,$y,$S,$y+1,$col); imagecolordeallocate($im,$col); } }
  /* alt blok */
  imagefilledrectangle($im,0,$ph,$S,$H,$navy2); imagefilledrectangle($im,0,$ph,$S,$ph+6,$red);
  /* kategori etiketi */
  $cats=['son-dakika'=>'SON DAKİKA','gundem'=>'GÜNDEM','politika'=>'POLİTİKA','dunya'=>'DÜNYA','ekonomi'=>'EKONOMİ','spor'=>'SPOR','magazin'=>'MAGAZİN','kultur-sanat'=>'KÜLTÜR-SANAT','saglik'=>'SAĞLIK','yasam'=>'YAŞAM','teknoloji'=>'TEKNOLOJİ','egitim'=>'EĞİTİM','genel'=>'GÜNDEM'];
  $cat=$cats[$n['cat']??'gundem']??'GÜNDEM'; $bb=imagettfbbox(22,0,$fBB,$cat); $cw2=$bb[2]-$bb[0]+40; $isSD=($n['cat']??'')==='son-dakika';
  imagefilledrectangle($im,48,$ph+34,48+$cw2,$ph+34+48,$isSD?$red:$yel); imagettftext($im,22,0,68,$ph+34+34,$isSD?$white:$navy2,$fBB,$cat);
  /* başlık: 2-4 satır, sığdır */
  $title=trim($n['t']??''); $maxW=$S-96; $size=60; do{ $lines=[]; $cur=''; foreach(preg_split('/\s+/u',$title) as $wd){ $t=$cur===''?$wd:$cur.' '.$wd; $b=imagettfbbox($size,0,$fT,$t); if(($b[2]-$b[0])<=$maxW) $cur=$t; else { if($cur!=='') $lines[]=$cur; $cur=$wd; } } if($cur!=='') $lines[]=$cur; if(count($lines)<=5) break; $size-=4; }while($size>=36);
  $lines=array_slice($lines,0,5); $lh=(int)($size*1.22); $y=$ph+34+48+28+$size;
  foreach($lines as $l){ imagettftext($im,$size,0,48,$y,$white,$fT,$l); $y+=$lh; }
  /* spot: 2 satıra kadar */
  $spot=trim($n['s']??''); if($spot&&$y<$H-64-80){ $ss=26; $sl=[]; $cur=''; foreach(preg_split('/\s+/u',$spot) as $wd){ $t=$cur===''?$wd:$cur.' '.$wd; $b=imagettfbbox($ss,0,$fB,$t); if(($b[2]-$b[0])<=$maxW) $cur=$t; else { if($cur!=='') $sl[]=$cur; $cur=$wd; if(count($sl)>=2) break; } } if($cur!==''&&count($sl)<2) $sl[]=$cur; if(count($sl)===2&&mb_strlen(implode(' ',$sl))<mb_strlen($spot)) $sl[1]=mb_substr($sl[1],0,mb_strlen($sl[1])-1).'…'; $y+=6; foreach($sl as $l){ if($y>$H-64-20) break; imagettftext($im,$ss,0,48,$y,$grey,$fB,$l); $y+=(int)($ss*1.4); } }
  /* alt imza şeridi */
  imagefilledrectangle($im,0,$H-64,$S,$H,$navy); imagettftext($im,28,0,48,$H-22,$white,$fT,'SKY'); $bw=imagettfbbox(28,0,$fT,'SKY'); imagettftext($im,28,0,48+($bw[2]-$bw[0]),$H-22,$sky,$fT,'TÜRK');
  $sig=trUpS(($n['d']??'')?mb_substr($n['d'],0,10):date('d.m.Y')).'  ·  skyturk_haber'; $sb=imagettfbbox(18,0,$fB,$sig); imagettftext($im,18,0,$S-48-($sb[2]-$sb[0]),$H-24,$grey,$fB,$sig);
  imagefilledellipse($im,48+($bw[2]-$bw[0])+imagettfbbox(28,0,$fT,'TÜRK')[2]+14,$H-30,12,12,$red);
  $dir=dirname($outPath); if(!is_dir($dir)) @mkdir($dir,0755,true); $ok=imagejpeg($im,$outPath,88); imagedestroy($im); return $ok;
}

/* Instagram Graph API (Instagram Login): tek görsel gönderi */
function sky_ig_publish(string $imageUrl, string $caption): array {
  $c=sky_social_conf(); $tok=$c['igToken']??''; $uid=$c['igUserId']??''; if(!$tok||!$uid) return ['error'=>'Instagram bağlı değil'];
  $post=function($url,$fields)use($tok){ $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>30,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>http_build_query($fields+['access_token'=>$tok])]); $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$code,json_decode((string)$r,true)]; };
  [$c1,$j1]=$post("https://graph.instagram.com/v21.0/$uid/media",['image_url'=>$imageUrl,'caption'=>$caption]); if($c1!==200||empty($j1['id'])) return ['error'=>'container: '.json_encode($j1,JSON_UNESCAPED_UNICODE)];
  /* konteyner hazır olana kadar bekle (en fazla ~20 sn) */
  for($i=0;$i<8;$i++){ $ch=curl_init("https://graph.instagram.com/v21.0/{$j1['id']}?fields=status_code&access_token=$tok"); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15]); $st=json_decode((string)curl_exec($ch),true); curl_close($ch); if(($st['status_code']??'')==='FINISHED') break; if(($st['status_code']??'')==='ERROR') return ['error'=>'container error: '.json_encode($st)]; sleep(3); }
  [$c2,$j2]=$post("https://graph.instagram.com/v21.0/$uid/media_publish",['creation_id'=>$j1['id']]); if($c2!==200||empty($j2['id'])) return ['error'=>'publish: '.json_encode($j2,JSON_UNESCAPED_UNICODE)];
  return ['ok'=>true,'id'=>$j2['id']];
}
/* Bağlantı testi: hesap adı */
function sky_ig_me(): array { $c=sky_social_conf(); $tok=$c['igToken']??''; if(!$tok) return ['error'=>'belirteç yok']; $ch=curl_init("https://graph.instagram.com/v21.0/me?fields=id,username,account_type,followers_count,media_count&access_token=$tok"); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15]); $r=json_decode((string)curl_exec($ch),true); curl_close($ch); return $r?:['error'=>'yanıt yok']; }
/* Belirteci uzat (60 gün) — 50 günden eskiyse yenile */
function sky_ig_refresh(): array { $c=sky_social_conf(); $tok=$c['igToken']??''; if(!$tok) return ['skipped'=>'yok']; $ch=curl_init("https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=$tok"); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15]); $r=json_decode((string)curl_exec($ch),true); curl_close($ch); if(!empty($r['access_token'])){ $sec=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[]; $sec['IG_TOKEN']=$r['access_token']; $sec['IG_TOKEN_AT']=time(); file_put_contents(__DIR__.'/secrets.php',"<?php return ".var_export($sec,true).";\n",LOCK_EX); return ['ok'=>true]; } return ['error'=>json_encode($r)]; }

/* Açıklama metni */
function sky_caption(array $n, string $site): string {
  $cats=['son-dakika'=>'#sondakika','gundem'=>'#gündem','politika'=>'#politika','dunya'=>'#dünya','ekonomi'=>'#ekonomi','spor'=>'#spor','magazin'=>'#magazin','kultur-sanat'=>'#kültürsanat','saglik'=>'#sağlık','yasam'=>'#yaşam','teknoloji'=>'#teknoloji','egitim'=>'#eğitim'];
  $tags=array_slice(array_filter(array_map(fn($t)=>'#'.preg_replace('/[^\p{L}\p{N}]/u','',mb_strtolower($t)),$n['tags']??[]),fn($t)=>mb_strlen($t)>2),0,5);
  return $n['t']."\n\n".mb_substr($n['s']??'',0,300)."\n\n📲 Haberin tamamı: ".$site."/#/haber/".$n['id']."\n\n".($cats[$n['cat']??'']??'#haber').' #skyturk #haber '.implode(' ',$tags);
}

/* Otomatik akış: son turda yayınlanan haberlerden en fazla $max tanesini paylaş */
function sky_social_autopost(array &$news, int $max=2): array {
  $c=sky_social_conf(); if(empty($c['igAuto'])||empty($c['igToken'])) return ['skipped'=>'otomatik paylaşım kapalı'];
  $cats=array_filter(array_map('trim',explode(',',$c['igCats']??''))); $perDay=(int)($c['igPerDay']??20); $site=rtrim($c['siteUrl']??'https://testhabersitesimiz.site','/');
  $today=date('Y-m-d'); $cnt=0; foreach($news as $x) if(!empty($x['ig']['d'])&&$x['ig']['d']===$today) $cnt++; if($cnt>=$perDay) return ['skipped'=>'günlük paylaşım sınırı'];
  $root=dirname(__DIR__); $done=[]; $err=[];
  foreach($news as &$n){ if(count($done)>=$max||$cnt>=$perDay) break; if(empty($n['rw'])||!empty($n['video'])||!empty($n['ig'])||($n['st']??'')!=='Yayında'||empty($n['imgUrl'])) continue; if($cats&&!in_array($n['cat'],$cats)) continue; if((time()-(int)($n['ts']??0))>6*3600) continue;
    $file='media/cards/'.$n['id'].'.jpg'; if(!file_exists($root.'/'.$file)&&!sky_make_card($n,$root.'/'.$file)){ $err[]=$n['id'].': kart'; $n['ig']=['err'=>'kart']; continue; }
    $r=sky_ig_publish($site.'/'.$file,sky_caption($n,$site)); if(!empty($r['ok'])){ $n['ig']=['id'=>$r['id'],'d'=>$today,'t'=>date('H:i')]; $done[]=$n['id']; $cnt++; } else { $n['ig']=['err'=>mb_substr($r['error']??'hata',0,200),'d'=>$today]; $err[]=$n['id'].': '.mb_substr($r['error']??'',0,80); } } unset($n);
  return ['posted'=>$done,'errors'=>$err];
}
