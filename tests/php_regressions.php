<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/php/tutor.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
check(valid_date('2024-02-29'),'Leap date should be valid');
check(!valid_date('2026-02-29'),'Invalid date must be rejected');
check(!valid_date('2026-13-01'),'Invalid month must be rejected');
$defs=lesson_defs('maharashtra',3,'math');
check(count($defs)>1,'Lesson definitions should load');
check(can_open(1,$defs,[]),'First lesson should open');
check(!can_open(2,$defs,[$defs[0]['id']=>['status'=>'parent_unlocked']]),'Unlock does not imply mastery');
check(can_open(2,$defs,[$defs[0]['id']=>['status'=>'mastered']]),'Mastery should unlock next lesson');
$future=gmdate('Y-m-d',time()+86400);
$metrics=week_metrics([], [['status'=>'completed','occurred_on'=>$future,'minutes'=>60,'subject'=>'math']]);
check($metrics['total_minutes']===0,'Future records must not count as study');
$answer=groq_tutor(['full_name'=>'Test Child','grade'=>3], ['message'=>'Invent a textbook lesson']);
check($answer['assessment']==='not_answer','Ungrounded response must not update mastery');
check($answer['error']==='textbook_retrieval_unavailable','Missing retrieval must be explicit');
check($answer['interaction']['type']==='none','No fabricated learner question');
check(curriculum_catalog()['maharashtra']['3'][0]['available']===false,'Catalog must not claim retrieval availability');
$u=uuid_v4();check((bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$u),'UUID must be RFC 4122 v4');
echo "PHP helper regressions passed\n";
