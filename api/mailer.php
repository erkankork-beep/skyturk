<?php date_default_timezone_set("Europe/Istanbul");
/* SKYTÜRK — e-posta gönderimi: CMS'te SMTP tanımlıysa Gmail/SMTP (STARTTLS), yoksa PHP mail() */
function sky_smtp_conf(){ $d=json_decode(@file_get_contents(__DIR__.'/data.json'),true); $s=$d['settings']['smtp']??[]; $sec=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[]; $s['pass']=$sec['SMTP_PASS']??''; return $s; }
function sky_mail(string $to, string $subject, string $body, ?string $replyTo=null): bool {
  $c=sky_smtp_conf(); $sec=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];
  /* 1) Brevo (HTTPS API) — paylaşımlı hostingde SMTP portları kapalı olduğu için tercih edilen yol */
  if(!empty($sec['BREVO_KEY'])){ $from=$c['addr']??'skyturk0607@gmail.com'; $payload=['sender'=>['name'=>$c['from']??'SKYTÜRK','email'=>$from],'to'=>[['email'=>$to]],'subject'=>$subject,'textContent'=>$body]; if($replyTo) $payload['replyTo']=['email'=>$replyTo];
    $ch=curl_init('https://api.brevo.com/v3/smtp/email'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['accept: application/json','content-type: application/json','api-key: '.$sec['BREVO_KEY']]]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); @file_put_contents(__DIR__.'/mail.log',date('c').' BREVO '.$code.' → '.$to.': '.($code>=200&&$code<300?'OK':substr((string)$r,0,160))."\n",FILE_APPEND); if($code>=200&&$code<300) return true; } $from=$c['addr']??''; $fromName=$c['from']??'SKYTÜRK'; $host=$c['host']??''; $port=(int)($c['port']??587); $pass=$c['pass']??'';
  if($host&&$from&&$pass){ try{ return sky_smtp_send($host,$port,$from,$pass,$fromName,$to,$subject,$body,$replyTo); }catch(Throwable $e){ @file_put_contents(__DIR__.'/mail.log',date('c').' SMTP HATA: '.$e->getMessage()."\n",FILE_APPEND); } }
  $hdr=['From: '.$fromName.' <'.($from?:'cms@'.($_SERVER['HTTP_HOST']??'testhabersitesimiz.site')).'>','Content-Type: text/plain; charset=UTF-8']; if($replyTo) $hdr[]='Reply-To: '.$replyTo;
  return @mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,implode("\r\n",$hdr));
}
function sky_smtp_send($host,$port,$user,$pass,$fromName,$to,$subject,$body,$replyTo=null){
  $fp=stream_socket_client(($port===465?'ssl://':'').$host.':'.$port,$errno,$errstr,15); if(!$fp) throw new Exception("bağlantı: $errstr");
  $read=function()use($fp){ $out=''; while(($l=fgets($fp,515))!==false){ $out.=$l; if(isset($l[3])&&$l[3]===' ') break; } return $out; };
  $cmd=function($c,$ok)use($fp,$read){ fwrite($fp,$c."\r\n"); $r=$read(); if(strpos($r,(string)$ok)!==0) throw new Exception(trim($c)." → ".trim($r)); return $r; };
  $read(); $cmd('EHLO skyturk',250);
  if($port!==465){ $cmd('STARTTLS',220); if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('TLS başlatılamadı'); $cmd('EHLO skyturk',250); }
  $cmd('AUTH LOGIN',334); $cmd(base64_encode($user),334); $cmd(base64_encode($pass),235);
  $cmd('MAIL FROM:<'.$user.'>',250); $cmd('RCPT TO:<'.$to.'>',250); $cmd('DATA',354);
  $hdr="From: =?UTF-8?B?".base64_encode($fromName)."?= <$user>\r\nTo: <$to>\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nDate: ".date('r')."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n".($replyTo?"Reply-To: <$replyTo>\r\n":'');
  fwrite($fp,$hdr."\r\n".chunk_split(base64_encode($body))."\r\n.\r\n"); $r=$read(); if(strpos($r,'250')!==0) throw new Exception('gönderim: '.trim($r));
  fwrite($fp,"QUIT\r\n"); fclose($fp); @file_put_contents(__DIR__.'/mail.log',date('c')." OK → $to: $subject\n",FILE_APPEND); return true;
}
