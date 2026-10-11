<?php
declare(strict_types=1);

function fraction_gcd(int $a,int $b): int { while($b!==0){[$a,$b]=[$b,$a%$b];}return max(1,$a); }
function fraction_text(array $value): string {
    [$n,$d]=$value;$g=fraction_gcd($n,$d);$n=intdiv($n,$g);$d=intdiv($d,$g);
    return $d===1?(string)$n:$n.'/'.$d;
}
function fraction_value(string $answer): ?array {
    if(!preg_match('/^([0-9]{1,3})(?:\/([0-9]{1,3}))?$/',$answer,$m))return null;
    $d=isset($m[2])?(int)$m[2]:1;
    return $d>0?[(int)$m[1],$d]:null;
}
function fraction_question(array $used,int $index): array {
    $skill=['identify','equivalent','compare','fraction_add','fraction_subtract'][$index%5];
    for($tries=0;$tries<500;$tries++){
        $d=random_int(2,12);$a=random_int(1,$d-1);$b=random_int(1,$d-1);$k=random_int(2,min(8,intdiv(24,$d)));
        $q=['id'=>uuid_v4(),'skill'=>$skill,'tries'=>0,'resolved'=>false,'correct'=>false,'a'=>$a,'d'=>$d];
        if($skill==='identify'){
            $q+=['signature'=>"shade $a/$d",'answer'=>[$a,$d],'answer_type'=>'fraction','prompt'=>'What fraction of this bar is shaded?',
                'concept'=>'A fraction names equal parts of a whole. The bottom number counts all equal parts; the top number counts the shaded parts. Example: three shaded parts out of eight is 3/8.',
                'hint'=>'Count all equal parts for the bottom number. Count the shaded parts for the top number.',
                'bars'=>[['n'=>$a,'d'=>$d]],'speech'=>"The bar has $d equal parts and $a shaded parts. What fraction is shaded?"];
        }elseif($skill==='equivalent'){
            $target=$d*$k;
            $q+=['signature'=>"equivalent $a/$d ?/$target",'answer'=>$a*$k,'answer_type'=>'number','target'=>$target,'prompt'=>"Fill the missing numerator: $a/$d = ?/$target",
                'concept'=>'Equivalent fractions name the same amount. Multiply the top and bottom by the same number. Example: 2/3 is equivalent to 4/6.',
                'hint'=>"Find the number that multiplies $d to make $target. Multiply the top by that same number.",
                'bars'=>[['n'=>$a,'d'=>$d],['n'=>0,'d'=>$target,'unknown'=>true]],'speech'=>"$a over $d equals a missing number over $target. What is the missing numerator?"];
        }elseif($skill==='compare'){
            $otherD=random_int(2,12);$otherN=random_int(1,$otherD-1);
            if(random_int(1,4)===1){$otherD=$d*2;$otherN=$a*2;}
            $left=$a*$otherD;$right=$otherN*$d;
            $q+=['signature'=>"compare $a/$d $otherN/$otherD",'answer'=>$left===$right?'=':($left<$right?'<':'>'),'answer_type'=>'comparison',
                'prompt'=>"Compare: $a/$d ? $otherN/$otherD",'b'=>$otherN,'other_d'=>$otherD,
                'concept'=>'Compare fractions of the same-sized whole. A smaller denominator does not always mean a bigger fraction. Example: 1/2 is greater than 1/3; 2/4 equals 1/2.',
                'hint'=>'Use the bars, or multiply the left top by the right bottom and the right top by the left bottom. Compare those two numbers.',
                'bars'=>[['n'=>$a,'d'=>$d],['n'=>$otherN,'d'=>$otherD]],'speech'=>"Is $a over $d less than, equal to, or greater than $otherN over $otherD?"];
        }else{
            $op=$skill==='fraction_add'?'+':'−';if($op==='−'&&$a<$b)[$a,$b]=[$b,$a];$q['a']=$a;
            $n=$op==='+'?$a+$b:$a-$b;
            $q+=['signature'=>"$a/$d $op $b/$d",'answer'=>[$n,$d],'answer_type'=>'fraction','b'=>$b,'op'=>$op,
                'prompt'=>"What is $a/$d $op $b/$d?",
                'concept'=>$op==='+'?'With the same bottom number, add the top numbers and keep the bottom. Example: 1/5 plus 2/5 is 3/5. You can give an equivalent simplified answer.':'With the same bottom number, subtract the top numbers and keep the bottom. Example: 4/5 minus 1/5 is 3/5. You can give an equivalent simplified answer.',
                'hint'=>$op==='+'?'The pieces are the same size. Add the top numbers; do not add the bottom numbers.':'The pieces are the same size. Subtract the top numbers; keep the bottom number.',
                'bars'=>[['n'=>$a,'d'=>$d],['n'=>$b,'d'=>$d]],'speech'=>"What is $a over $d ".($op==='+'?'plus':'minus')." $b over $d?"];
        }
        if(!in_array($q['signature'],$used,true))return $q;
    }
    throw new RequestFailure(503,'Please try a fresh practice later');
}
