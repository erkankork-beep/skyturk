<?php
/* SKYTÜRK Dijital Gazete — günlük 10 sayfalık PDF üretimi (tFPDF, Unicode)
   Elle: php gazete.php   |  fetch.php saat 09:00'dan sonra günün sayısı yoksa çağırır  */
define('FPDF_FONTPATH', __DIR__.'/lib/font/');
define('_SYSTEM_TTFONTS', __DIR__.'/lib/font/unifont/');
require_once __DIR__.'/lib/tfpdf.php'; require_once __DIR__.'/lib/font/unifont/ttfonts.php';
date_default_timezone_set('Europe/Istanbul');

function trUp($s){ return mb_strtoupper(str_replace(['i','ı'],['İ','I'],$s)); }
class SkyPDF extends tFPDF {
  function W(){return $this->w;} function Hh(){return $this->h;}
  public $ink=[17,17,17]; public $grey=[85,85,85]; public $sky=[46,155,240]; public $navy=[11,42,107]; public $red=[216,35,42]; public $yel=[255,212,0]; public $line=[201,211,227]; public $soft=[243,246,250];
  public $catMap=['gundem'=>['GÜNDEM','#0B4F9E'],'politika'=>['POLİTİKA','#3F51B5'],'dunya'=>['DÜNYA','#00897B'],'ekonomi'=>['EKONOMİ','#F9A825'],'spor'=>['SPOR','#2E7D32'],'kultur-sanat'=>['KÜLTÜR-SANAT','#8E24AA'],'saglik'=>['SAĞLIK','#D81B60'],'yasam'=>['YAŞAM','#FB8C00'],'teknoloji'=>['TEKNOLOJİ','#2E9BF0'],'magazin'=>['MAGAZİN','#E91E63'],'surmanset'=>['SÜRMANŞET','#B71C1C'],'egitim'=>['EĞİTİM','#5C6BC0'],'genel'=>['GENEL','#546E7A'],'son-dakika'=>['SON DAKİKA','#E8262C'],'istanbul'=>['İSTANBUL','#455A64'],'ankara'=>['ANKARA','#7B1FA2']];
  public $imgDir; public $dateStr; public $issueNo; public $pageNo=0; public $totalPages=10;
  function hex($h){ $h=ltrim($h,'#'); return [hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2))]; }
  function fill($c){ $this->SetFillColor($c[0],$c[1],$c[2]); } function color($c){ $this->SetTextColor($c[0],$c[1],$c[2]); } function draw($c){ $this->SetDrawColor($c[0],$c[1],$c[2]); }
  function catName($k){ return $this->catMap[$k][0]??trUp($k); } function catCol($k){ return $this->hex($this->catMap[$k][1]??'#0B4F9E'); }
  function T($x,$y,$s){ $this->Text($x,$y,$s); }
  /* satır kırma */
  function wrap($s,$font,$size,$w){ $this->SetFont($font,'',$size); $words=preg_split('/\s+/u',trim($s)); $lines=[]; $cur='';
    foreach($words as $wd){ $t=$cur===''?$wd:$cur.' '.$wd; if($this->GetStringWidth($t)<=$w) $cur=$t; else { if($cur!=='') $lines[]=$cur; $cur=$wd; } } if($cur!=='') $lines[]=$cur; return $lines; }
  function fit($s,$font,$w,$h,$start,$min=13,$lead=1.0){ for($sz=$start;$sz>=$min;$sz--){ $ls=$this->wrap($s,$font,$sz,$w); if(count($ls)*$sz*$lead<=$h) return [$sz,$ls]; } $ls=$this->wrap($s,$font,$min,$w); return [$min,array_slice($ls,0,max(1,(int)floor($h/($min*$lead))))]; }
  /* metin bloğu: sol-üst köşe, döndürür alt y */
  function block($x,$y,$w,$s,$font,$size,$col,$lead=1.28,$maxl=0){ $ls=$this->wrap($s,$font,$size,$w); if($maxl&&count($ls)>$maxl){ $ls=array_slice($ls,0,$maxl); $ls[$maxl-1]=mb_substr($ls[$maxl-1],0,max(0,mb_strlen($ls[$maxl-1])-1)).'…'; }
    $this->SetFont($font,'',$size); $this->color($col); foreach($ls as $l){ $y+=$size; $this->T($x,$y,$l); $y+=$size*($lead-1); } return $y; }
  function head($x,$y,$w,$h,$s,$col,$start,$upper=true,$lead=.98,$align='L'){ $t=$upper?trUp($s):$s; [$sz,$ls]=$this->fit($t,'T',$w,$h,$start,13,$lead); $this->SetFont('T','',$sz); $this->color($col); $yy=$y+$sz*.92;
    foreach($ls as $l){ $xx=$align==='C'?$x+($w-$this->GetStringWidth($l))/2:$x; $this->T($xx,$yy,$l); $yy+=$sz*$lead; } return $yy-$sz*$lead+$sz*.3; }
  function kicker($x,$y,$s,$bg,$fg,$size=8.5){ $this->SetFont('TB','',$size); $s=trUp($s); $w=$this->GetStringWidth($s)+12; $this->fill($bg); $this->Rect($x,$y,$w,$size+7,'F'); $this->color($fg); $this->T($x+6,$y+$size+1,$s); return $w; }
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
  function mastheadInner(){ $this->fill($this->ink); $this->Rect(0,0,$this->w,22,'F'); $this->SetFont('H','',12); $this->color([255,255,255]); $this->T(36,15,'SKYTÜRK'); $this->SetFont('SC','',8); $this->color([201,211,227]); $s=trUp($this->dateStr).'  ·  SAYI '.$this->issueNo.'  ·  testhabersitesimiz.site'; $this->T($this->w-36-$this->GetStringWidth($s),15,$s); return 40; }
  function footer2(){ $this->rule(36,$this->w-36,$this->h-28); $this->SetFont('SC','',7.5); $this->color($this->grey); $this->T(36,$this->h-16,'SKYTÜRK Dijital Gazete · Fotoğraflar temsilidir · Kaynaklar künyede.'); $s='Sayfa '.$this->pageNo.' / '.$this->totalPages; $this->T($this->w-36-$this->GetStringWidth($s),$this->h-16,$s); }
  function section($y,$cat,$x=36,$w=null){ $w=$w??($this->w-72); $this->fill($this->catCol($cat)); $this->Rect($x,$y-14,4,18,'F'); $this->SetFont('H','',15); $this->color($this->ink); $this->T($x+10,$y,$this->catName($cat)); $this->rule($x,$x+$w,$y+8); return $y+26; }
  function story($x,$y,$w,$n,$imgH=0,$size=11.5,$spot=true,$maxl=0,$src=false){ if($imgH){ $this->img($x,$y,$w,$imgH,$n); $y+=$imgH+10; }
    $y=$this->block($x,$y,$w,$n['t'],'H',$size,$this->ink,1.2,$maxl); if($spot){ $y+=3; $y=$this->block($x,$y,$w,$n['s']??'','B',8.6,$this->grey,1.3,5); }
    if($src){ $this->SetFont('SC','',7.2); $this->color($this->sky); $this->T($x,$y+9,'Kaynak: '.($n['srcName']??$n['by']??'').' · '.substr($n['d']??'',11)); $y+=12; } return $y+8; }
  function cols($ytop,$ybot,$ncol,$items,$gap=14,$imgFirst=110){ $x0=36; $cw=($this->w-72-$gap*($ncol-1))/$ncol; $xs=[]; $ys=[]; for($i=0;$i<$ncol;$i++){ $xs[]=$x0+$i*($cw+$gap); $ys[]=$ytop; } $used=0;
    foreach($items as $k=>$n){ $ci=0; foreach($ys as $j=>$v) if($v<$ys[$ci]) $ci=$j; $y=$ys[$ci]; if($y>$ybot-90) break; $yy=$this->story($xs[$ci],$y,$cw,$n,$k<$ncol?$imgFirst:0,11); $this->rule($xs[$ci],$xs[$ci]+$cw,$yy-2,null,.4); $ys[$ci]=$yy+8; $used++; }
    for($j=1;$j<$ncol;$j++){ $this->draw($this->line); $this->SetLineWidth(.4); $this->Line($xs[$j]-$gap/2,$ytop-8,$xs[$j]-$gap/2,$ybot); } return $used; }
}


/* ===== İÇ SAYFA ŞABLON MOTORU (12 kolon × 24 satır ızgara) =====
   slot: [col,row,cw,rh,type]  type: hero(fotoğraf+üstüne başlık) | photo(fotoğraf+başlık+spot) | box(renkli blok) | text | strip(yatay küçük foto+başlık) | yellow(sarı blok) */

/* GD ile kapak önizlemesi (Imagick yoksa) — A4 oranı 900×1273 */
function sky_cover_gd($out,$lead,$pool,$pdf,$no,$dateStr,$rnd){
  $W=900;$H=1273;$im=imagecreatetruecolor($W,$H); $white=imagecolorallocate($im,255,255,255); imagefill($im,0,0,$white);
  $red=imagecolorallocate($im,216,35,42); $navy=imagecolorallocate($im,11,42,107); $ink=imagecolorallocate($im,17,17,17); $yel=imagecolorallocate($im,255,212,0); $grey=imagecolorallocate($im,120,120,120); $soft=imagecolorallocate($im,241,241,241);
  $F=__DIR__.'/lib/font/unifont/'; $fT=$F.'DejaVuSansCondensed-Bold.ttf'; $fB=$F.'DejaVuSans.ttf';
  $bandCol=$rnd%2==0?$red:$navy; imagefilledrectangle($im,0,0,$W,120,$bandCol); imagettftext($im,74,0,28,96,$white,$fT,'SKYTÜRK'); imagettftext($im,13,0,560,48,$white,$fB,trUp($dateStr).' · SAYI '.$no); imagettftext($im,13,0,560,72,$white,$fB,'DİJİTAL GAZETE · 10 SAYFA');
  imagefilledrectangle($im,0,120,$W,146,$ink); imagettftext($im,12,0,28,138,$white,$fB,'DOLAR 48,65   EURO 56,49   ALTIN 6.784   BİST 100 14.467   İSTANBUL 24°');
  $placeImg=function($n,$x,$y,$w,$h)use($im,$pdf,$navy){ $f=$pdf->fetchImg($n); $ok=false; if($f){ $src=@imagecreatefromjpeg($f); if($src){ $sw=imagesx($src); $sh=imagesy($src); $r=max($w/$sw,$h/$sh); $cw=(int)($w/$r); $ch=(int)($h/$r); imagecopyresampled($im,$src,$x,$y,(int)(($sw-$cw)/2),(int)(($sh-$ch)/2),$w,$h,$cw,$ch); imagedestroy($src); $ok=true; } } if(!$ok){ $c=$pdf->catCol($n['cat']??'gundem'); imagefilledrectangle($im,$x,$y,$x+$w,$y+$h,imagecolorallocate($im,$c[0],$c[1],$c[2])); } };
  $wrap=function($txt,$size,$font,$maxw)use($im){ $words=preg_split('/\s+/u',$txt); $lines=[]; $cur=''; foreach($words as $wd){ $t=$cur===''?$wd:$cur.' '.$wd; $bb=imagettfbbox($size,0,$font,$t); if(($bb[2]-$bb[0])<=$maxw) $cur=$t; else { if($cur!=='') $lines[]=$cur; $cur=$wd; } } if($cur!=='') $lines[]=$cur; return $lines; };
  /* manşet fotoğrafı + başlık */ $placeImg($lead,28,170,$W-56,470); imagefilledrectangle($im,28,470,$W-28,640,imagecolorallocate($im,0,0,0)); imagefilledrectangle($im,44,486,44+140,486+28,$yel); imagettftext($im,13,0,52,506,$ink,$fT,trUp($pdf->catName($lead['cat']??'gundem')));
  $sz=34; $lines=$wrap(trUp($lead['t']),$sz,$fT,$W-100); if(count($lines)>2){ $sz=27; $lines=$wrap(trUp($lead['t']),$sz,$fT,$W-100); } $lines=array_slice($lines,0,3); $lh=(int)($sz*1.25); $yy=636-8-(count($lines)-1)*$lh-6; foreach($lines as $l){ imagettftext($im,$sz,0,44,$yy,$white,$fT,$l); $yy+=$lh; }
  /* alt: 3 haber */ $cw=(int)(($W-56-24)/3); for($i=0;$i<3;$i++){ $n=$pool[$i]??null; if(!$n) break; $x=28+$i*($cw+12); $placeImg($n,$x,664,$cw,200); imagefilledrectangle($im,$x,864,$x+$cw,868,[$red,$navy,$ink][$i]); $ls=$wrap(trUp($n['t']),17,$fT,$cw-8); $yy=896; foreach(array_slice($ls,0,4) as $l){ imagettftext($im,17,0,$x+4,$yy,$ink,$fT,$l); $yy+=22; } }
  /* alt bant */ imagefilledrectangle($im,28,1020,$W-28,1110,$navy); $n=$pool[3]??null; if($n){ $ls=$wrap(trUp($n['t']),22,$fT,$W-100); $yy=1058; foreach(array_slice($ls,0,2) as $l){ imagettftext($im,22,0,44,$yy,$white,$fT,$l); $yy+=30; } }
  $n=$pool[4]??null; if($n){ imagefilledrectangle($im,28,1126,$W-28,1200,$soft); $ls=$wrap(trUp($n['t']),20,$fT,$W-100); $yy=1160; foreach(array_slice($ls,0,2) as $l){ imagettftext($im,20,0,44,$yy,$ink,$fT,$l); $yy+=26; } }
  imagefilledrectangle($im,0,$H-36,$W,$H,$red); imagettftext($im,13,0,28,$H-13,$white,$fT,'SON DAKİKA   ▸ '.mb_substr($pool[5]['t']??'',0,70));
  $ok=imagejpeg($im,$out,86); imagedestroy($im); return $ok;
}

function sky_templates(){ return [
 [[0,0,12,9,'hero'],[0,9,4,7,'photo'],[4,9,4,7,'photo'],[8,9,4,7,'photo'],[0,16,6,4,'box'],[6,16,6,4,'strip'],[0,20,12,4,'text']],
 [[0,0,7,12,'hero'],[7,0,5,6,'box'],[7,6,5,6,'photo'],[0,12,4,6,'photo'],[4,12,4,6,'text'],[8,12,4,6,'photo'],[0,18,12,3,'strip'],[0,21,6,3,'text'],[6,21,6,3,'text']],
 [[0,0,6,10,'photo'],[6,0,6,10,'photo'],[0,10,12,4,'yellow'],[0,14,4,6,'strip'],[4,14,4,6,'strip'],[8,14,4,6,'strip'],[0,20,12,4,'box']],
 [[0,0,12,5,'box'],[0,5,3,8,'photo'],[3,5,3,8,'photo'],[6,5,3,8,'photo'],[9,5,3,8,'photo'],[0,13,8,7,'hero'],[8,13,4,7,'text'],[0,20,12,4,'strip']],
 [[0,0,5,14,'hero'],[5,0,7,7,'photo'],[5,7,7,7,'box'],[0,14,4,5,'text'],[4,14,4,5,'text'],[8,14,4,5,'text'],[0,19,12,5,'yellow']],
 [[0,0,8,8,'hero'],[8,0,4,8,'text'],[0,8,4,8,'photo'],[4,8,8,8,'box'],[0,16,6,4,'strip'],[6,16,6,4,'strip'],[0,20,12,4,'text']],
 [[0,0,4,12,'box'],[4,0,8,12,'hero'],[0,12,6,6,'photo'],[6,12,6,6,'photo'],[0,18,12,3,'yellow'],[0,21,4,3,'text'],[4,21,4,3,'text'],[8,21,4,3,'text']],
 [[0,0,12,4,'yellow'],[0,4,6,9,'hero'],[6,4,6,9,'hero'],[0,13,3,6,'text'],[3,13,3,6,'text'],[6,13,6,6,'photo'],[0,19,12,5,'box']],
 [[0,0,9,10,'hero'],[9,0,3,5,'box'],[9,5,3,5,'text'],[0,10,4,7,'photo'],[4,10,4,7,'photo'],[8,10,4,7,'photo'],[0,17,12,4,'strip'],[0,21,12,3,'text']],
 [[0,0,6,7,'box'],[6,0,6,7,'photo'],[0,7,12,8,'hero'],[0,15,4,5,'strip'],[4,15,4,5,'strip'],[8,15,4,5,'strip'],[0,20,6,4,'text'],[6,20,6,4,'yellow']],
 [[0,0,3,9,'photo'],[3,0,6,9,'hero'],[9,0,3,9,'photo'],[0,9,12,5,'box'],[0,14,6,5,'text'],[6,14,6,5,'text'],[0,19,12,5,'strip']],
 [[0,0,12,10,'hero'],[0,10,6,5,'yellow'],[6,10,6,5,'box'],[0,15,4,9,'photo'],[4,15,4,9,'photo'],[8,15,4,9,'photo']],
 [[0,0,8,6,'box'],[8,0,4,6,'text'],[0,6,4,10,'photo'],[4,6,8,10,'hero'],[0,16,12,4,'strip'],[0,20,12,4,'text']],
 [[0,0,6,12,'hero'],[6,0,6,4,'yellow'],[6,4,6,8,'photo'],[0,12,3,7,'text'],[3,12,3,7,'text'],[6,12,3,7,'text'],[9,12,3,7,'text'],[0,19,12,5,'box']],
 [[0,0,12,3,'strip'],[0,3,7,10,'hero'],[7,3,5,5,'box'],[7,8,5,5,'photo'],[0,13,4,6,'photo'],[4,13,4,6,'text'],[8,13,4,6,'photo'],[0,19,12,5,'yellow']],
 [[0,0,4,8,'photo'],[4,0,4,8,'photo'],[8,0,4,8,'photo'],[0,8,12,6,'box'],[0,14,8,10,'hero'],[8,14,4,5,'text'],[8,19,4,5,'text']],
 [[0,0,12,6,'yellow'],[0,6,5,9,'hero'],[5,6,7,9,'photo'],[0,15,12,4,'box'],[0,19,4,5,'strip'],[4,19,4,5,'strip'],[8,19,4,5,'strip']],
 [[0,0,7,8,'photo'],[7,0,5,8,'box'],[0,8,5,8,'box'],[5,8,7,8,'hero'],[0,16,12,4,'text'],[0,20,6,4,'strip'],[6,20,6,4,'strip']],
 [[0,0,12,8,'hero'],[0,8,4,6,'box'],[4,8,4,6,'yellow'],[8,8,4,6,'box'],[0,14,6,10,'photo'],[6,14,6,5,'text'],[6,19,6,5,'text']],
 [[0,0,5,7,'box'],[5,0,7,7,'hero'],[0,7,4,9,'photo'],[4,7,4,9,'photo'],[8,7,4,9,'photo'],[0,16,12,3,'yellow'],[0,19,6,5,'text'],[6,19,6,5,'text']],
];}
function sky_render_page($pdf,$cats,$items,$tpl,$W,$H,$M){
  $y0=$pdf->mastheadInner(); $cols=12; $rows=24; $gx=8; $gy=8; $gw=$W-2*$M; $gh=$H-40-$y0-40; $cw=($gw-$gx*($cols-1))/$cols; $rh=($gh-$gy*($rows-1))/$rows;
  /* kategori bandı */
  $y0+=6; $c=$pdf->catCol($cats[0]); $pdf->fill($c); $pdf->Rect($M,$y0,$gw,22,'F'); $pdf->SetFont('T','',14); $pdf->color([255,255,255]); $pdf->T($M+8,$y0+16,implode('  ·  ',array_map(fn($k)=>$pdf->catName($k),$cats))); $y0+=30;
  $gh=$H-40-$y0; $rh=($gh-$gy*($rows-1))/$rows; $pal=[$pdf->navy,$pdf->red,$pdf->ink,[0,120,110],[63,81,181]]; $k=0; $i=0;
  foreach($tpl as $slot){ [$c0,$r0,$cs,$rs,$type]=$slot; $n=$items[$i]??null; if(!$n) break; $i++;
    $x=$M+$c0*($cw+$gx); $y=$y0+$r0*($rh+$gy); $w=$cs*$cw+($cs-1)*$gx; $h=$rs*$rh+($rs-1)*$gy; $col=$pal[$k++%count($pal)];
    switch($type){
      case 'hero': $pdf->img($x,$y,$w,$h,$n); $bh=min($h*.55,max(70,$h*.45)); $pdf->fill([0,0,0]); $pdf->Rect($x,$y+$h-$bh,$w,$bh,'F'); $pdf->kicker($x+10,$y+$h-$bh+8,$pdf->catName($n['cat']),$pdf->yel,$pdf->ink,8); $pdf->head($x+10,$y+$h-$bh+26,$w-20,$bh-40,$n['t'],[255,255,255],$w>300?30:20,true,.98); if($bh>90){ $pdf->SetFont('B','',8); $pdf->color([230,230,230]); $pdf->T($x+10,$y+$h-8,mb_substr($n['s']??'',0,(int)($w/4.2)).'…'); } break;
      case 'photo': $ih=min($h*.55,$w*.7); $pdf->img($x,$y,$w,$ih,$n); $pdf->fill($col); $pdf->Rect($x,$y+$ih,$w,4,'F'); $yy=$pdf->head($x,$y+$ih+8,$w,min(60,($h-$ih)*.55),$n['t'],$pdf->ink,$w>200?18:14,true,1.0); if($h-($yy-$y)>30) $pdf->block($x,$yy+4,$w,$n['s']??'','B',8.2,$pdf->grey,1.28,max(1,(int)(($y+$h-$yy-6)/10.5))); break;
      case 'box': $pdf->fill($col); $pdf->Rect($x,$y,$w,$h,'F'); $pdf->kicker($x+10,$y+8,$pdf->catName($n['cat']),$pdf->yel,$pdf->ink,7.5); $yy=$pdf->head($x+10,$y+28,$w-20,min($h-40,$h*.6),$n['t'],[255,255,255],$w>300?26:18,true,.98); if($h>110) $pdf->block($x+10,$yy+6,$w-20,$n['s']??'','B',8.4,[225,232,245],1.28,max(1,(int)(($y+$h-$yy-14)/10.8))); break;
      case 'yellow': $pdf->fill($pdf->yel); $pdf->Rect($x,$y,$w,$h,'F'); $pdf->fill($pdf->red); $pdf->Rect($x,$y,6,$h,'F'); if($h<60){ $pdf->head($x+14,$y+6,$w-24,$h-12,$n['t'],$pdf->ink,20,true,1.0); } else { $yy=$pdf->head($x+14,$y+8,$w-24,min($h-20,$h*.6),$n['t'],$pdf->ink,$w>300?24:17,true,.98); $pdf->block($x+14,$yy+4,$w-24,$n['s']??'','B',8.4,[60,50,0],1.28,max(1,(int)(($y+$h-$yy-10)/10.8))); } break;
      case 'strip': $pdf->fill([241,241,241]); $pdf->Rect($x,$y,$w,$h,'F'); $iw=min($w*.32,$h*1.5); $pdf->img($x+6,$y+6,$iw,$h-12,$n); $yy=$pdf->head($x+$iw+16,$y+8,$w-$iw-24,min($h-16,$h*.6),$n['t'],$pdf->ink,$w>300?18:14,true,1.0); if($h>60) $pdf->block($x+$iw+16,$yy+3,$w-$iw-24,$n['s']??'','B',8,$pdf->grey,1.28,max(1,(int)(($y+$h-$yy-8)/10.2))); break;
      default: $pdf->fill($col); $pdf->Rect($x,$y,4,$h,'F'); $yy=$pdf->head($x+12,$y+2,$w-12,min($h*.5,70),$n['t'],$pdf->ink,$w>300?20:15,true,1.0); $pdf->block($x+12,$yy+4,$w-12,$n['s']??'','B',8.4,$pdf->grey,1.3,max(1,(int)(($y+$h-$yy-6)/10.9)));
    }
  }
  return $i;
}

function skyturk_gazete_build($force=false,$forDate=null){
  $root=dirname(__DIR__); $gdir=$root.'/gazete'; if(!is_dir($gdir)) @mkdir($gdir,0755,true);
  $issuesF=$gdir.'/issues.json'; $issues=file_exists($issuesF)?(json_decode(file_get_contents($issuesF),true)?:[]):[];
  $today=$forDate?:date('Y-m-d'); $tsDay=strtotime($today.' 09:00'); foreach($issues as $is) if(($is['date']??'')===$today&&!$force) return ['skipped'=>'bugünün sayısı var','issue'=>$is];
  $data=json_decode(@file_get_contents(__DIR__.'/data.json'),true)?:['news'=>[]];
  $all=array_values(array_filter($data['news'],fn($n)=>!empty($n['rw'])&&empty($n['video'])&&($n['st']??'')==='Yayında'));
  $ts=fn($n)=>$n['ts']??0; usort($all,fn($a,$b)=>$ts($b)<=>$ts($a));
  $win=array_values(array_filter($all,fn($n)=>$ts($n)>=time()-24*3600)); if(count($win)<40) $win=array_values(array_filter($all,fn($n)=>$ts($n)>=time()-48*3600)); if(count($win)<40) $win=$all;
  /* benzer başlıkları tekilleştir */
  $seenT=[]; $win=array_values(array_filter($win,function($n)use(&$seenT){ $k=mb_substr(preg_replace('/[^\p{L}\p{N}]+/u','',mb_strtolower($n['t'])),0,28); if(isset($seenT[$k])) return false; $seenT[$k]=1; return true; }));
  if(count($win)<12) return ['error'=>'yeterli haber yok ('.count($win).')'];
  $no=null; foreach($issues as $k=>$is){ if(($is['date']??'')===$today){ $no=$no===null?$is['no']:min($no,$is['no']); unset($issues[$k]); } } $issues=array_values($issues); if($no===null) $no=count($issues)+1; $months=['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık']; $days=['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
  $dateStr=date('j',$tsDay).' '.$months[(int)date('n',$tsDay)-1].' '.date('Y',$tsDay).' '.$days[(int)date('w',$tsDay)];
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
      $pdf->SetFont('SC','',8.5); $pdf->color([255,255,255]); foreach([[30,trUp($dateStr).' · SAYI '.$no],[44,'DİJİTAL GAZETE · ÜCRETSİZ'],[58,'testhabersitesimiz.site']] as [$yy,$s]) $pdf->T($W-$M-$pdf->GetStringWidth($s),$yy,$s); }
    else { $pdf->fill($pdf->navy); $pdf->Rect(0,0,$W,84,'F'); $pdf->fill($pdf->red); $pdf->Rect(0,78,$W,6,'F'); $pdf->SetFont('T','',60); $pdf->color([255,255,255]); $s='SKYTÜRK'; $pdf->T(($W-$pdf->GetStringWidth($s))/2,62,$s);
      $pdf->SetFont('SC','',8.5); $pdf->color([201,211,227]); $pdf->T($M,76,trUp($dateStr)); $s='SAYI '.$no.' · 10 SAYFA · testhabersitesimiz.site'; $pdf->T($W-$M-$pdf->GetStringWidth($s),76,$s); }
    $pdf->fill($pdf->ink); $pdf->Rect(0,84,$W,16,'F'); $pdf->SetFont('SC','',7.5); $pdf->color([255,255,255]); $pdf->T($M,95,'DOLAR 48,65   EURO 56,49   ALTIN 6.784   BİST 100 14.467   BİTCOİN 3.754.831'); $s='İSTANBUL 24° PARÇALI BULUTLU  ·  TRAFİK: 15 TEMMUZ KÖPRÜSÜ YOĞUN'; $pdf->T($W-$M-$pdf->GetStringWidth($s),95,$s); return 108; };
  $lead=$take(['surmanset','gundem','son-dakika','politika','genel'],1)[0]??$win[0]; if(!in_array($lead['id'],$used)) $used[]=$lead['id']; $pool=$take(['gundem','politika','dunya','ekonomi','spor','saglik','genel','teknoloji','egitim','kultur-sanat'],12); $fillTo($pool,12);
  $CN=fn($k)=>$pdf->catName($k); $PG=fn($k)=>['gundem'=>2,'genel'=>3,'politika'=>4,'dunya'=>5,'ekonomi'=>6,'spor'=>7,'saglik'=>8,'teknoloji'=>8,'egitim'=>9,'kultur-sanat'=>9,'magazin'=>9,'surmanset'=>2][$k]??2;
  $bottom3=function($y,$start)use($pdf,$pool,$W,$M,$CN,$PG){ $cw=($W-2*$M-20)/3; for($i=0;$i<3;$i++){ $n=$pool[$start+$i]??null; if(!$n) continue; $x=$M+$i*($cw+10); $pdf->fill([241,241,241]); $pdf->Rect($x,$y,$cw,70,'F'); $pdf->img($x+6,$y+6,64,58,$n); $pdf->head($x+78,$y+10,$cw-84,50,$n['t'],$pdf->ink,13,true,1.0); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x+78,$y+66,$CN($n['cat']).' · sayfa '.$PG($n['cat'])); } };
  if($rnd==0){ $y=$mast(0); $ph=300; $pdf->img($M,$y,$W-2*$M,$ph,$lead); $pdf->fill([0,0,0]); $pdf->Rect($M,$y+$ph-150,$W-2*$M,150,'F'); $pdf->kicker($M+12,$y+$ph-146,$CN($lead['cat']),$pdf->yel,$pdf->ink);
    $pdf->head($M+12,$y+$ph-124,$W-2*$M-24,100,$lead['t'],[255,255,255],44); $pdf->SetFont('B','',8.5); $pdf->color([255,255,255]); $pdf->T($M+12,$y+$ph-8,mb_substr($lead['s']??'',0,130).'…');
    $y+=$ph+14; $cw=($W-2*$M-20)/3; for($i=0;$i<3;$i++){ $n=$pool[$i]; $x=$M+$i*($cw+10); $pdf->img($x,$y,$cw,88,$n); $pdf->kicker($x,$y+92,$CN($n['cat']),[$pdf->red,$pdf->navy,$pdf->sky][$i],[255,255,255]); $yy=$pdf->head($x,$y+112,$cw,52,$n['t'],$pdf->ink,17,true,1.0); $yy=$pdf->block($x,$yy+4,$cw,$n['s']??'','B',8.2,$pdf->grey,1.28,3); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x,$y+218,$CN($n['cat']).' · sayfa '.$PG($n['cat'])); }
    $y+=232; $pdf->fill($pdf->navy); $pdf->Rect($M,$y,$W-2*$M,96,'F'); $n=$pool[3]; $pdf->img($M+8,$y+8,150,80,$n); $pdf->kicker($M+170,$y+10,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($M+170,$y+30,$W-2*$M-190,44,$n['t'],[255,255,255],22); $pdf->SetFont('B','',8.3); $pdf->color([221,230,245]); $pdf->T($M+170,$y+88,mb_substr($n['s']??'',0,100).'…');
    $y+=110; $cw2=($W-2*$M-10)/2; for($i=0;$i<2;$i++){ $n=$pool[4+$i]; $x=$M+$i*($cw2+10); $pdf->fill([241,241,241]); $pdf->Rect($x,$y,$cw2,58,'F'); $pdf->img($x+6,$y+6,70,46,$n); $pdf->head($x+84,$y+8,$cw2-92,40,$n['t'],$pdf->ink,15,true,1.0); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x+84,$y+54,'sayfa '.$PG($n['cat'])); }
    $y+=72; $bottom3($y,6); }
  elseif($rnd==1){ $y=$mast(1); $lw=($W-2*$M)*.5; $pdf->fill($pdf->red); $pdf->Rect($M,$y,$lw,250,'F'); $pdf->kicker($M+12,$y+10,'MANŞET',$pdf->ink,$pdf->yel); $pdf->head($M+12,$y+34,$lw-24,150,$lead['t'],[255,255,255],50,true,.95); $pdf->block($M+12,$y+196,$lw-24,$lead['s']??'','B',9,[255,255,255],1.3,3);
    $pdf->img($M+$lw,$y,$W-$M-($M+$lw),250,$lead); $pdf->fill($pdf->red); $pdf->Rect($M+$lw,$y+234,$W-$M-($M+$lw),16,'F'); $pdf->SetFont('SC','',7.5); $pdf->color([255,255,255]); $pdf->T($M+$lw+6,$y+245,'FOTOĞRAF: TEMSİLİ · '.$CN($lead['cat']));
    $y+=262; $cw=($W-2*$M-24)/4; for($i=0;$i<4;$i++){ $n=$pool[$i]; $x=$M+$i*($cw+8); $pdf->img($x,$y,$cw,100,$n); $pdf->fill([$pdf->navy,$pdf->red,$pdf->ink,$pdf->sky][$i]); $pdf->Rect($x,$y+100,$cw,50,'F'); $pdf->head($x+5,$y+104,$cw-10,42,$n['t'],[255,255,255],14,true,1.0); }
    $y+=164; $pdf->fill($pdf->ink); $pdf->Rect($M,$y,$W-2*$M,2,'F'); $y+=14; $cw2=($W-2*$M-10)/2; $n=$pool[4]; $pdf->kicker($M,$y,$CN($n['cat']),$pdf->navy,[255,255,255]); $yy=$pdf->head($M,$y+20,$cw2,52,$n['t'],$pdf->ink,22); $pdf->block($M,$yy+4,$cw2,$n['s']??'','B',8.4,$pdf->grey,1.28,4);
    $n=$pool[5]; $x=$M+$cw2+10; $pdf->img($x,$y,$cw2,100,$n); $pdf->fill([0,0,0]); $pdf->Rect($x,$y+56,$cw2,44,'F'); $pdf->head($x+6,$y+60,$cw2-12,36,$n['t'],[255,255,255],15);
    $y+=126; $bottom3($y,6); $y+=84; $n=$pool[9]??$pool[0]; $pdf->fill($pdf->navy); $pdf->Rect($M,$y,$W-2*$M,56,'F'); $pdf->kicker($M+10,$y+6,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($M+10,$y+22,$W-2*$M-20,32,$n['t'],[255,255,255],20); }
  else { $y=$mast(0); $g=10; $cw=($W-2*$M-$g)/2; $pdf->img($M,$y,$cw,230,$lead); $pdf->fill($pdf->yel); $pdf->Rect($M,$y+160,$cw,70,'F'); $pdf->head($M+8,$y+164,$cw-16,62,$lead['t'],$pdf->ink,24,true,.98); $pdf->kicker($M+8,$y+8,'GÜNÜN HABERİ',$pdf->red,[255,255,255]);
    $n=$pool[0]; $x=$M+$cw+$g; $pdf->fill($pdf->navy); $pdf->Rect($x,$y,$cw,230,'F'); $pdf->img($x+8,$y+14,$cw-16,104,$n); $pdf->kicker($x+8,$y+122,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($x+8,$y+140,$cw-16,60,$n['t'],[255,255,255],22); $pdf->block($x+8,$y+204,$cw-16,$n['s']??'','B',8.2,[221,230,245],1.28,1);
    $y+=244; $cw4=($W-2*$M-3*$g)/4; for($i=0;$i<4;$i++){ $n=$pool[1+$i]; $x=$M+$i*($cw4+$g); $pdf->img($x,$y,$cw4,84,$n); $pdf->fill([$pdf->red,$pdf->ink,$pdf->sky,$pdf->navy][$i]); $pdf->Rect($x,$y+70,$cw4,14,'F'); $pdf->SetFont('TB','',7); $pdf->color([255,255,255]); $pdf->T($x+4,$y+80,$CN($n['cat'])); $yy=$pdf->head($x,$y+92,$cw4,46,$n['t'],$pdf->ink,13,true,1.0); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x,$yy+10,'sayfa '.$PG($n['cat'])); }
    $y+=160; $pdf->fill($pdf->red); $pdf->Rect($M,$y,$W-2*$M,64,'F'); $n=$pool[5]; $pdf->kicker($M+10,$y+6,$CN($n['cat']),$pdf->yel,$pdf->ink); $pdf->head($M+10,$y+24,$W-2*$M-20,36,$n['t'],[255,255,255],22);
    $y+=78; $n=$pool[6]; $pdf->fill([238,238,238]); $pdf->Rect($M,$y,$W-2*$M,52,'F'); $pdf->img($M+6,$y+6,60,40,$n); $pdf->head($M+74,$y+6,$W-2*$M-90,40,$n['t'],$pdf->ink,16,true,1.0);
    $y+=66; $bottom3($y,7); $y+=84; $n=$pool[10]??$pool[1]; $pdf->kicker($M,$y,$CN($n['cat']),$pdf->red,[255,255,255]); $yy=$pdf->head($M,$y+20,$W-2*$M,40,$n['t'],$pdf->ink,20); $pdf->block($M,$yy+4,$W-2*$M,$n['s']??'','B',8.4,$pdf->grey,1.28,2); }
  /* son dakika şeridi */
  $pdf->fill($pdf->red); $pdf->Rect(0,$H-20,$W,20,'F'); $pdf->SetFont('TB','',8); $pdf->color([255,255,255]); $pdf->T($M,$H-7,'SON DAKİKA'); $pdf->SetFont('B','',8); $sd=array_slice($win,0,3); $pdf->T($M+66,$H-7,implode('   ',array_map(fn($n)=>'▸ '.mb_substr($n['t'],0,52),$sd)));
  $pdf->SetFont('SC','',6.5); $pdf->color($pdf->grey); $s='Fotoğraflar temsilidir · Kaynaklar künyede · Sayfa 1/10'; $pdf->T($W-$M-$pdf->GetStringWidth($s),$H-24,$s);
  /* ---------- İÇ SAYFALAR ---------- */
  $used=[$lead['id']]; // kapaktakiler iç sayfada tekrar kullanılabilir (gazete mantığı: kapak = özet)
  $pageCats=[[2,['surmanset','gundem','son-dakika']],[3,['gundem','son-dakika','genel','istanbul','ankara']],[4,['politika']],[5,['dunya']],[6,['ekonomi']],[7,['spor']],[8,['saglik','teknoloji']],[9,['magazin','kultur-sanat','egitim','yasam']]];
  $tpls=sky_templates(); $order=range(0,count($tpls)-1); shuffle($order); $usedT=[];
  $stand=[['Galatasaray',5,13],['Fenerbahçe',5,11],['Beşiktaş',5,10],['Trabzonspor',5,9],['Konyaspor',5,8],['Göztepe',5,8],['Kocaelispor',5,7],['Gençlerbirliği',5,6]];
  foreach($pageCats as $pi=>[$pn,$cats]){
    $pdf->AddPage(); $pdf->pageNo=$pn; $ti=$order[$pi]; $usedT[]=$ti; $tpl=$tpls[$ti]; $need=count($tpl);
    $items=$take($cats,$need); $fillTo($items,$need); if(count($items)<$need){ foreach($win as $n){ if(count($items)>=$need) break; if(!in_array($n['id'],array_column($items,'id'))) $items[]=$n; } } sky_render_page($pdf,$cats,$items,$tpl,$W,$H,$M); $pdf->footer2();
  }
  /* 10. sayfa: arka */
  { $pdf->AddPage(); $pdf->pageNo=10; $y=$pdf->mastheadInner();
    $astro=$data['astro']['items']??[]; $signs=[['koc','♈','Koç'],['boga','♉','Boğa'],['ikizler','♊','İkizler'],['yengec','♋','Yengeç'],['aslan','♌','Aslan'],['basak','♍','Başak'],['terazi','♎','Terazi'],['akrep','♏','Akrep'],['yay','♐','Yay'],['oglak','♑','Oğlak'],['kova','♒','Kova'],['balik','♓','Balık']];
      $pdf->fill([63,81,181]); $pdf->Rect($M,$y,4,18,'F'); $pdf->SetFont('H','',15); $pdf->color($pdf->ink); $pdf->T($M+10,$y+14,'GÜNLÜK BURÇ YORUMLARI'); $pdf->rule($M,$W-$M,$y+22); $y+=36; $cw=($W-2*$M-24)/3; $ch=92;
      foreach($signs as $i=>[$id,$sym,$name]){ $x=$M+($i%3)*($cw+12); $yy=$y+intdiv($i,3)*($ch+10); $pdf->fill($pdf->soft); $pdf->Rect($x,$yy,$cw,$ch,'F'); $pdf->SetFont('B','',20); $pdf->color($pdf->navy); $pdf->T($x+10,$yy+26,$sym); $pdf->SetFont('H','',11); $pdf->color($pdf->ink); $pdf->T($x+36,$yy+22,trUp($name));
        $it=$astro[$id]??null; $txt=$it['genel']??'Bugünün yorumu sitede: testhabersitesimiz.site/#/astroloji'; $pdf->block($x+10,$yy+32,$cw-20,$txt,'B',7.6,$pdf->grey,1.32,4); $pdf->SetFont('SC','',7); $pdf->color($pdf->sky); $pdf->T($x+10,$yy+$ch-8,$it?('Şanslı sayı '.($it['sansli_sayi']??'').' · Renk: '.($it['renk']??'')):''); }
      $yb=$y+4*($ch+10)+14; $pdf->rule($M,$W-$M,$yb,$pdf->navy,1.2); $yb+=14; $hw3=($W-2*$M-28)/3; $bh=150;
      /* HAVA */ $x=$M; $pdf->fill($pdf->soft); $pdf->Rect($x,$yb,$hw3,$bh,'F'); $pdf->SetFont('H','',10.5); $pdf->color($pdf->ink); $pdf->T($x+12,$yb+20,'HAVA · İSTANBUL'); $pdf->rule($x+12,$x+$hw3-12,$yb+26,$pdf->navy,1.2);
      $pdf->SetFont('H','',34); $pdf->T($x+12,$yb+66,'24°'); $pdf->SetFont('B','',9); $pdf->color($pdf->grey); $pdf->T($x+82,$yb+52,'Parçalı bulutlu'); $pdf->T($x+82,$yb+66,'14 Eylül'); $yy=$yb+92; foreach([['Pazartesi','23°','Sağanak'],['Salı','22°','Yağmurlu'],['Çarşamba','25°','Az bulutlu'],['Perşembe','26°','Güneşli']] as $r){ $pdf->SetFont('TB','',8); $pdf->color($pdf->ink); $pdf->T($x+12,$yy,$r[0]); $pdf->SetFont('B','',8); $pdf->color($pdf->grey); $pdf->T($x+80,$yy,$r[1].'  '.$r[2]); $yy+=13; }
      /* ANKET */ $x=$M+$hw3+14; $pdf->fill($pdf->soft); $pdf->Rect($x,$yb,$hw3,$bh,'F'); $pdf->SetFont('H','',10.5); $pdf->color($pdf->ink); $pdf->T($x+12,$yb+20,'GÜNÜN ANKETİ'); $pdf->rule($x+12,$x+$hw3-12,$yb+26,$pdf->navy,1.2);
      $poll=$data['polls'][0]??null; if($poll){ $yy=$pdf->block($x+12,$yb+32,$hw3-24,$poll['q'],'TB',8.5,$pdf->ink,1.25,2)+6; $tot=array_sum(array_map(fn($o)=>is_numeric($o[1]??null)?(int)$o[1]:0,$poll['o'])); foreach(array_slice($poll['o'],0,4) as $o){ $v=is_numeric($o[1]??null)?(int)$o[1]:0; $pct=$tot>0?(int)round($v*100/$tot):0; $pdf->SetFont('SC','',7.8); $pdf->color($pdf->grey); $pdf->T($x+12,$yy+7,$o[0]); $s=$pct.'%'; $pdf->T($x+$hw3-12-$pdf->GetStringWidth($s),$yy+7,$s); $pdf->fill([227,232,239]); $pdf->Rect($x+12,$yy+10,$hw3-24,6,'F'); $pdf->fill($pdf->sky); $pdf->Rect($x+12,$yy+10,($hw3-24)*$pct/100,6,'F'); $yy+=22; } $pdf->SetFont('SC','',7); $pdf->color($pdf->grey); $pdf->T($x+12,$yb+$bh-10,'Oy: testhabersitesimiz.site/#/anketler'); } else { $pdf->SetFont('B','',8.5); $pdf->color($pdf->grey); $pdf->T($x+12,$yb+46,'Bugün anket yok.'); }
      /* KÜNYE */ $x=$M+2*($hw3+14); $pdf->fill($pdf->soft); $pdf->Rect($x,$yb,$hw3,$bh,'F'); $pdf->SetFont('H','',10.5); $pdf->color($pdf->ink); $pdf->T($x+12,$yb+20,'KÜNYE'); $pdf->rule($x+12,$x+$hw3-12,$yb+26,$pdf->navy,1.2);
      $srcs=implode(', ',array_unique(array_filter(array_map(fn($n)=>$n['srcName']??$n['by']??'',$win)))); $pdf->block($x+12,$yb+32,$hw3-24,"SKYTÜRK Medya Yayıncılık · Dijital Yayın: SKYTÜRK CMS · İletişim: info@skyturk.com.tr\nHer sabah 09:00'da son 24 saatin haberlerinden otomatik derlenir.\nKaynaklar: ".$srcs.". Fotoğraflar temsilidir (Pexels).",'B',7.8,$pdf->grey,1.32,9);
      /* GÜNÜN FOTOĞRAFI */ $yp=$yb+$bh+14; $ph=$H-40-$yp-6; if($ph>70){ $pn=null; foreach($win as $n){ if(!empty($n['imgUrl'])&&$n['id']!==$lead['id']){ $pn=$n; break; } } if($pn){ $pw=($W-2*$M)*.62; $pdf->img($M,$yp,$pw,$ph,$pn); $pdf->fill($pdf->navy); $pdf->Rect($M+$pw,$yp,$W-2*$M-$pw,$ph,'F'); $pdf->kicker($M+$pw+10,$yp+8,'GÜNÜN FOTOĞRAFI',$pdf->yel,$pdf->ink,7.5); $pdf->head($M+$pw+10,$yp+28,$W-2*$M-$pw-20,$ph-40,$pn['t'],[255,255,255],16,true,1.0); } }
    $pdf->footer2();
  }
  $fn='sayi-'.$no.'-'.$today.'.pdf'; $pdf->Output('F',$gdir.'/'.$fn); foreach(glob($gdir.'/sayi-*-'.$today.'.pdf') as $old) if(basename($old)!==$fn) @unlink($old);
  $cover='gazete/sayi-'.$no.'-'.$today.'.jpg'; $coverOk=false;
  try{ if(class_exists('Imagick')){ $im=new Imagick(); $im->setResolution(110,110); $im->readImage($gdir.'/'.$fn.'[0]'); $im->setImageBackgroundColor('white'); $im=$im->flattenImages(); $im->setImageFormat('jpeg'); $im->setImageCompressionQuality(85); $im->writeImage($root.'/'.$cover); $coverOk=true; } }catch(Throwable $e){}
  if(!$coverOk&&function_exists('imagecreatetruecolor')){ $coverOk=sky_cover_gd($root.'/'.$cover,$lead,$pool,$pdf,$no,$dateStr,$rnd); }
  $issue=['no'=>$no,'date'=>$today,'cover'=>$coverOk?$cover:null,'dateStr'=>$dateStr,'file'=>'gazete/'.$fn,'pages'=>10,'lead'=>$lead['t'],'leadImg'=>$lead['imgUrl']??null,'count'=>count($win),'layout'=>$rnd,'templates'=>$usedT,'created'=>date('c')];
  array_unshift($issues,$issue); file_put_contents($issuesF,json_encode($issues,JSON_UNESCAPED_UNICODE),LOCK_EX);
  return ['ok'=>true,'issue'=>$issue];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__){ $fd=null; foreach($argv as $a) if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$a)) $fd=$a; echo json_encode(skyturk_gazete_build(in_array('--force',$argv),$fd),JSON_UNESCAPED_UNICODE)."\n"; }
