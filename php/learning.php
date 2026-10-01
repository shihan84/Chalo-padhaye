<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

const CURRICULA=['maharashtra','nios','cbse','telangana'];
const RECORD_TYPES=['assignment','reading','project','field_trip','physical','art','life_skill','other'];
const RECORD_STATUS=['planned','completed'];

function query_param(string $key, ?string $default=null): ?string { return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default; }
function valid_date(?string $v): bool { if(!$v)return false; $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); return $d && $d->format('Y-m-d')===$v; }
function student_progress_rows(string $id): array { $q=db()->prepare('SELECT id,subject,chapter,topic,score,attempts,status,updated_at FROM student_progress WHERE student_id=? ORDER BY updated_at DESC LIMIT 100');$q->execute([$id]);return $q->fetchAll(); }
function student_sessions(string $id): array { $q=db()->prepare('SELECT id,subject,chapter,started_at,ended_at FROM tutor_sessions WHERE student_id=? ORDER BY started_at DESC LIMIT 60');$q->execute([$id]);return $q->fetchAll(); }
function student_records(string $id,int $limit=80): array { $limit=max(1,min(200,$limit));$q=db()->prepare("SELECT id,record_type,title,curriculum,subject,notes,minutes,status,occurred_on,due_date,created_at,updated_at FROM homeschool_records WHERE student_id=? ORDER BY created_at DESC LIMIT {$limit}");$q->execute([$id]);return $q->fetchAll(); }
function level_label(array $s,string $c): string { $g=(int)$s['grade']; if($c==='nios')return $g===3?'NIOS Level A':($g===5?'NIOS Level B':"NIOS / Grade {$g}"); if($c==='cbse')return "CBSE Class {$g}"; if($c==='telangana')return $g===10?'Telangana SSC Class 10':"Telangana State Board Class {$g}"; return "Grade {$g}"; }
function subject_groups(array $progress,string $curriculum): array {
    $out=[]; foreach($progress as $r){$raw=(string)($r['subject']??'');$p=strpos($raw,':');if($p===false){$c='maharashtra';$sid=$raw;}else{$c=substr($raw,0,$p);$sid=substr($raw,$p+1);}if($c===$curriculum)$out[$sid][]=$r;} return $out;
}
function week_metrics(array $sessions,array $records): array {
    $cutoff=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-7 days');$active=[];$subjects=[];$sm=0;$ws=0;
    foreach($sessions as $r){$start=new DateTimeImmutable($r['started_at'],new DateTimeZone('UTC'));if($start<$cutoff)continue;$ws++;$active[$start->format('Y-m-d')]=1;if($r['subject'])$subjects[$r['subject']]=1;if($r['ended_at']){$end=new DateTimeImmutable($r['ended_at'],new DateTimeZone('UTC'));$sm+=max(0,min(180,(int)round(($end->getTimestamp()-$start->getTimestamp())/60)));}}
    $om=0;$items=0;foreach($records as $r){if($r['status']!=='completed')continue;$day=$r['occurred_on']?:substr($r['created_at'],0,10);if($day<$cutoff->format('Y-m-d'))continue;$items++;$om+=max(0,min(600,(int)$r['minutes']));$active[$day]=1;if($r['subject'])$subjects['offline:'.$r['subject']]=1;}
    $today=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d');$cursor=isset($active[$today])?new DateTimeImmutable($today):new DateTimeImmutable('yesterday');$streak=0;while(isset($active[$cursor->format('Y-m-d')])){$streak++;$cursor=$cursor->modify('-1 day');}
    return ['sessions'=>$ws,'study_minutes'=>$sm,'offline_minutes'=>$om,'total_minutes'=>$sm+$om,'active_days'=>count($active),'subjects'=>count($subjects),'portfolio_items'=>$items,'streak_days'=>$streak];
}
function curriculum_catalog(): array {
    static $catalog=null;if($catalog!==null)return $catalog;
    $plan=json_decode((string)@file_get_contents(dirname(__DIR__).'/data/lesson_plans.json'),true)?:[];
    $out=[];foreach(($plan['curricula']??[]) as $c=>$grades){foreach($grades as $grade=>$subjects){foreach($subjects as $sid=>$lessons){$out[$c][(string)$grade][]= ['id'=>$sid,'label'=>ucwords(str_replace('_',' ',$sid)),'available'=>true,'lesson_count'=>is_array($lessons)?count($lessons):0];}}}
    return $catalog=$out;
}
function roadmap_data(array $student,string $curriculum,array $progress): array {
    $groups=subject_groups($progress,$curriculum);$items=curriculum_catalog()[$curriculum][(string)$student['grade']]??[];$out=[];
    foreach($items as $item){$rows=$groups[$item['id']]??[];$scores=array_map(fn($x)=>(int)($x['score']??0),$rows);$mastery=$scores?(int)round(array_sum($scores)/count($scores)):0;$attempts=array_sum(array_map(fn($x)=>(int)$x['attempts'],$rows));usort($rows,fn($a,$b)=>(int)$a['score']<=>(int)$b['score']);
      if(!$rows){$act='teach';$reason='Start this subject';}elseif($mastery<45){$act='revision';$reason='Review weak topics';}elseif($mastery<80){$act='practice';$reason='Keep practising';}else{$act='quiz';$reason='Check retention';}
      $item['mastery']=$mastery;$item['attempts']=$attempts;$item['topics']=count($rows);$item['weak_topics']=array_map(fn($x)=>['topic'=>$x['topic'],'score'=>(int)$x['score']],array_slice($rows,0,3));$item['recommended_activity']=$act;$item['recommendation']=$reason;$out[]=$item;
    } return $out;
}
function daily_plan_data(array $student,string $curriculum,array $progress): array {
    $grade=(int)$student['grade'];$groups=subject_groups($progress,$curriculum);$subjects=curriculum_catalog()[$curriculum][(string)$grade]??[];$items=[];
    foreach($subjects as $item){$rows=$groups[$item['id']]??[];$avg=$rows?(int)round(array_sum(array_map(fn($x)=>(int)$x['score'],$rows))/count($rows)):null;if($avg===null){$act='teach';$reason='New learning block';}elseif($avg<45){$act='revision';$reason='Needs revision';}elseif($avg<80){$act='practice';$reason='Build confidence';}else{$act='quiz';$reason='Quick mastery check';}$items[]=['kind'=>'lesson','subject'=>$item['id'],'title'=>$item['label'],'minutes'=>$grade>=6?25:20,'activity'=>$act,'reason'=>$reason,'supplemental'=>false,'priority'=>$avg??101];}
    usort($items,fn($a,$b)=>$a['priority']<=>$b['priority']);foreach($items as &$i)unset($i['priority']);
    if($curriculum==='nios'){$items[]=['kind'=>'offline_suggestion','record_type'=>'reading','title'=>'Independent reading / read aloud','minutes'=>$grade<=3?15:20,'reason'=>'Daily reading habit'];$items[]=['kind'=>'offline_suggestion','record_type'=>'physical','title'=>'Movement / outdoor play','minutes'=>$grade<=3?20:25,'reason'=>'Daily physical activity'];}return $items;
}
