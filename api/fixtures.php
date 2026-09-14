<?php
/* SKYTÜRK — fikstür/skor (TheSportsDB açık API) → fixtures.json */
function skyturk_fixtures(){
  $leagues=[['4339','TSL','Türkiye Süper Ligi'],['4480','UCL','UEFA Şampiyonlar Ligi'],['4481','UEL','UEFA Avrupa Ligi'],['4328','EPL','İngiltere Premier Ligi'],['4335','LAL','La Liga'],['4332','SEA','Serie A'],['4331','BUN','Bundesliga'],['4334','LI1','Fransa Ligue 1']];
  $abbr=['Fenerbahce'=>'FB','Fenerbahçe'=>'FB','Galatasaray'=>'GS','Besiktas'=>'BJK','Beşiktaş'=>'BJK','Trabzonspor'=>'TS','Basaksehir'=>'IBFK','Istanbul Basaksehir'=>'IBFK','Kasimpasa'=>'KAS','Konyaspor'=>'KON','Kayserispor'=>'KAY','Gaziantep FK'=>'GAZ','Antalyaspor'=>'ANT','Alanyaspor'=>'ALA','Sivasspor'=>'SIV','Rizespor'=>'RIZ','Samsunspor'=>'SAM','Goztepe'=>'GÖZ','Eyupspor'=>'EYÜ','Kocaelispor'=>'KOC','Genclerbirligi'=>'GB','Karagumruk'=>'KRG','Manchester United'=>'MUN','Manchester City'=>'MCI','Liverpool'=>'LIV','Arsenal'=>'ARS','Chelsea'=>'CHE','Tottenham'=>'TOT','Newcastle'=>'NEW','Real Madrid'=>'RMA','Barcelona'=>'BAR','Atletico Madrid'=>'ATM','Juventus'=>'JUV','Inter'=>'INT','AC Milan'=>'MIL','Napoli'=>'NAP','Roma'=>'ROM','Bayern Munich'=>'FCB','Borussia Dortmund'=>'BVB','Paris SG'=>'PSG','Paris Saint-Germain'=>'PSG'];
  $ab=function($n)use($abbr){ foreach($abbr as $k=>$v) if(stripos($n,$k)!==false) return $v; $w=preg_split('/\s+/',trim($n)); $s=count($w)>1?strtoupper(mb_substr($w[0],0,1).mb_substr($w[1],0,1).mb_substr($w[count($w)-1],0,1)):strtoupper(mb_substr($n,0,3)); return mb_strtoupper(str_replace(['i','ı'],['İ','I'],$s)); };
  $get=function($u){ $ch=curl_init($u); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'SKYTURK/1.0']); $r=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return $c===200?json_decode($r,true):null; };
  $out=[]; $err=[]; $tz=new DateTimeZone('Europe/Istanbul');
  foreach($leagues as [$id,$code,$name]){
    foreach(['eventsnextleague','eventspastleague'] as $ep){
      $j=$get("https://www.thesportsdb.com/api/v1/json/3/$ep.php?id=$id"); if(!$j){ $err[]="$code:$ep"; continue; }
      foreach(($j['events']??[]) as $e){
        $ts=!empty($e['strTimestamp'])?strtotime($e['strTimestamp'].' UTC'):(!empty($e['dateEvent'])?strtotime($e['dateEvent'].' '.($e['strTime']??'00:00:00').' UTC'):0); if(!$ts) continue;
        if($ts<time()-3*86400||$ts>time()+10*86400) continue;
        $d=(new DateTime('@'.$ts))->setTimezone($tz);
        $out[$e['idEvent']]=['id'=>$e['idEvent'],'league'=>$code,'leagueName'=>$name,'ts'=>$ts,'date'=>$d->format('d.m.Y'),'time'=>$d->format('H:i'),'home'=>$e['strHomeTeam']??'','away'=>$e['strAwayTeam']??'','h'=>$ab($e['strHomeTeam']??''),'a'=>$ab($e['strAwayTeam']??''),'hs'=>$e['intHomeScore'],'as'=>$e['intAwayScore'],'status'=>$e['strStatus']??'','round'=>$e['intRound']??null,'hb'=>!empty($e['strHomeTeamBadge'])?$e['strHomeTeamBadge'].'/small':null,'ab'=>!empty($e['strAwayTeamBadge'])?$e['strAwayTeamBadge'].'/small':null];
      }
    }
  }
  $out=array_values($out); usort($out,fn($a,$b)=>$a['ts']<=>$b['ts']);
  $res=['updated'=>date('c'),'leagues'=>array_map(fn($l)=>['code'=>$l[1],'name'=>$l[2]],$leagues),'count'=>count($out),'errors'=>$err,'matches'=>$out];
  file_put_contents(__DIR__.'/fixtures.json',json_encode($res,JSON_UNESCAPED_UNICODE),LOCK_EX); return ['count'=>count($out),'errors'=>$err];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__) echo json_encode(skyturk_fixtures(),JSON_UNESCAPED_UNICODE)."\n";
