<?php
/* SKYTÜRK Dijital Gazete — günlük 10 sayfalık PDF üretimi (tFPDF, Unicode)
   Elle: php gazete.php   |  fetch.php saat 09:00'dan sonra günün sayısı yoksa çağırır  */
define('FPDF_FONTPATH', __DIR__.'/lib/font/');
define('_SYSTEM_TTFONTS', __DIR__.'/lib/font/unifont/');
require_once __DIR__.'/lib/tfpdf.php'; require_once __DIR__.'/lib/font/unifont/ttfonts.php';
date_default_timezone_set('Europe/Istanbul');

class SkyPDF extends tFPDF {
  function W(){return $this->w;} function Hh(){return $this->h;}
  public $ink=[17,17,17]; public $grey=[85,85,85]; public $sky=[46,155,240]; public $navy=[11,42,107]; public $red=[216,35,42]; public $yel=[255,212,0]; public $line=[201,211,227]; public $soft=[243,246,250];
  public $catMap=['gundem'=>['GÜNDEM','#0B4F9E'],'politika'=>['POLİTİKA','#3F51B5'],'dunya'=>['DÜNYA','#00897B'],'ekonomi'=>['EKONOMİ','#F9A825'],'spor'=>['SPOR','#2E7D32'],'kultur-sanat'=>['KÜLTÜR-SANAT','#8E24AA'],'saglik'=>['SAĞLIK','#D81B60'],'yasam'=>['YAŞAM','#FB8C00'],'teknoloji'=>['TEKNOLOJİ','#2E9BF0'],'egitim'=>['EĞİTİM','#5C6BC0'],'genel'=>['GENEL','#546E7A'],'son-dakika'=>['SON DAKİKA','#E8262C'],'istanbul'=>['İSTANBUL','#455A64'],'ankara'=>['ANKARA','#7B1FA2']];
  public $imgDir; public $dateStr; public $issueNo; public $pageNo=0; public $totalPages=10;
  function hex($h){ $h=ltrim($h,'#'); return [hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2))]; }
  function fill($c){ $this->SetFillColor($c[0],$c[1],$c[2]); } function color($c){ $this->SetTextColor($c[0],$c[1],$c[2]); } function draw($c){ $this->SetDrawColor($c[0],$c[1],$c[2]); }
  function catName($k){ return $this->catMap[$k][0]??mb_strtoupper($k); } function catCol($k){ return $this->hex($this->catMap[$k][1]??'#0B4F9E'); }
  function T($x,$y,$s){ $this->Text($x,$y,$s); }
  /* satır kırma */
  function wrap($s,$font,$size,$w){ $this->SetFont($font,'',$size); $words=preg_split('/\s+/u',trim($s)); $lines=[]; $cur='';
    foreach($words as $wd){ $t=$cur===''?$wd:$cur.' '.$wd; if($this->GetStringWidth($t)<=$w) $cur=$t; else { if($cur!=='') $lines[]=$cur; $cur=$wd; } } if($cur!=='') $lines[]=$cur; return $lines; }
  function fit($s,$font,$w,$h,$start,$min=13,$lead=1.0){ for($sz=$start;$sz>=$min;$sz--){ $ls=$this->wrap($s,$font,$sz,$w); if(count($ls)*$sz*$lead<=$h) return [$sz,$ls]; } $ls=$this->wrap($s,$font,$min,$w); return [$min,array_slice($ls,0,max(1,(int)floor($h/($min*$lead))))]; }
  /* metin bloğu: sol-üst köşe, döndürür alt y */
  function block($x,$y,$w,$s,$font,$size,$col,$lead=1.28,$maxl=0){ $ls=$this->wrap($s,$font,$size,$w); if($maxl&&count($ls)>$maxl){ $ls=array_slice($ls,0,$maxl); $ls[$maxl-1]=mb_substr($ls[$maxl-1],0,max(0,mb_strlen($ls[$maxl-1])-1)).'…'; }
    $this->SetFont($font,'',$size); $this->color($col); foreach($ls as $l){ $y+=$size; $this->T($x,$y,$l); $y+=$size*($lead-1); } return $y; }
  function head($x,$y,$w,$h,$s,$col,$start,$upper=true,$lead=.98,$align='L'){ $t=$upper?mb_strtoupper($s):$s; [$sz,$ls]=$this->fit($t,'T',$w,$h,$start,13,$lead); $this->SetFont('T','',$sz); $this->color($col); $yy=$y+$sz*.92;
    foreach($ls as $l){ $xx=$align==='C'?$x+($w-$this->GetStringWidth($l))/2:$x; $this->T($xx,$yy,$l); $yy+=$sz*$lead; } return $yy-$sz*$lead+$sz*.3; }
  function kicker($x,$y,$s,$bg,$fg,$size=8.5){ $this->SetFont('TB','',$size); $s=mb_strtoupper($s); $w=$this->GetStringWidth($s)+12; $this->fill($bg); $this->Rect($x,$y,$w,$size+7,'F'); $this->color($fg); $this->T($x+6,$y+$size+1,$s); return $w; }
  function rule($x1,$x2,$y,$c=null,$wd=.6){ $this->draw($c?:$this->line); $this->SetLineWidth($wd); $this->Line($x1,$y,$x2,$y); }
  /* görsel: Pexels/YouTube URL → önbellek → JPEG; yoksa renkli kutu */
  function img($x,$y,$w,$h,$n){ $f=$this->fetchImg($n); if($f){ try{ $this->Image($f,$x,$y,$w,$h,'JPG'); $this->draw([255,255,255]); $this->SetLineWidth(1); $this->Rect($x,$y,$w,$h); return true; }catch(Throwable $e){} }
    $c=$this->catCol($n['cat']??'gundem'); $this->fill($c); $this->Rect($x,$y,$w,$h,'F');
    $this->SetFont('SC','',7); $this->color([255,255,255]); $this->T($x+6,$y+$h-6,$this->catName($n['cat']??'gundem')); return false; }
  function fetchImg($n){ if(empty($n['imgUrl'])) return null; if(!is_dir($this->imgDir)) @mkdir($this->imgDir,0755,true); $f=$this->imgDir.'/'.md5($n['imgUrl']).'.jpg'; if(file_exists($f)&&filesize($f)>2000) return $f;
    $ch=curl_init($n['imgUrl']); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_USERAGENT=>'SKYTURK-gazete']); $d=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $ct=curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
    if($code!==200||!$d) return null; if(function_exists('imagecreatefromstring')){ $im=@imagecreatefromstring($d); if(!$im) return null; $mw=940; $w=imagesx($im); $h=imagesy($im); if($w>$mw){ $nh=(int)($h*$mw/$w); $t=imagecreatetruecolor($mw,$nh); imagecopyresampled($t,$im,0,0,0,0,$mw,$nh,$w,$h); imagedestroy($im); $im=$t; } imagejpeg($im,$f,82); imagedestroy($im); return file_exists($f)?$f:null; }
    if(stripos((string)$ct,'jpeg')!==false){ file_put_contents($f,$d); return $f; } return null; }
  function SetAlpha($a){ /* tFPDF'te alpha yok — yaklaşık: atla */ }
  /* üst/alt bilgi */
  function mastheadInner(){ $this->fill($this->ink); $this->Rect(0,0,$this->w,22,'F'); $this->SetFont('H','',12); $this->color([255,255,255]); $this->T(36,15,'SKYTÜRK'); $this->SetFont('SC','',8); $this->color([201,211,227]); $s=mb_strtoupper($this->dateStr).'  ·  SAYI '.$this->issueNo.'  ·  testhabersitesimiz.site'; $this->T($this->w-36-$this->GetStringWidth($s),15,$s); return 40; }
  function footer2(){ $this->rule(36,$this->w-36,$this->h-28); $this->SetFont('SC','',7.5); $this->color($this->grey); $this->T(36,$this->h-16,'SKYTÜRK Dijital Gazete · Haberler açık RSS akışlarından SKYTÜRK editörlüğüyle özetlenmiştir; kaynaklar her haberde belirtilir. Fotoğraflar temsilidir (Pexels).'); $s='Sayfa '.$this->pageNo.' / '.$this->totalPages; $this->T($this->w-36-$this->GetStringWidth($s),$this->h-16,$s); }
  function section($y,$cat,$x=36,$w=null){ $w=$w??($this->w-72); $this->fill($this->catCol($cat)); $this->Rect($x,$y-14,4,18,'F'); $this->SetFont('H','',15); $this->color($this->ink); $this->T($x+10,$y,$this->catName($cat)); $this->rule($x,$x+$w,$y+8); return $y+26; }
  function story($x,$y,$w,$n,$imgH=0,$size=11.5,$spot=true,$maxl=0,$src=true){ if($imgH){ $this->img($x,$y,$w,$imgH,$n); $y+=$imgH+10; }
    $y=$this->block($x,$y,$w,$n['t'],'H',$size,$this->ink,1.2,$maxl); if($spot){ $y+=3; $y=$this->block($x,$y,$w,$n['s']??'','B',8.6,$this->grey,1.3,5); }
    if($src){ $this->SetFont('SC','',7.2); $this->color($this->sky); $this->T($x,$y+9,'Kaynak: '.($n['srcName']??$n['by']??'').' · '.substr($n['d']??'',11)); $y+=12; } return $y+8; }
  function cols($ytop,$ybot,$ncol,$items,$gap=14,$imgFirst=110){ $x0=36; $cw=($this->w-72-$gap*($ncol-1))/$ncol; $xs=[]; $ys=[]; for($i=0;$i<$ncol;$i++){ $xs[]=$x0+$i*($cw+$gap); $ys[]=$ytop; } $used=0;
    foreach($items as $k=>$n){ $ci=0; foreach($ys as $j=>$v) if($v<$ys[$ci]) $ci=$j; $y=$ys[$ci]; if($y>$ybot-90) break; $yy=$this->story($xs[$ci],$y,$cw,$n,$k<$ncol?$imgFirst:0,11); $this->rule($xs[$ci],$xs[$ci]+$cw,$yy-2,null,.4); $ys[$ci]=$yy+8; $used++; }
    for($j=1;$j<$ncol;$j++){ $this->draw($this->line); $this->SetLineWidth(.4); $this->Line($xs[$j]-$gap/2,$ytop-8,$xs[$j]-$gap/2,$ybot); } return $used; }
}

function skyturk_gazete_build($force=false){
  $root=dirname(__DIR__); $gdir=$root.'/gazete'; if(!is_dir($gdir)) @mkdir($gdir,0755,true);
  $issuesF=$gdir.'/issues.json'; $issues=file_exists($issuesF)?(json_decode(file_get_contents($issuesF),true)?:[]):[];
  $today=date('Y-m-d'); foreach($issues as $is) if(($is['date']??'')===$today&&!$force) return ['skipped'=>'bugünün sayısı var','issue'=>$is];
  $data=json_decode(@file_get_contents(__DIR__.'/data.json'),true)?:['news'=>[]];
  $all=array_values(array_filter($data['news'],fn($n)=>!empty($n['rw'])&&empty($n['video'])&&($n['st']??'')==='Yayında'));
  $ts=fn($n)=>$n['ts']??0; usort($all,fn($a,$b)=>$ts($b)<=>$ts($a));
  $win=array_values(array_filter($all,fn($n)=>$ts($n)>=time()-24*3600)); if(count($win)<40) $win=array_values(array_filter($all,fn($n)=>$ts($n)>=time()-48*3600)); if(count($win)<40) $win=$all;
  if(count($win)<12) return ['error'=>'yeterli haber yok ('.count($win).')'];
  $no=count($issues)+1; $months=['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık']; $days=['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
  $dateStr=date('j').' '.$months[(int)date('n')-1].' '.date('Y').' '.$days[(int)date('w')];
  $pdf=new SkyPDF('P','pt','A4'); $pdf->imgDir=$gdir.'/img'; $pdf->dateStr=$dateStr; $pdf->issueNo=$no; $pdf->SetAutoPageBreak(false); $pdf->SetMargins(0,0,0);
  foreach([['T','DejaVuSansCondensed-Bold.ttf'],['TB','DejaVuSans-Bold.ttf'],['B','DejaVuSans.ttf'],['SC','DejaVuSansCondensed.ttf'],['H','DejaVuSerif-Bold.ttf']] as [$k,$f]) $pdf->AddFont($k,'',$f,true);
  $pdf->SetTitle('SKYTÜRK Dijital Gazete — Sayı '.$no.' — '.$dateStr,true); $pdf->SetAuthor('SKYTÜRK',true);
  $W=$pdf->W(); $H=$pdf->Hh(); $M=36;
  $by=function($cat)use($win){ return array_values(array_filter($win,fn($n)=>($n['cat']??'')===$cat)); };
  $used=[]; $take=function($cats,$k)use(&$used,$win){ $out=[]; foreach($win as $n){ if(count($out)>=$k) break; if(in_array($n['cat']??'',$cats)&&!in_array($n['id'],$used)){ $out[]=$n; $used[]=$n['id']; } } return $out; };
  $fillTo=function(&$arr,$k)use(&$used,$win){ foreach($win as $n){ if(count($arr)>=$k) break; if(!in_array($n['id'],$used)){ $arr[]=$n; $used[]=$n['id']; } } };
  $rnd=mt_rand(0,2); $seed=crc32($today);
  /* ---------- KAPAK ---------- */
  $pdf->AddPage(); $pdf->pageNo=1;
  $mast=function($v)use($pdf,$W,$H,$M,$dateStr,$no){ if($v%2==0){ $pdf->fill($pdf->red); $pdf->Rect(0,0,$W,78,'F'); $pdf->SetFont('T','',60); $pdf->color([255,255,255]); $pdf->T($M,64,'SKYTÜRK');
      $pdf->fill($pdf->yel); $pdf->Rect($M+300,30,90,30,'F'); $pdf->SetFont('TB','',9); $pdf->color($pdf->ink); $pdf->T($M+318,42,'HER SABAH'); $pdf->T($M+326,54,"09:00'DA");
      $pdf->SetFont('SC','',8.5); $pdf->color([255,255,255]); foreach([[30,mb_strtoupper($dateStr).' · SAYI '.$no],[44,'DİJİTAL GAZETE · ÜCRETSİZ'],[58,'testhabersitesimiz.site']] as [$yy,$s]) $pdf->T($W-$M-$pdf->GetStringWidth($s),$yy,$s); }
    else { $pdf->fill($pdf->navy); $pdf->Rect(0,0,$W,84,'F'); $pdf->fill($pdf->red); $pdf->Rect(0,78,$W,6,'F'); $pdf->SetFont('T','',60); $pdf->color([255,255,255]); $s='SKYTÜRK'; $pdf->T(($W-$pdf->GetStringWidth($s))/2,62,$s);
      $pdf->SetFont('SC','',8.5); $pdf->color([201,211,227]); $pdf->T($M,76,mb_strtoupper($dateStr)); $s='SAYI '.$no.' · 10 SAYFA · testhabersitesimiz.site'; $pdf->T($W-$M-$pdf->GetStringWidth($s),76,$s); }
    $pdf->fill($pdf->ink); $pdf->Rect(0,84,$W,16,'F'); $pdf->SetFont('SC','',7.5); $pdf->color([255,255,255]); $pdf->T($M,95,'DOLAR 48,65   EURO 56,49   ALTIN 6.784   BİST 100 14.467   BİTCOİN 3.754.831'); $s='İSTANBUL 24° PARÇALI BULUTLU  ·  TRAFİK: 15 TEMMUZ KÖPRÜSÜ YOĞUN'; $pdf->T($W-$M-$pdf->GetStringWidth($s),95,$s); return 108; };
  $lead=$take(['gundem','son-dakika','politika','genel'],1)[0]??$win[0]; if(!in_array($lead['id'],$used)) $used[]=$lead['id']; $pool=$take(['gundem','politika','dunya','ekonomi','spor','saglik','genel','teknoloji','egitim','kultur-sanat'],12); $fillTo($pool,12);
  $CN=fn($k)=>$pdf->catName($k); $PG=fn($k)=>['gundem'=>2,'genel'=>3,'politika'=>4,'dunya'=>5,'ekonomi'=>6,'spor'=>7,'saglik'=>8,'teknoloji'=>8,'egitim'=>9,'kultur-sanat'=>9][$k]??2;
  $bottom3=function($y,$start)use($pdf,$pool,$W,$M,$CN,$PG){ $cw=($W-2*$M-20)/3; for($i=0;$i<3;$i++){ $n=$pool[$start+$i]??null; if(!$n) continue; $x=$M+$i*($cw+10); $pdf->fill([241,241,241]); $pdf->Rect($x,$y,$cw,70,'F'); $pdf->img($x+6,$y+6,64,58,$n); $pdf->head($x+78,$y+10,$cw-84,50,$n['t'],$pdf->ink,13,true,1.0); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x+78,$y+66,$CN($n['cat']).' · sayfa '.$PG($n['cat'])); } };
  if($rnd==0){ $y=$mast(0); $ph=300; $pdf->img($M,$y,$W-2*$M,$ph,$lead); $pdf->fill([0,0,0]); $pdf->Rect($M,$y+$ph-150,$W-2*$M,150,'F'); $pdf->kicker($M+12,$y+$ph-146,$CN($lead['cat']),$pdf->yel,$pdf->ink);
    $pdf->head($M+12,$y+$ph-124,$W-2*$M-24,100,$lead['t'],[255,255,255],44); $pdf->SetFont('B','',8.5); $pdf->color([255,255,255]); $pdf->T($M+12,$y+$ph-8,mb_substr($lead['s']??'',0,115).'…  Kaynak: '.($lead['srcName']??$lead['by']??''));
    $y+=$ph+14; $cw=($W-2*$M-20)/3; for($i=0;$i<3;$i++){ $n=$pool[$i]; $x=$M+$i*($cw+10); $pdf->img($x,$y,$cw,88,$n); $pdf->kicker($x,$y+92,$CN($n['cat']),[$pdf->red,$pdf->navy,$pdf->sky][$i],[255,255,255]); $yy=$pdf->head($x,$y+112,$cw,52,$n['t'],$pdf->ink,17,true,1.0); $yy=$pdf->block($x,$yy+4,$cw,$n['s']??'','B',8.2,$pdf->grey,1.28,3); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x,$y+218,'Kaynak: '.($n['srcName']??$n['by']??'').' · sayfa '.$PG($n['cat'])); }
    $y+=232; $pdf->fill($pdf->navy); $pdf->Rect($M,$y,$W-2*$M,96,'F'); $n=$pool[3]; $pdf->img($M+8,$y+8,150,80,$n); $pdf->kicker($M+170,$y+10,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($M+170,$y+30,$W-2*$M-190,44,$n['t'],[255,255,255],22); $pdf->SetFont('B','',8.3); $pdf->color([221,230,245]); $pdf->T($M+170,$y+88,mb_substr($n['s']??'',0,100).'…');
    $y+=110; $cw2=($W-2*$M-10)/2; for($i=0;$i<2;$i++){ $n=$pool[4+$i]; $x=$M+$i*($cw2+10); $pdf->fill([241,241,241]); $pdf->Rect($x,$y,$cw2,58,'F'); $pdf->img($x+6,$y+6,70,46,$n); $pdf->head($x+84,$y+8,$cw2-92,40,$n['t'],$pdf->ink,15,true,1.0); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x+84,$y+54,'sayfa '.$PG($n['cat'])); }
    $y+=72; $bottom3($y,6); }
  elseif($rnd==1){ $y=$mast(1); $lw=($W-2*$M)*.5; $pdf->fill($pdf->red); $pdf->Rect($M,$y,$lw,250,'F'); $pdf->kicker($M+12,$y+10,'MANŞET',$pdf->ink,$pdf->yel); $pdf->head($M+12,$y+34,$lw-24,150,$lead['t'],[255,255,255],50,true,.95); $pdf->block($M+12,$y+196,$lw-24,$lead['s']??'','B',9,[255,255,255],1.3,3);
    $pdf->img($M+$lw,$y,$W-$M-($M+$lw),250,$lead); $pdf->fill($pdf->red); $pdf->Rect($M+$lw,$y+234,$W-$M-($M+$lw),16,'F'); $pdf->SetFont('SC','',7.5); $pdf->color([255,255,255]); $pdf->T($M+$lw+6,$y+245,'FOTOĞRAF: PEXELS (TEMSİLİ) · KAYNAK: '.mb_strtoupper($lead['srcName']??$lead['by']??''));
    $y+=262; $cw=($W-2*$M-24)/4; for($i=0;$i<4;$i++){ $n=$pool[$i]; $x=$M+$i*($cw+8); $pdf->img($x,$y,$cw,100,$n); $pdf->fill([$pdf->navy,$pdf->red,$pdf->ink,$pdf->sky][$i]); $pdf->Rect($x,$y+100,$cw,50,'F'); $pdf->head($x+5,$y+104,$cw-10,42,$n['t'],[255,255,255],14,true,1.0); }
    $y+=164; $pdf->fill($pdf->ink); $pdf->Rect($M,$y,$W-2*$M,2,'F'); $y+=14; $cw2=($W-2*$M-10)/2; $n=$pool[4]; $pdf->kicker($M,$y,$CN($n['cat']),$pdf->navy,[255,255,255]); $yy=$pdf->head($M,$y+20,$cw2,52,$n['t'],$pdf->ink,22); $pdf->block($M,$yy+4,$cw2,$n['s']??'','B',8.4,$pdf->grey,1.28,4);
    $n=$pool[5]; $x=$M+$cw2+10; $pdf->img($x,$y,$cw2,100,$n); $pdf->fill([0,0,0]); $pdf->Rect($x,$y+56,$cw2,44,'F'); $pdf->head($x+6,$y+60,$cw2-12,36,$n['t'],[255,255,255],15);
    $y+=126; $bottom3($y,6); $y+=84; $n=$pool[9]??$pool[0]; $pdf->fill($pdf->navy); $pdf->Rect($M,$y,$W-2*$M,56,'F'); $pdf->kicker($M+10,$y+6,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($M+10,$y+22,$W-2*$M-20,32,$n['t'],[255,255,255],20); }
  else { $y=$mast(0); $g=10; $cw=($W-2*$M-$g)/2; $pdf->img($M,$y,$cw,230,$lead); $pdf->fill($pdf->yel); $pdf->Rect($M,$y+160,$cw,70,'F'); $pdf->head($M+8,$y+164,$cw-16,62,$lead['t'],$pdf->ink,24,true,.98); $pdf->kicker($M+8,$y+8,'GÜNÜN HABERİ',$pdf->red,[255,255,255]);
    $n=$pool[0]; $x=$M+$cw+$g; $pdf->fill($pdf->navy); $pdf->Rect($x,$y,$cw,230,'F'); $pdf->img($x+8,$y+14,$cw-16,104,$n); $pdf->kicker($x+8,$y+122,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($x+8,$y+140,$cw-16,60,$n['t'],[255,255,255],22); $pdf->block($x+8,$y+204,$cw-16,$n['s']??'','B',8.2,[221,230,245],1.28,1);
    $y+=244; $cw4=($W-2*$M-3*$g)/4; for($i=0;$i<4;$i++){ $n=$pool[1+$i]; $x=$M+$i*($cw4+$g); $pdf->img($x,$y,$cw4,84,$n); $pdf->fill([$pdf->red,$pdf->ink,$pdf->sky,$pdf->navy][$i]); $pdf->Rect($x,$y+70,$cw4,14,'F'); $pdf->SetFont('TB','',7); $pdf->color([255,255,255]); $pdf->T($x+4,$y+80,$CN($n['cat'])); $yy=$pdf->head($x,$y+92,$cw4,46,$n['t'],$pdf->ink,13,true,1.0); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x,$yy+10,'Kaynak: '.($n['srcName']??$n['by']??'')); }
    $y+=160; $pdf->fill($pdf->red); $pdf->Rect($M,$y,$W-2*$M,64,'F'); $n=$pool[5]; $pdf->kicker($M+10,$y+6,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($M+10,$y+24,$W-2*$M-20,36,$n['t'],[255,255,255],22);
    $y+=78; $n=$pool[6]; $pdf->fill([238,238,238]); $pdf->Rect($M,$y,$W-2*$M,52,'F'); $pdf->img($M+6,$y+6,60,40,$n); $pdf->head($M+74,$y+6,$W-2*$M-90,40,$n['t'],$pdf->ink,16,true,1.0);
    $y+=66; $bottom3($y,7); $y+=84; $n=$pool[10]??$pool[1]; $pdf->kicker($M,$y,$CN($n['cat']),$pdf->red,[255,255,255]); $yy=$pdf->head($M,$y+20,$W-2*$M,40,$n['t'],$pdf->ink,20); $pdf->block($M,$yy+4,$W-2*$M,$n['s']??'','B',8.4,$pdf->grey,1.28,2); }
  /* son dakika şeridi */
  $pdf->fill($pdf->red); $pdf->Rect(0,$H-20,$W,20,'F'); $pdf->SetFont('TB','',8); $pdf->color([255,255,255]); $pdf->T($M,$H-7,'SON DAKİKA'); $pdf->SetFont('B','',8); $sd=array_slice($win,0,3); $pdf->T($M+66,$H-7,implode('   ',array_map(fn($n)=>'▸ '.mb_substr($n['t'],0,52),$sd)));
  $pdf->SetFont('SC','',6.5); $pdf->color($pdf->grey); $s='Haberler açık RSS akışlarından SKYTÜRK editörlüğüyle özetlenmiştir · Fotoğraflar temsilidir (Pexels) · Sayfa 1/10'; $pdf->T($W-$M-$pdf->GetStringWidth($s),$H-24,$s);
  /* ---------- İÇ SAYFALAR ---------- */
  $used=[$lead['id']]; // kapaktakiler iç sayfada tekrar kullanılabilir (gazete mantığı: kapak = özet)
  $pageCats=[[2,['gundem','son-dakika'],3],[3,['gundem','son-dakika','genel','istanbul','ankara'],3],[4,['politika'],'pol'],[5,['dunya'],3],[6,['ekonomi'],'eko'],[7,['spor'],'spor'],[8,['saglik','teknoloji'],'dual'],[9,['egitim','kultur-sanat','yasam'],'dual'],[10,[],'back']];
  $stand=[['Galatasaray',5,13],['Fenerbahçe',5,11],['Beşiktaş',5,10],['Trabzonspor',5,9],['Konyaspor',5,8],['Göztepe',5,8],['Kocaelispor',5,7],['Gençlerbirliği',5,6]];
  foreach($pageCats as [$pn,$cats,$mode]){
    $pdf->AddPage(); $pdf->pageNo=$pn; $y=$pdf->mastheadInner();
    if($mode===3){ $items=$take($cats,9); $fillTo($items,8); $y=$pdf->section($y+14,$cats[0]); $pdf->cols($y,$H-40,3,$items); }
    elseif($mode==='pol'){ $p=$take($cats,6); $fillTo($p,5); $y=$pdf->section($y+14,'politika'); $lw=($W-2*$M)*.58; $pdf->story($M,$y,$lw,$p[0],150,15); $rx=$M+$lw+16; $rw=$W-$M-$rx; $yy=$y; foreach(array_slice($p,1,5) as $n){ $yy=$pdf->story($rx,$yy,$rw,$n,0,10.5,true,3); $pdf->rule($rx,$rx+$rw,$yy-2,null,.4); $yy+=6; if($yy>$H-120) break; } $pdf->draw($pdf->line); $pdf->Line($rx-8,$y-8,$rx-8,$H-60); }
    elseif($mode==='eko'){ $e=$take($cats,6); $fillTo($e,4); $y=$pdf->section($y+14,'ekonomi'); $lw=($W-2*$M)*.6; $yy=$pdf->story($M,$y,$lw,$e[0],170,15); foreach(array_slice($e,1) as $n){ if($yy>$H-150) break; $yy=$pdf->story($M,$yy,$lw,$n,0,12); $pdf->rule($M,$M+$lw,$yy-2,null,.4); $yy+=6; }
      $rx=$M+$lw+16; $rw=$W-$M-$rx; $pdf->fill($pdf->soft); $pdf->Rect($rx,$y,$rw,300,'F'); $pdf->SetFont('H','',11); $pdf->color($pdf->ink); $pdf->T($rx+12,$y+20,'PİYASALAR'); $pdf->rule($rx+12,$rx+$rw-12,$y+26,$pdf->navy,1.2); $yy=$y+46;
      foreach([['DOLAR','48,65','+0,12%',1],['EURO','56,49','-0,08%',0],['STERLİN','65,91','+0,04%',1],['ALTIN (gr)','6.784,88','-0,31%',0],['BİST 100','14.467','+0,82%',1],['BİTCOİN','3.754.831','-0,80%',0]] as [$k,$v,$d,$up]){ $pdf->SetFont('TB','',9); $pdf->color($pdf->ink); $pdf->T($rx+12,$yy,$k); $pdf->SetFont('H','',13); $pdf->T($rx+$rw-12-$pdf->GetStringWidth($v),$yy+2,$v); $pdf->SetFont('SC','',8); $pdf->color($up?[34,160,107]:$pdf->red); $pdf->T($rx+$rw-12-$pdf->GetStringWidth($d),$yy+13,$d); $pdf->rule($rx+12,$rx+$rw-12,$yy+18,null,.4); $yy+=36; }
      $pdf->SetFont('SC','',7); $pdf->color($pdf->grey); $pdf->T($rx+12,$y+290,'Kapanış verileri · bilgi amaçlıdır'); }
    elseif($mode==='spor'){ $s=$take($cats,9); $fillTo($s,6); $y=$pdf->section($y+14,'spor'); $lw=($W-2*$M)*.55; $yy=$pdf->story($M,$y,$lw,$s[0],160,15); $yy=$pdf->story($M,$yy,$lw,$s[1]??$s[0],0,12);
      $rx=$M+$lw+16; $rw=$W-$M-$rx; $yy2=$y; foreach(array_slice($s,2,4) as $n){ $yy2=$pdf->story($rx,$yy2,$rw,$n,0,10.5,false,3); $pdf->rule($rx,$rx+$rw,$yy2-4,null,.4); $yy2+=4; }
      $bh=$H-70-$yy2-10; $pdf->fill($pdf->soft); $pdf->Rect($rx,$yy2+10,$rw,$bh,'F'); $pdf->SetFont('H','',10.5); $pdf->color($pdf->ink); $pdf->T($rx+10,$yy2+28,'SÜPER LİG PUAN DURUMU'); $pdf->rule($rx+10,$rx+$rw-10,$yy2+34,[46,125,50],1.2); $ty=$yy2+50;
      foreach($stand as $i=>[$t,$o,$p]){ if($ty>$yy2+$bh) break; $pdf->SetFont($i<4?'TB':'B','',8.5); $pdf->color($i<4?[46,125,50]:$pdf->grey); $pdf->T($rx+10,$ty,(string)($i+1)); $pdf->color($pdf->ink); $pdf->T($rx+26,$ty,$t); $pdf->T($rx+$rw-40-$pdf->GetStringWidth((string)$o),$ty,(string)$o); $pdf->SetFont('TB','',8.5); $pdf->T($rx+$rw-10-$pdf->GetStringWidth((string)$p),$ty,(string)$p); $ty+=15; }
      foreach(array_slice($s,6) as $n){ if($yy>$H-100) break; $yy=$pdf->story($M,$yy,$lw,$n,0,11,true,2); $pdf->rule($M,$M+$lw,$yy-2,null,.4); $yy+=4; } }
    elseif($mode==='dual'){ $hw=($W-2*$M-16)/2; $c1=$cats[0]; $c2=$cats[1]; $a=$take([$c1],5); $fillTo($a,3); $b=$take(array_slice($cats,1),5); $fillTo($b,3);
      foreach([[$M,$c1,$a],[$M+$hw+16,$c2,$b]] as [$x,$c,$L]){ $yy=$pdf->section($y+14,$c,$x,$hw); $yy=$pdf->story($x,$yy,$hw,$L[0],120,13); foreach(array_slice($L,1) as $n){ if($yy>$H-120) break; $yy=$pdf->story($x,$yy,$hw,$n,0,11); $pdf->rule($x,$x+$hw,$yy-2,null,.4); $yy+=6; } }
      $pdf->draw($pdf->line); $pdf->Line($M+$hw+8,$y+6,$M+$hw+8,$H-60); }
    else { /* arka sayfa */ $astro=$data['astro']['items']??[]; $signs=[['koc','♈','Koç'],['boga','♉','Boğa'],['ikizler','♊','İkizler'],['yengec','♋','Yengeç'],['aslan','♌','Aslan'],['basak','♍','Başak'],['terazi','♎','Terazi'],['akrep','♏','Akrep'],['yay','♐','Yay'],['oglak','♑','Oğlak'],['kova','♒','Kova'],['balik','♓','Balık']];
      $pdf->fill([63,81,181]); $pdf->Rect($M,$y,4,18,'F'); $pdf->SetFont('H','',15); $pdf->color($pdf->ink); $pdf->T($M+10,$y+14,'GÜNLÜK BURÇ YORUMLARI'); $pdf->rule($M,$W-$M,$y+22); $y+=36; $cw=($W-2*$M-24)/3; $ch=92;
      foreach($signs as $i=>[$id,$sym,$name]){ $x=$M+($i%3)*($cw+12); $yy=$y+intdiv($i,3)*($ch+10); $pdf->fill($pdf->soft); $pdf->Rect($x,$yy,$cw,$ch,'F'); $pdf->SetFont('B','',20); $pdf->color($pdf->navy); $pdf->T($x+10,$yy+26,$sym); $pdf->SetFont('H','',11); $pdf->color($pdf->ink); $pdf->T($x+36,$yy+22,mb_strtoupper($name));
        $it=$astro[$id]??null; $txt=$it['genel']??'Bugünün yorumu sitede: testhabersitesimiz.site/#/astroloji'; $pdf->block($x+10,$yy+32,$cw-20,$txt,'B',7.6,$pdf->grey,1.32,4); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x+10,$yy+$ch-8,$it?('Şanslı sayı '.($it['sansli_sayi']??'').' · Renk: '.($it['renk']??'')):''); }
      $yb=$y+4*($ch+10)+14; $pdf->rule($M,$W-$M,$yb,$pdf->navy,1.2); $yb+=22; $hw3=($W-2*$M-28)/3;
      $pdf->SetFont('H','',11); $pdf->color($pdf->ink); $pdf->T($M,$yb,'HAVA DURUMU · İSTANBUL'); $pdf->SetFont('H','',30); $pdf->T($M,$yb+36,'24°'); $pdf->SetFont('B','',8.5); $pdf->color($pdf->grey); $pdf->T($M+50,$yb+24,'Parçalı bulutlu'); $pdf->T($M+50,$yb+36,'Pzt 23° · Sal 22° · Çrş 25° · Prş 26°');
      $x=$M+$hw3+14; $pdf->SetFont('H','',11); $pdf->color($pdf->ink); $pdf->T($x,$yb,'GÜNÜN ANKETİ'); $poll=$data['polls'][0]??null; if($poll){ $pdf->block($x,$yb+8,$hw3,$poll['q'],'TB',8.5,$pdf->ink,1.28,2); $tot=max(1,$poll['votes']??0); foreach(array_slice($poll['o'],0,4) as $j=>$o){ $yy=$yb+44+$j*14; $pct=is_numeric($o[1]??null)?(int)$o[1]:0; $pdf->fill([227,232,239]); $pdf->Rect($x,$yy,$hw3,7,'F'); $pdf->fill($pdf->sky); $pdf->Rect($x,$yy,$hw3*max(0,min(100,$pct))/100,7,'F'); $pdf->SetFont('SC','',7.5); $pdf->color($pdf->grey); $pdf->T($x,$yy-2,$o[0]); $s=$pct.'%'; $pdf->T($x+$hw3-$pdf->GetStringWidth($s),$yy-2,$s); } }
      $pdf->SetFont('SC','',7); $pdf->color($pdf->grey); $pdf->T($x,$yb+100,'Oy vermek için: testhabersitesimiz.site/#/anketler');
      $x=$M+2*($hw3+14); $pdf->SetFont('H','',11); $pdf->color($pdf->ink); $pdf->T($x,$yb,'KÜNYE'); $pdf->SetFont('B','',8); $pdf->color($pdf->grey); foreach(['SKYTÜRK Medya Yayıncılık','İmtiyaz Sahibi: —','Genel Yayın Yönetmeni: —','Sorumlu Yazı İşleri Müdürü: —','Dijital Yayın: SKYTÜRK CMS','İletişim: info@skyturk.com.tr',"Bu gazete her sabah 09:00'da son 24 saatin",'haberlerinden otomatik derlenir.'] as $j=>$l) $pdf->T($x,$yb+16+$j*11,$l); }
    $pdf->footer2();
  }
  $fn='sayi-'.$no.'-'.$today.'.pdf'; $pdf->Output('F',$gdir.'/'.$fn);
  $issue=['no'=>$no,'date'=>$today,'dateStr'=>$dateStr,'file'=>'gazete/'.$fn,'pages'=>10,'lead'=>$lead['t'],'leadImg'=>$lead['imgUrl']??null,'count'=>count($win),'layout'=>$rnd,'created'=>date('c')];
  array_unshift($issues,$issue); file_put_contents($issuesF,json_encode($issues,JSON_UNESCAPED_UNICODE),LOCK_EX);
  return ['ok'=>true,'issue'=>$issue];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__){ echo json_encode(skyturk_gazete_build(in_array('--force',$argv)),JSON_UNESCAPED_UNICODE)."\n"; }
