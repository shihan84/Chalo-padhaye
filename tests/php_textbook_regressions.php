<?php
declare(strict_types=1);
require dirname(__DIR__).'/php/tutor.php';
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
$q=textbook_question('A triangle has three sides. A rectangle has four corners. We can identify geometric shapes around us.');check($q!==null,'Find a reading sentence');
$answer=$q['answer'];$first=textbook_grade($q,'wrong');check(!$first['complete']&&str_contains($first['text'],'Hint:'),'First attempt gives hint');check(!str_contains($first['text'],'“'.$q['word'].'”'),'Hint hides word');
$correct=textbook_grade($q,' '.strtoupper($answer).' ');check($correct['complete']&&$correct['assessment']==='correct','Normalise exact word');
$q=textbook_question('A rectangle has four corners.');check($q===null,'Short ambiguous sentence excluded');
$q=textbook_question('Computers are useful electronic machines.');$one=textbook_grade($q,'incorrect');$two=textbook_grade($q,'incorrect');check($two['complete']&&str_contains($two['text'],$q['word']),'Second wrong attempt explains');
check(textbook_question('Copyright reserved; permission required for reproduction.')===null,'Exclude front matter');
check(textbook_find('../../etc/passwd')===null,'Reject arbitrary paths');
foreach([1=>'A',2=>'A',3=>'A',4=>'B',5=>'B',6=>'C',8=>'C'] as $grade=>$level)check(level_label(['grade'=>$grade],'nios')==='NIOS Level '.$level,'NIOS mapping');
check(level_label(['grade'=>9],'nios')==='NIOS Secondary','Secondary mapping');check(level_label(['grade'=>11],'nios')==='NIOS Senior Secondary','Senior mapping');
echo "Grounded reading checks, hints, exact answer validation, source exclusions and NIOS mappings passed\n";
