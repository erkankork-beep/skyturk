<?php
/* SKYTÜRK MCP sunucusu (Streamable HTTP, JSON-RPC) — Claude bağlayıcısı
   Araçlar: get_stats, list_news, get_news, create_news, update_news, delete_news,
            set_status, fetch_rss, list_polls, toggle_poll, deploy_from_github, get_log */
require_once __DIR__.'/config.php';
date_default_timezone_set('Europe/Istanbul');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if($_SERVER['REQUEST_METHOD']==='GET'){http_response_code(405);echo '{"error":"POST JSON-RPC only"}';exit;}
if($_SERVER['REQUEST_METHOD']==='DELETE'){http_response_code(204);exit;}
$FILE=__DIR__.'/data.json'; $LOG=__DIR__.'/mcp.log';
$CATS=['son-dakika','gundem','politika','dunya','ekonomi','spor','kultur-sanat','saglik','yasam','teknoloji','egitim','genel','ankara','istanbul','resmi-ilan'];
$STS=['Yayında','Taslak','Planlandı','Arşiv'];
function load(){global $FILE;$d=file_exists($FILE)?json_decode(file_get_contents($FILE),true):null;if(!is_array($d))$d=['news'=>[],'polls'=>[]];if(!isset($d['news']))$d['news']=[];if(!isset($d['polls'])||!is_array($d['polls']))$d['polls']=[];return $d;}
function save($d){global $FILE;if(file_exists($FILE))@copy($FILE,__DIR__.'/data.bak.json');$d['updated']=date('c');file_put_contents($FILE,json_encode($d,JSON_UNESCAPED_UNICODE),LOCK_EX);}
function tsOf($n){if(isset($n['ts']))return (int)$n['ts'];if(preg_match('/(\d\d)\.(\d\d)\.(\d{4}) (\d\d):(\d\d)/',$n['d']??'',$m))return mktime($m[4],$m[5],0,$m[2],$m[1],$m[3]);return 0;}
function slim($n){return ['id'=>$n['id'],'cat'=>$n['cat'],'title'=>$n['t'],'spot'=>$n['s']??'','date'=>$n['d']??'','by'=>$n['by']??'','status'=>$n['st']??'','views'=>$n['v']??0,'tags'=>$n['tags']??[],'ozel'=>!empty($n['ozel']),'auto'=>!empty($n['auto']),'src'=>$n['src']??null];}
function logm($m){global $LOG;file_put_contents($LOG,date('d.m.Y H:i').' '.$m."\n".substr((string)@file_get_contents($LOG),0,20000));}
function res($id,$r){echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>$r],JSON_UNESCAPED_UNICODE);exit;}
function err($id,$c,$m){echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$c,'message'=>$m]],JSON_UNESCAPED_UNICODE);exit;}
function text($s,$isErr=false){return ['content'=>[['type'=>'text','text'=>is_string($s)?$s:json_encode($s,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)]],'isError'=>$isErr];}

$TOOLS=[
 ['name'=>'get_stats','description'=>'Site istatistikleri: haber sayıları (yayında/taslak/RSS), açık anketler, son RSS çekimi, son haber.','inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
 ['name'=>'list_news','description'=>'Haberleri listele. Filtre: kategori, durum, arama metni, sadece elle girilenler. En yeni önce.','inputSchema'=>['type'=>'object','properties'=>['limit'=>['type'=>'integer','default'=>30],'cat'=>['type'=>'string','enum'=>$CATS],'status'=>['type'=>'string','enum'=>$STS],'query'=>['type'=>'string'],'manual_only'=>['type'=>'boolean'],'ozel_only'=>['type'=>'boolean']]]],
 ['name'=>'get_news','description'=>'Bir haberin tüm alanları (gövde paragrafları dahil).','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'integer']],'required'=>['id']]],
 ['name'=>'create_news','description'=>'Yeni haber oluştur. body: paragraflar boş satırla ayrılmış düz metin. status varsayılan Yayında.','inputSchema'=>['type'=>'object','properties'=>['title'=>['type'=>'string'],'spot'=>['type'=>'string'],'body'=>['type'=>'string'],'cat'=>['type'=>'string','enum'=>$CATS],'status'=>['type'=>'string','enum'=>$STS],'tags'=>['type'=>'array','items'=>['type'=>'string']],'by'=>['type'=>'string'],'ozel'=>['type'=>'boolean'],'date'=>['type'=>'string','description'=>'dd.mm.yyyy HH:MM, boşsa şimdi']],'required'=>['title','cat']]],
 ['name'=>'update_news','description'=>'Haberi güncelle; yalnızca verilen alanlar değişir. RSS haberi düzenlenirse kalıcı (elle) haber olur.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'integer'],'title'=>['type'=>'string'],'spot'=>['type'=>'string'],'body'=>['type'=>'string'],'cat'=>['type'=>'string','enum'=>$CATS],'status'=>['type'=>'string','enum'=>$STS],'tags'=>['type'=>'array','items'=>['type'=>'string']],'by'=>['type'=>'string'],'ozel'=>['type'=>'boolean'],'date'=>['type'=>'string']],'required'=>['id']]],
 ['name'=>'delete_news','description'=>'Haber(ler)i sil. ids listesi ya da filtre: manual_only=true ile tüm elle girilen örnek haberleri temizlemek için kullanılabilir (dikkat!).','inputSchema'=>['type'=>'object','properties'=>['ids'=>['type'=>'array','items'=>['type'=>'integer']],'delete_all_manual'=>['type'=>'boolean','description'=>'true ise RSS dışındaki tüm haberleri siler'],'confirm'=>['type'=>'boolean','description'=>'delete_all_manual için true olmalı']]]],
 ['name'=>'set_status','description'=>'Haber durumunu değiştir (Yayında/Taslak/Planlandı/Arşiv).','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'integer'],'status'=>['type'=>'string','enum'=>$STS]],'required'=>['id','status']]],
 ['name'=>'fetch_rss','description'=>'RSS kaynaklarını şimdi çek (cron ile aynı iş).','inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
 ['name'=>'list_polls','description'=>'Anketleri listele.','inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
 ['name'=>'create_poll','description'=>'Yeni anket oluştur; ilk anket anasayfada gösterilir.','inputSchema'=>['type'=>'object','properties'=>['question'=>['type'=>'string'],'options'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['question','options']]],
 ['name'=>'toggle_poll','description'=>'Anketi aç/kapat.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'string'],'open'=>['type'=>'boolean']],'required'=>['id','open']]],
 ['name'=>'deploy_from_github','description'=>'GitHub deposundaki (erkankork-beep/skyturk) main dalını indirip site dosyalarını public_html üzerine kurar. data.json ve config.php korunur.','inputSchema'=>['type'=>'object','properties'=>['ref'=>['type'=>'string','default'=>'main']]]],
 ['name'=>'set_secret','description'=>'Sunucu gizli ayarı yaz: ANTHROPIC_KEY (Haiku özgünleştirme için API anahtarı) veya REWRITE_MODEL. Değer depoya girmez, api/secrets.php içinde tutulur.','inputSchema'=>['type'=>'object','properties'=>['name'=>['type'=>'string','enum'=>['ANTHROPIC_KEY','REWRITE_MODEL']],'value'=>['type'=>'string']],'required'=>['name','value']]],
 ['name'=>'rewrite_now','description'=>'Bekleyen RSS haberlerini Haiku ile hemen özgünleştir (en fazla 20, ~40 sn).','inputSchema'=>['type'=>'object','properties'=>['max'=>['type'=>'integer','default'=>20]]]],
 ['name'=>'get_log','description'=>'Son RSS ve MCP işlem kayıtları.','inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
];

$raw=file_get_contents('php://input'); $req=json_decode($raw,true);
if(!is_array($req)) err(null,-32700,'Parse error');
$id=$req['id']??null; $m=$req['method']??''; $p=$req['params']??[];
if($m==='initialize') res($id,['protocolVersion'=>$p['protocolVersion']??'2025-03-26','capabilities'=>['tools'=>new stdClass()],'serverInfo'=>['name'=>'skyturk-cms','version'=>'1.0']]);
if($m==='notifications/initialized'||strpos($m,'notifications/')===0){http_response_code(202);exit;}
if($m==='ping') res($id,new stdClass());
if($m==='tools/list') res($id,['tools'=>$TOOLS]);
if($m!=='tools/call') err($id,-32601,'Method not found: '.$m);
$name=$p['name']??''; $a=$p['arguments']??[];
try{
switch($name){
 case 'get_stats':{$d=load();$n=$d['news'];$pub=count(array_filter($n,fn($x)=>($x['st']??'')==='Yayında'));$auto=count(array_filter($n,fn($x)=>!empty($x['auto'])));$dr=count(array_filter($n,fn($x)=>($x['st']??'')==='Taslak'));usort($n,fn($a,$b)=>tsOf($b)<=>tsOf($a));
  res($id,text(['toplam'=>count($d['news']),'yayinda'=>$pub,'taslak'=>$dr,'rss_otomatik'=>$auto,'ozgunlestirilmis'=>count(array_filter($n,fn($x)=>!empty($x['rw']))),'bekleyen'=>count(array_filter($n,fn($x)=>!empty($x['auto'])&&empty($x['rw'])&&($x['rwTries']??0)<2)),'elle'=>count($d['news'])-$auto,'acik_anket'=>count(array_filter($d['polls'],fn($q)=>!empty($q['open']))),'son_guncelleme'=>$d['updated']??null,'son_haber'=>$n?slim($n[0]):null]));}
 case 'list_news':{$d=load();$n=$d['news'];$q=mb_strtolower($a['query']??'');
  $n=array_filter($n,function($x)use($a,$q){if(!empty($a['cat'])&&$x['cat']!==$a['cat'])return false;if(!empty($a['status'])&&($x['st']??'')!==$a['status'])return false;if(!empty($a['manual_only'])&&!empty($x['auto']))return false;if(!empty($a['ozel_only'])&&empty($x['ozel']))return false;if($q&&mb_strpos(mb_strtolower(($x['t']??'').' '.($x['s']??'').' '.implode(' ',$x['tags']??[])),$q)===false)return false;return true;});
  usort($n,fn($a,$b)=>tsOf($b)<=>tsOf($a));$lim=max(1,min(200,(int)($a['limit']??30)));
  res($id,text(['count'=>count($n),'items'=>array_map('slim',array_slice(array_values($n),0,$lim))]));}
 case 'get_news':{$d=load();foreach($d['news'] as $x)if($x['id']==$a['id']){$o=slim($x);$o['body']=$x['p']??[];$o['imgPrompt']=$x['imgPrompt']??null;$o['srcTitle']=$x['srcTitle']??null;$o['rewritten']=!empty($x['rw']);res($id,text($o));}res($id,text('Haber bulunamadı: '.$a['id'],true));}
 case 'create_news':{$d=load();if(!in_array($a['cat'],$CATS))res($id,text('Geçersiz kategori',true));$nid=max(array_merge([5000],array_map(fn($x)=>(int)$x['id'],array_filter($d['news'],fn($x)=>empty($x['auto'])))))+1;
  $date=$a['date']??date('d.m.Y H:i');$paras=array_values(array_filter(array_map('trim',preg_split('/\n\s*\n/',(string)($a['body']??'')))));if(!$paras)$paras=[$a['spot']??''];
  $n=['id'=>$nid,'cat'=>$a['cat'],'t'=>trim($a['title']),'s'=>trim($a['spot']??''),'d'=>$date,'by'=>$a['by']??'Skytürk Haber Merkezi','v'=>0,'st'=>$a['status']??'Yayında','tags'=>$a['tags']??[],'ozel'=>!empty($a['ozel']),'p'=>$paras];
  array_unshift($d['news'],$n);save($d);logm("MCP create #$nid ".$n['t']);res($id,text(['ok'=>true,'created'=>slim($n),'url'=>'https://testhabersitesimiz.site/#/haber/'.$nid]));}
 case 'update_news':{$d=load();$found=false;foreach($d['news'] as &$x){if($x['id']==$a['id']){$found=true;foreach(['title'=>'t','spot'=>'s','cat'=>'cat','status'=>'st','tags'=>'tags','by'=>'by','ozel'=>'ozel','date'=>'d'] as $k=>$f)if(array_key_exists($k,$a))$x[$f]=$a[$k];if(isset($a['body']))$x['p']=array_values(array_filter(array_map('trim',preg_split('/\n\s*\n/',$a['body']))));unset($x['auto']);$out=slim($x);}}unset($x);
  if(!$found)res($id,text('Haber bulunamadı',true));save($d);logm('MCP update #'.$a['id']);res($id,text(['ok'=>true,'updated'=>$out]));}
 case 'delete_news':{$d=load();$before=count($d['news']);
  if(!empty($a['delete_all_manual'])){if(empty($a['confirm']))res($id,text('confirm=true gerekli',true));$d['news']=array_values(array_filter($d['news'],fn($x)=>!empty($x['auto'])));}
  else{$ids=array_map('intval',$a['ids']??[]);$d['news']=array_values(array_filter($d['news'],fn($x)=>!in_array((int)$x['id'],$ids)));}
  save($d);$n=$before-count($d['news']);logm("MCP delete $n haber");res($id,text(['ok'=>true,'deleted'=>$n,'remaining'=>count($d['news'])]));}
 case 'set_status':{$d=load();$ok=false;foreach($d['news'] as &$x)if($x['id']==$a['id']){$x['st']=$a['status'];$ok=true;}unset($x);if(!$ok)res($id,text('Haber bulunamadı',true));save($d);res($id,text(['ok'=>true,'id'=>$a['id'],'status'=>$a['status']]));}
 case 'fetch_rss':{$self='https://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']).'/fetch.php?key='.urlencode(SKYTURK_TOKEN);
  $ch=curl_init($self);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT_MS=>1500,CURLOPT_NOSIGNAL=>1,CURLOPT_SSL_VERIFYPEER=>false]);@curl_exec($ch);curl_close($ch);
  logm('MCP fetch_rss başlatıldı');res($id,text(['ok'=>true,'message'=>'RSS çekimi arka planda başlatıldı; 30-60 sn sonra get_log ile sonucu görün.']));}
 case 'list_polls':{$d=load();res($id,text($d['polls']));}
 case 'create_poll':{$d=load();$q=['id'=>'p'.time(),'q'=>$a['question'],'o'=>array_map(fn($o)=>[$o,0],$a['options']),'votes'=>0,'open'=>true];array_unshift($d['polls'],$q);save($d);res($id,text(['ok'=>true,'poll'=>$q]));}
 case 'toggle_poll':{$d=load();$ok=false;foreach($d['polls'] as &$q)if($q['id']===$a['id']){$q['open']=(bool)$a['open'];$ok=true;}unset($q);if(!$ok)res($id,text('Anket bulunamadı',true));save($d);res($id,text(['ok'=>true]));}
 case 'deploy_from_github':{$ref=preg_replace('/[^a-zA-Z0-9_.\/-]/','',$a['ref']??'main');$url="https://codeload.github.com/erkankork-beep/skyturk/zip/refs/heads/$ref";
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_TIMEOUT=>60,CURLOPT_USERAGENT=>'SKYTURK-deploy']);$zipdata=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if(!$zipdata||$code!=200)res($id,text("GitHub'dan indirilemedi (HTTP $code)",true));
  $tmp=sys_get_temp_dir().'/skyturk_'.uniqid();$zp=$tmp.'.zip';file_put_contents($zp,$zipdata);$z=new ZipArchive();if($z->open($zp)!==true)res($id,text('Zip açılamadı',true));mkdir($tmp);$z->extractTo($tmp);$z->close();@unlink($zp);
  $root=glob($tmp.'/*',GLOB_ONLYDIR)[0]??null;if(!$root)res($id,text('Paket boş',true));
  $dst=dirname(__DIR__);$files=['index.html','404.html','.htaccess','cms/index.html','api/fetch.php','api/feeds.php','api/data.php','api/.htaccess','api/'.basename(__FILE__),'api/rewrite.php'];$done=[];
  foreach($files as $f){if(file_exists("$root/$f")){@mkdir(dirname("$dst/$f"),0755,true);copy("$root/$f","$dst/$f");$done[]=$f;}}
  $sha=trim(@file_get_contents("$root/.git_sha")?:'');logm('MCP deploy '.$ref.' → '.count($done).' dosya');
  res($id,text(['ok'=>true,'ref'=>$ref,'files'=>$done,'not_in_repo'=>array_values(array_diff($files,$done))]));}
 case 'set_secret':{$sec=file_exists(__DIR__.'/secrets.php')?(include __DIR__.'/secrets.php'):[];$sec[$a['name']]=trim($a['value']);file_put_contents(__DIR__.'/secrets.php',"<?php return ".var_export($sec,true).";\n",LOCK_EX);logm('MCP set_secret '.$a['name']);res($id,text(['ok'=>true,'has_key'=>!empty($sec['ANTHROPIC_KEY']),'model'=>$sec['REWRITE_MODEL']??'claude-haiku-4-5-20251001']));}
 case 'rewrite_now':{require_once __DIR__.'/rewrite.php';$d=load();$r=skyturk_rewrite($d['news'],max(1,min(20,(int)($a['max']??20))),40);save($d);logm('MCP rewrite '.json_encode($r));res($id,text($r));}
 case 'get_log':{res($id,text(['rss'=>explode("\n",substr((string)@file_get_contents(__DIR__.'/fetch.log'),0,2000)),'mcp'=>explode("\n",substr((string)@file_get_contents($LOG),0,2000))]));}
 default: err($id,-32602,'Unknown tool: '.$name);
}}catch(Throwable $e){res($id,text('Hata: '.$e->getMessage(),true));}
