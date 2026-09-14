<?php
/* SKYTÜRK — API kredi takibi ve uyarılar */
function sky_credit_file(){return __DIR__.'/credit.json';}
function sky_credit_load(){$f=sky_credit_file();$c=file_exists($f)?(json_decode(file_get_contents($f),true)?:[]):[];return array_merge(['balance'=>0,'spent'=>0,'set_at'=>null,'alerts'=>[],'hourly'=>[],'email'=>'','exhausted'=>false],$c);}
function sky_credit_save($c){file_put_contents(sky_credit_file(),json_encode($c,JSON_UNESCAPED_UNICODE),LOCK_EX);}
function sky_credit_set($usd,$email=null){$c=sky_credit_load();$c['balance']=(float)$usd;$c['spent']=0;$c['set_at']=date('c');$c['alerts']=[];$c['exhausted']=false;if($email!==null)$c['email']=trim($email);sky_credit_save($c);return $c;}
function sky_credit_status(){$c=sky_credit_load();$rem=max(0,$c['balance']-$c['spent']);$now=time();$h=array_filter($c['hourly'],fn($v,$k)=>$k>=$now-86400,ARRAY_FILTER_USE_BOTH);$spent24=array_sum($h);$hours=count($h)?max(1,($now-min(array_keys($h)))/3600):24;$rate=$spent24/$hours;$left=$rate>0?$rem/$rate:null;
  return ['balance'=>round($c['balance'],2),'spent'=>round($c['spent'],4),'remaining'=>round($rem,2),'rate_per_day'=>round($rate*24,3),'hours_left'=>$left===null?null:round($left,1),'set_at'=>$c['set_at'],'email'=>$c['email'],'exhausted'=>$c['exhausted'],'alerts'=>$c['alerts']];}
function sky_credit_spend($usd,$apiError=null){
  $c=sky_credit_load(); $c['spent']+=$usd; $hk=(string)(floor(time()/3600)*3600); $c['hourly'][$hk]=($c['hourly'][$hk]??0)+$usd; foreach(array_keys($c['hourly']) as $k) if((int)$k<time()-7*86400) unset($c['hourly'][$k]);
  if($apiError&&stripos($apiError,'credit')!==false){ $c['exhausted']=true; if(!in_array('exhausted',$c['alerts'])){ $c['alerts'][]='exhausted'; sky_credit_mail($c,'SKYTÜRK — API kredisi BİTTİ','Anthropic API "kredi yetersiz" hatası döndü. Haber özgünleştirme ve astroloji durdu. Console > Add funds ile kredi yükleyip CMS > Ayarlar > Servisler & API alanına yeni bakiyeyi girin.'); } }
  sky_credit_save($c); $st=sky_credit_status();
  if($st['hours_left']!==null&&$c['balance']>0){ foreach([48,24,20,12,10,6,5,4,3,2,1] as $t){ if($st['hours_left']<=$t&&!in_array($t,$c['alerts'])){ $c['alerts'][]=$t; sky_credit_save($c);
    sky_credit_mail($c,"SKYTÜRK — API kredisi ~{$t} saat içinde bitiyor","Kalan tahmini kredi: \${$st['remaining']} · günlük harcama hızı: \${$st['rate_per_day']} · tahmini kalan süre: {$st['hours_left']} saat.\nKredi yüklemek: platform.claude.com > Credits > Add funds. Ardından CMS > Ayarlar > Servisler & API > 'Yüklenen kredi' alanına yeni toplamı girin; uyarılar sıfırlanır."); break; } } }
  return $st;
}
function sky_credit_mail($c,$subj,$body){ $to=$c['email']??''; if(!$to) return false; $host=$_SERVER['HTTP_HOST']??'testhabersitesimiz.site'; return @mail($to,'=?UTF-8?B?'.base64_encode($subj).'?=',$body."\n\n— SKYTÜRK CMS",implode("\r\n",['From: SKYTÜRK CMS <cms@'.$host.'>','Content-Type: text/plain; charset=UTF-8'])); }
