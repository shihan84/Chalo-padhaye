<?php
declare(strict_types=1);
require_once __DIR__.'/private_state.php';
function maths_question(string $skill,int $grade): array {
    $a=random_int(2,9);$b=random_int(2,9);$n=random_int(2,5);$answer=0;$choices=[];$visual=null;
    $concept='';$hint='';$worked='';$prompt='';$type='number';
    switch($skill){
    case 'solid_name':
        $shapes=['ball'=>['sphere','A sphere has a curved surface.'],'dice'=>['cube','A cube has six equal square faces.'],'tin can'=>['cylinder','A cylinder has two flat circular faces and a curved surface.']];
        $object=array_rand($shapes);[$answer,$concept]=$shapes[$object];$prompt="Which solid shape resembles a $object?";$choices=['sphere','cube','cylinder'];$hint='Imagine the flat faces and curved surface of the object.';break;
    case 'solid_faces':
        $solids=['cube'=>6,'cuboid'=>6,'cylinder'=>2,'cone'=>1,'sphere'=>0];$solid=array_rand($solids);$answer=$solids[$solid];$prompt="How many flat faces does a $solid have?";$concept='A flat face is a flat surface of a solid. Curved surfaces are not counted as flat faces.';$hint='Imagine the solid. Count only the flat surfaces, not curved surfaces.';break;
    case 'place':
        $power=random_int(1,$grade===3?2:4);$base=10**$power;$digit=random_int(1,9);$number=random_int(1,9)*($base*10)+$digit*$base+random_int(0,$base-1);$answer=$digit*$base;
        $prompt="What is the value of the digit $digit in the ".['','tens','hundreds','thousands','ten-thousands'][$power]." place of $number?";$concept='The position of a digit determines its value. In 352, the 5 is worth 50.';$hint="Multiply the digit by $base.";break;
    case 'compare':
        $a=random_int(10,$grade===3?999:99999);$b=random_int(10,$grade===3?999:99999);$answer=$a===$b?'=':($a<$b?'<':'>');$choices=['<','=','>'];$prompt="Choose the sign: $a __ $b.";$concept='Compare the number of digits first. When equal, compare from the leftmost digit.';$hint='Which number is smaller, or are they equal?';break;
    case 'add':case 'sub':
        $a=random_int(20,$grade===3?499:49999);$b=random_int(11,$grade===3?399:39999);if($skill==='sub'&&$a<$b)[$a,$b]=[$b,$a];$op=$skill==='add'?'+':'−';$answer=$skill==='add'?$a+$b:$a-$b;$prompt="What is $a $op $b?";$concept='Line up the place-value columns. Work from ones to tens and onwards. Exchange a ten when needed.';$hint=$skill==='add'?'Add the ones first, then carry if you have ten or more.':'Start with the ones. Exchange one ten for ten ones if needed.';break;
    case 'multiply':
        $x=$grade===3?$a:random_int(101,999);if($grade===5)$b=random_int(101,399);$answer=$x*$b;$prompt="There are $b equal groups of $x counters. How many counters altogether?";$concept='Multiplication counts equal groups. Three groups of four make 12.';$hint="Add $x a total of $b times, or use multiplication.";break;
    case 'divide':
        $x=$grade===3?$a:random_int(12,99);if($grade===5)$b=random_int(11,29);$total=$x*$b;$answer=$x;$prompt="$total counters are shared equally among $b children. How many does each child get?";$concept='Division shares a total into equal groups.';$hint="Find the number which multiplied by $b gives $total.";break;
    case 'fraction':case 'fraction_add':
        $d=random_int(3,10);$top=random_int(1,$d-1);$other=random_int(1,$d-$top);$type='rational';$answer=[$skill==='fraction'?$top:$top+$other,$d];
        $prompt=$skill==='fraction'?"$top of $d equal parts are shaded. What fraction is shaded?":"What is $top/$d + $other/$d?";
        $visual=['kind'=>'fraction','n'=>$top,'d'=>$d];if($skill==='fraction_add')$visual['other']=$other;
        $concept=$skill==='fraction'?'A fraction counts equal parts. The denominator counts all parts; the numerator counts selected parts.':'For fractions with the same denominator, add the numerators and keep the denominator.';$hint=$skill==='fraction'?"Write shaded parts over all $d equal parts.":'Add the top numbers. Keep the bottom number.';break;
    case 'length':case 'weight':case 'capacity':
        $factor=$skill==='length'?100:1000;$large=['length'=>'metres','weight'=>'kilograms','capacity'=>'litres'][$skill];$small=['length'=>'centimetres','weight'=>'grams','capacity'=>'millilitres'][$skill];$answer=$a*$factor;$prompt="How many $small are in $a $large?";$concept="One $large unit equals $factor $small.";$hint="Multiply $a by $factor.";break;
    case 'table_total':case 'table_most':
        $scale=$grade===5?[2,5][random_int(0,1)]:1;$values=['Apples'=>$a,'Bananas'=>$b,'Oranges'=>$a+$b];$visual=['kind'=>$grade===5?'pictograph':'table','values'=>$values,'scale'=>$scale];$answer=$skill==='table_total'?2*($a+$b)*$scale:'Oranges';$prompt=$skill==='table_total'?'How many fruits are recorded altogether?':'Which fruit has the largest count?';$concept=$grade===5?"Each dot represents $scale fruits. Count dots and use the key.":'A table connects each label with its count. Read every row before answering.';$hint=$skill==='table_total'?'Add the counts, using the pictograph key if shown.':'Compare the counts, then choose the label.';if($skill==='table_most')$choices=array_keys($values);break;
    case 'position':
        $left=random_int(0,1)===1;$answer=$left?'left':'right';$visual=['kind'=>'position','labels'=>$left?['Ball','Box']:['Box','Ball']];$choices=['left','right'];$prompt='Is the ball to the left or right of the box?';$concept='Position describes where one object is compared with another. Read the arrangement from your viewpoint.';$hint='Look at which side of the box the ball occupies.';break;
    case 'shape_sides':
        $sides=['triangle'=>3,'square'=>4,'pentagon'=>5,'hexagon'=>6];$shape=array_rand($sides);$answer=$sides[$shape];$visual=['kind'=>'polygon','sides'=>$answer];$prompt="How many straight sides does this $shape have?";$concept='A polygon is a closed shape made of straight sides. Count each side once.';$hint='Start at one corner and count each edge around the shape.';break;
    case 'symmetry':
        $answer='matching halves';$choices=['matching halves','unequal halves'];$prompt='A fold is a line of symmetry when it divides a shape into what?';$concept='A line of symmetry divides a shape into two halves that match when folded along the line.';$hint='Imagine folding one half over the other.';break;
    case 'clock_minutes':
        $answer=$a*5;$visual=['kind'=>'clock','minute'=>$a];$prompt="The minute hand points to $a. How many minutes past the hour is it?";$concept='Each numbered step on a clock represents five minutes.';$hint="Count by fives $a times.";break;
    case 'elapsed':
        $hours=random_int(1,3);$minutes=[0,15,30,45][random_int(0,3)];$answer=$hours*60+$minutes;$prompt="A journey lasts $hours hours and $minutes minutes. How many minutes is that altogether?";$concept='One hour contains 60 minutes. Convert hours, then add the remaining minutes.';$hint="Start with $hours × 60, then add $minutes.";break;
    case 'sequence':
        $step=random_int(2,9);$start=random_int(1,30);$answer=$start+4*$step;$terms=implode(', ',[$start,$start+$step,$start+2*$step,$start+3*$step]);$prompt="What comes next: $terms, __?";$concept='In a constant-step pattern, add the same amount each time.';$hint='Subtract two neighbouring terms to find the step.';break;
    case 'roman':
        $roman=['IV'=>4,'VI'=>6,'IX'=>9,'XII'=>12,'XIV'=>14,'XIX'=>19,'XVIII'=>18,'XV'=>15];$symbol=array_rand($roman);$answer=$roman[$symbol];$prompt="Write $symbol as an ordinary number.";$concept='I means 1, V means 5 and X means 10. A smaller symbol before a larger one is subtracted.';$hint='Read from left to right; check whether a smaller symbol comes before a larger one.';break;
    case 'angle':
        $degrees=[30,45,60,90,120,135,150][random_int(0,6)];$answer=$degrees<90?'acute':($degrees===90?'right':'obtuse');$choices=['acute','right','obtuse'];$visual=['kind'=>'angle','degrees'=>$degrees];$prompt="What type of angle measures $degrees degrees?";$concept='An acute angle is below 90 degrees. A right angle is 90 degrees. An obtuse angle is above 90 and below 180 degrees.';$hint='Compare this angle with 90 degrees.';break;
    case 'diameter':
        $answer=2*$a;$prompt="A circle has radius $a cm. What is its diameter in cm?";$concept='A diameter crosses the centre of a circle and is twice its radius.';$hint="Add two radii of $a cm.";break;
    case 'factor':
        $multiple=$a*$b;$answer=($multiple%$n)===0?'yes':'no';$choices=['yes','no'];$prompt="Is $n a factor of $multiple?";$concept='A factor divides a number exactly, with no remainder.';$hint="Divide $multiple by $n and check for a remainder.";break;
    case 'decimal':
        $t=random_int(1,9);$h=random_int(1,9);$type='rational';$answer=[$t*10+$h,100];$prompt="Write $t tenths and $h hundredths as a decimal.";$concept='One tenth equals ten hundredths. Decimal places show tenths, then hundredths.';$hint='Put the tenths digit immediately after the decimal point, then the hundredths digit.';break;
    case 'perimeter':case 'area':
        $answer=$skill==='perimeter'?2*($a+$b):$a*$b;$visual=['kind'=>'rectangle','a'=>$a,'b'=>$b];$prompt=$skill==='perimeter'?"A rectangle is $a cm long and $b cm wide. What is its perimeter in cm?":"A rectangle is $a cm long and $b cm wide. What is its area in square cm?";$concept=$skill==='perimeter'?'Perimeter is the distance around the boundary. Add all four sides of a rectangle.':'Area counts square units inside a shape. Multiply a rectangle’s length by its width.';$hint=$skill==='perimeter'?"Add $a + $b + $a + $b.":"Multiply $a by $b.";break;
    case 'net':
        $answer='six squares';$choices=['six squares','three circles','one triangle'];$prompt='Which set of faces can make a cube net?';$concept='A net unfolds the faces of a solid into a flat arrangement. A cube needs six square faces, arranged so they can fold without overlap.';$hint='Think about the shape and number of the cube’s faces.';break;
    case 'algebra':
        $answer=$a;$total=$a+$b;$prompt="What number makes this true: □ + $b = $total?";$concept='A box can stand for an unknown number. Keep both sides of an equality balanced.';$hint="Subtract $b from $total.";break;
    default:throw new RequestFailure(503,'This skill is not ready');
    }
    if($choices)$type='choice';
    $shown=is_array($answer)?$answer[0].'/'.$answer[1]:(string)$answer;
    $worked="The answer is $shown. $hint";
    if($skill==='add')$worked="$a + $b = $shown. Add the ones: ".($a%10).' + '.($b%10).'. Exchange ten ones for one ten when needed, then add the remaining columns.';
    if($skill==='sub')$worked="$a − $b = $shown. Subtract from the ones column. When the top digit is too small, exchange a ten before subtracting; continue leftwards.";
    if($skill==='multiply')$worked="$b groups × $x in each group = $shown. Split $b into place-value parts and multiply each part by $x, then add the results.";
    if($skill==='divide')$worked="$shown in each group × $b groups = $total, so $total divided by $b is $shown.";
    if($skill==='fraction_add')$worked="Add the numerators: $top + $other = ".($top+$other).". The equal-sized parts stay the same, so the answer is $shown.";
    if($skill==='decimal')$worked="$t tenths equals ".($t*10)." hundredths. Add $h hundredths to get ".($t*10+$h)."/100, or 0.$t$h.";

    return ['id'=>uuid_v4(),'skill'=>$skill,'prompt'=>$prompt,'concept'=>$concept,'hint'=>$hint,'worked'=>$worked,'answer'=>$answer,'answer_type'=>$type,'choices'=>$choices,'visual'=>$visual,'tries'=>0,'resolved'=>false,'independent'=>false];
}
function maths_answer_matches(array $q,mixed $answer): bool {
    if(!is_string($answer)||strlen($answer)>24)throw new RequestFailure(422,'Enter a short answer');
    $answer=trim($answer);
    if($q['answer_type']==='choice'){
        if(!in_array($answer,$q['choices'],true))throw new RequestFailure(422,'Choose one of the answers');return $answer===$q['answer'];
    }
    if($q['answer_type']==='number'){
        if(!preg_match('/^\d{1,9}$/',$answer))throw new RequestFailure(422,'Enter a whole number');return (int)$answer===$q['answer'];
    }
    if(preg_match('/^(\d{1,6})\/(\d{1,6})$/',$answer,$m)){$n=(int)$m[1];$d=(int)$m[2];}
    elseif(preg_match('/^(\d{1,6})(?:\.(\d{1,4}))?$/',$answer,$m)){$digits=$m[2]??'';$d=10**strlen($digits);$n=(int)$m[1]*$d+(int)($digits?:0);}
    else throw new RequestFailure(422,'Enter a whole number, fraction or decimal');
    if($d===0)throw new RequestFailure(422,'A fraction denominator must be positive');
    return $n*$q['answer'][1]===$q['answer'][0]*$d;
}
