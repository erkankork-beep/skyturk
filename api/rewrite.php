<?php
/* SKYTÜRK — Haiku ile özgünleştirme. fetch.php sonunda çağrılır; tek başına da çalışır. */
function skyturk_rewrite(array &$news, int $maxItems=30, int $budgetSec=70): array {
  $secrets=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];
  $key=$secrets['ANTHROPIC_KEY']??''; if(!$key) return ['skipped'=>'anahtar yok'];
  $model=$secrets['REWRITE_MODEL']??'claude-haiku-4-5-20251001';
  $cats=['son-dakika','gundem','politika','dunya','ekonomi','spor','kultur-sanat','saglik','yasam','teknoloji','magazin','egitim','genel','ankara','istanbul'];
  $system="Sen SKYTÜRK haber sitesinin dijital editörüsün. Sana bir kaynaktan gelen haber başlığı ve özeti verilecek. Görevin:\n1) Gerçekleri, isimleri, sayıları ve tarihleri asla değiştirmeden, kaynağın cümlelerini tekrar etmeden, SKYTÜRK üslubuyla ÖZGÜN bir Türkçe başlık yaz (en fazla 90 karakter, tırnak ve ünlem abartısı yok, tıklama tuzağı yok).\n2) İki cümlelik özgün bir spot yaz (en fazla 240 karakter). Özette olmayan bilgi ekleme; bilgi yetersizse genel ama doğru kal.\n3) Şu listeden en uygun kategori id'sini seç: ".implode(', ',$cats).". Son dakika yalnızca acil/gelişen olaylar için.\n4) 2-4 kısa Türkçe etiket ver (küçük harf).\n5) Haber için İngilizce, kısa, kişi adı içermeyen bir stok/illüstrasyon görsel istemi yaz (imgPrompt) ve stok fotoğraf sitesinde arama için 2-3 kelimelik somut İngilizce arama terimi ver (imgQuery; ör. 'earthquake rescue', 'stock market screen', 'football stadium'). Kişi, marka, logo isteme.\n6) Haber tanınmış bir kişi (ünlü, sporcu, siyasetçi, sanatçı) hakkındaysa o kişinin tam adını 'person' alanına yaz (ör. 'Tarkan', 'Arda Güler', 'Hande Erçel'); haber kurum/olay hakkındaysa boş bırak. Birden fazla kişi varsa haberin ana öznesini yaz.\nYalnızca şu JSON'u döndür, başka hiçbir şey yazma: {\"title\":\"\",\"spot\":\"\",\"cat\":\"\",\"tags\":[],\"imgPrompt\":\"\",\"imgQuery\":\"\",\"person\":\"\"}";
  $start=time(); $done=0; $fail=0; $cost=0; $lastErr=null;
  foreach($news as &$n){
    if(empty($n['auto'])||!empty($n['rw'])) continue;
    if(($n['rwTries']??0)>=2) continue;
    if($done>=$maxItems||time()-$start>$budgetSec) break;
    $user="KAYNAK: ".($n['srcName']??$n['by'])."\nBAŞLIK: ".$n['t']."\nÖZET: ".($n['s']??'')."\nMEVCUT KATEGORİ: ".$n['cat'];
    $body=['model'=>$model,'max_tokens'=>400,
      'system'=>[['type'=>'text','text'=>$system,'cache_control'=>['type'=>'ephemeral']]],
      'messages'=>[['role'=>'user','content'=>$user]]];
    $ch=curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>25,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE),
      CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01']]);
    $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $n['rwTries']=($n['rwTries']??0)+1;
    $j=json_decode((string)$resp,true);
    if($code!==200||!isset($j['content'][0]['text'])){ $fail++; $n['rwErr']=substr((string)$resp,0,160); $lastErr=(string)$resp; if(stripos($lastErr,'credit')!==false) break; continue; }
    $txt=trim($j['content'][0]['text']); $txt=preg_replace('/^```(json)?|```$/m','',$txt);
    $o=json_decode(trim($txt),true);
    if(!is_array($o)||empty($o['title'])){ $fail++; $n['rwErr']='json'; continue; }
    $n['srcTitle']=$n['t']; $n['srcSpot']=$n['s']??'';
    $n['t']=mb_substr(trim($o['title']),0,140); $n['s']=mb_substr(trim($o['spot']??''),0,300);
    if(!empty($o['cat'])&&in_array($o['cat'],$cats)&&$n['cat']!=='magazin'&&($n['cat']!=='son-dakika'||(time()-($n['ts']??time()))>6*3600)) $n['cat']=$o['cat'];
    if(!empty($o['tags'])&&is_array($o['tags'])) $n['tags']=array_slice(array_map(fn($t)=>mb_strtolower(trim((string)$t)),$o['tags']),0,4);
    if(!empty($o['imgPrompt'])) $n['imgPrompt']=trim($o['imgPrompt']);
    if(!empty($o['imgQuery'])) $n['imgQuery']=trim($o['imgQuery']);
    if(!empty($o['person'])&&mb_strlen($o['person'])<60) $n['person']=trim($o['person']);
    $n['rw']=true; unset($n['rwErr']); $done++;
    $u=$j['usage']??[]; $cost+=(($u['input_tokens']??0)+($u['cache_creation_input_tokens']??0))*1e-6+($u['cache_read_input_tokens']??0)*1e-7+($u['output_tokens']??0)*5e-6;
  } unset($n);
  return ['rewritten'=>$done,'failed'=>$fail,'model'=>$model,'est_cost_usd'=>round($cost,4),'api_error'=>$lastErr?substr($lastErr,0,120):null];
}

/* Günlük astroloji — günde bir kez Haiku ile 12 burç */
function skyturk_astro(array &$data): array {
  $today=date('Y-m-d'); if(($data['astro']['date']??'')===$today) return ['skipped'=>'bugün üretildi'];
  $secrets=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];
  $key=$secrets['ANTHROPIC_KEY']??''; if(!$key) return ['skipped'=>'anahtar yok'];
  $signs=['koc'=>'Koç','boga'=>'Boğa','ikizler'=>'İkizler','yengec'=>'Yengeç','aslan'=>'Aslan','basak'=>'Başak','terazi'=>'Terazi','akrep'=>'Akrep','yay'=>'Yay','oglak'=>'Oğlak','kova'=>'Kova','balik'=>'Balık'];
  $prompt="Bugün ".date('d.m.Y').". SKYTÜRK haber sitesi için 12 burcun günlük yorumunu yaz. Her burç için: 'genel' (2-3 kısa cümle, sıcak ve olumlu ama gerçekçi, tıbbi/finansal kesin iddia yok), 'ask' (1 cümle), 'kariyer' (1 cümle), 'saglik' (1 cümle), 'sansli_sayi' (1-99), 'renk' (bir renk). Yalnızca JSON döndür: {\"koc\":{\"genel\":\"\",\"ask\":\"\",\"kariyer\":\"\",\"saglik\":\"\",\"sansli_sayi\":0,\"renk\":\"\"}, ...} Anahtarlar: ".implode(', ',array_keys($signs));
  $body=['model'=>$secrets['REWRITE_MODEL']??'claude-haiku-4-5-20251001','max_tokens'=>6000,'messages'=>[['role'=>'user','content'=>$prompt]]];
  $ch=curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01']]);
  $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $j=json_decode((string)$resp,true); if($code!==200||!isset($j['content'][0]['text'])) return ['error'=>substr((string)$resp,0,160)];
  $txt=preg_replace('/^```(json)?|```$/m','',trim($j['content'][0]['text'])); $o=json_decode(trim($txt),true);
  if(!is_array($o)||count($o)<12) return ['error'=>'json'];
  $data['astro']=['date'=>$today,'items'=>$o]; return ['ok'=>true,'date'=>$today];
}

/* Mevcut haberler için kişi çıkarımı (ör. magazin) → Commons fotoğrafı alınabilsin */
function skyturk_persons(array &$news, string $cat='magazin', int $max=30): array {
  $secrets=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[]; $key=$secrets['ANTHROPIC_KEY']??''; if(!$key) return ['skipped'=>'anahtar yok'];
  $model=$secrets['REWRITE_MODEL']??'claude-haiku-4-5-20251001'; $items=[];
  foreach($news as $i=>$n){ if(($n['cat']??'')!==$cat||!empty($n['person'])||!empty($n['personTried'])||!empty($n['video'])) continue; $items[$i]=mb_substr($n['t'],0,140); if(count($items)>=$max) break; }
  if(!$items) return ['done'=>0];
  $prompt="Aşağıdaki haber başlıklarının her biri için haberin ana öznesi olan TANINMIŞ kişinin tam adını yaz (ünlü, sporcu, siyasetçi, sanatçı). Kişi yoksa ya da tanınmış değilse boş string. Yalnızca JSON döndür: {\"<id>\":\"Ad Soyad\",...}\n\n".implode("\n",array_map(fn($k,$v)=>"$k: $v",array_keys($items),$items));
  $ch=curl_init('https://api.anthropic.com/v1/messages'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>45,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'max_tokens'=>1200,'messages'=>[['role'=>'user','content'=>$prompt]]],JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01']]);
  $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $j=json_decode((string)$resp,true);
  if($code!==200||!isset($j['content'][0]['text'])) return ['error'=>substr((string)$resp,0,160)];
  $txt=preg_replace('/^```(json)?|```$/m','',trim($j['content'][0]['text'])); $o=json_decode(trim($txt),true); if(!is_array($o)) return ['error'=>'json'];
  $done=0; foreach($items as $i=>$t){ $news[$i]['personTried']=true; $p=trim((string)($o[(string)$i]??'')); if($p&&mb_strlen($p)<60){ $news[$i]['person']=$p; if(($news[$i]['imgSource']??'')!=='commons'){ unset($news[$i]['imgUrl'],$news[$i]['imgCredit'],$news[$i]['imgLink']); $news[$i]['imgTries']=0; } $done++; } }
  return ['done'=>$done,'scanned'=>count($items)];
}
