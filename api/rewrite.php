<?php
/* SKYTÜRK — Haiku ile özgünleştirme. fetch.php sonunda çağrılır; tek başına da çalışır. */
function skyturk_rewrite(array &$news, int $maxItems=30, int $budgetSec=70): array {
  $secrets=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];
  $key=$secrets['ANTHROPIC_KEY']??''; if(!$key) return ['skipped'=>'anahtar yok'];
  $model=$secrets['REWRITE_MODEL']??'claude-haiku-4-5-20251001';
  $cats=['son-dakika','gundem','politika','dunya','ekonomi','spor','kultur-sanat','saglik','yasam','teknoloji','egitim','genel','ankara','istanbul'];
  $system="Sen SKYTÜRK haber sitesinin dijital editörüsün. Sana bir kaynaktan gelen haber başlığı ve özeti verilecek. Görevin:\n1) Gerçekleri, isimleri, sayıları ve tarihleri asla değiştirmeden, kaynağın cümlelerini tekrar etmeden, SKYTÜRK üslubuyla ÖZGÜN bir Türkçe başlık yaz (en fazla 90 karakter, tırnak ve ünlem abartısı yok, tıklama tuzağı yok).\n2) İki cümlelik özgün bir spot yaz (en fazla 240 karakter). Özette olmayan bilgi ekleme; bilgi yetersizse genel ama doğru kal.\n3) Şu listeden en uygun kategori id'sini seç: ".implode(', ',$cats).". Son dakika yalnızca acil/gelişen olaylar için.\n4) 2-4 kısa Türkçe etiket ver (küçük harf).\n5) Haber için İngilizce, kısa, kişi adı içermeyen bir stok/illüstrasyon görsel istemi yaz (imgPrompt) ve stok fotoğraf sitesinde arama için 2-3 kelimelik somut İngilizce arama terimi ver (imgQuery; ör. 'earthquake rescue', 'stock market screen', 'football stadium'). Kişi, marka, logo isteme.\nYalnızca şu JSON'u döndür, başka hiçbir şey yazma: {\"title\":\"\",\"spot\":\"\",\"cat\":\"\",\"tags\":[],\"imgPrompt\":\"\",\"imgQuery\":\"\"}";
  $start=time(); $done=0; $fail=0; $cost=0;
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
    if($code!==200||!isset($j['content'][0]['text'])){ $fail++; $n['rwErr']=substr((string)$resp,0,160); continue; }
    $txt=trim($j['content'][0]['text']); $txt=preg_replace('/^```(json)?|```$/m','',$txt);
    $o=json_decode(trim($txt),true);
    if(!is_array($o)||empty($o['title'])){ $fail++; $n['rwErr']='json'; continue; }
    $n['srcTitle']=$n['t']; $n['srcSpot']=$n['s']??'';
    $n['t']=mb_substr(trim($o['title']),0,140); $n['s']=mb_substr(trim($o['spot']??''),0,300);
    if(!empty($o['cat'])&&in_array($o['cat'],$cats)&&$n['cat']!=='son-dakika') $n['cat']=$o['cat'];
    if(!empty($o['tags'])&&is_array($o['tags'])) $n['tags']=array_slice(array_map(fn($t)=>mb_strtolower(trim((string)$t)),$o['tags']),0,4);
    if(!empty($o['imgPrompt'])) $n['imgPrompt']=trim($o['imgPrompt']);
    if(!empty($o['imgQuery'])) $n['imgQuery']=trim($o['imgQuery']);
    $n['rw']=true; unset($n['rwErr']); $done++;
    $u=$j['usage']??[]; $cost+=(($u['input_tokens']??0)+($u['cache_creation_input_tokens']??0))*1e-6+($u['cache_read_input_tokens']??0)*1e-7+($u['output_tokens']??0)*5e-6;
  } unset($n);
  return ['rewritten'=>$done,'failed'=>$fail,'model'=>$model,'est_cost_usd'=>round($cost,4)];
}
