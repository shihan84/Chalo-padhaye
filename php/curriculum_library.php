<?php
declare(strict_types=1);
require_once __DIR__.'/textbook.php';
// Public metadata only; page text remains private and requires family authentication.
function curriculum_library(string $curriculum, int $grade): ?array {
    static $data=null;
    if ($data===null) $data=json_decode((string)@file_get_contents(dirname(__DIR__).'/data/curriculum_sources.json'),true)?:[];
    $board=$data['boards'][$curriculum]??null;
    $entry=$board['grades'][(string)$grade]??null;
    if (!$entry) return null;
    $coverage=[];
    foreach ($data['boards']??[] as $key=>$row) {
        $coverage[]=['curriculum'=>$key,'label'=>$row['label'],'classes'=>count($row['grades']),
            'book_entries'=>array_sum(array_map(fn($g)=>count($g['books']),$row['grades'])),
            'pending_classes'=>count(array_filter($row['grades'],fn($g)=>count($g['pending'])>0))];
    }
    $ready=0;
    foreach($entry['books'] as &$book){
        foreach(textbook_manifest() as $source){
            if($source['url']!==$book['url'])continue;
            $book['book_id']=$source['id'];$book['page_count']=$source['page_count'];$book['first_lesson_page']=$source['first_lesson_page'];$book['chapters']=$source['chapters'];$book['tutor_grades']=$source['grades'];
            $book['tutor_available']=textbook_index($source)!==null;if($book['tutor_available'])$ready++;
        }
    }unset($book);
    $profiles=[];
    if($curriculum==='telangana'&&$grade===10){
        $profileData=json_decode((string)file_get_contents(dirname(__DIR__).'/data/telangana_study_profiles.json'),true,512,JSON_THROW_ON_ERROR);
        $profiles=$profileData['profiles'];
    }
    return ['curriculum'=>$curriculum,'board'=>$board['label'],'grade'=>$grade,'study_profiles'=>$profiles,
        'grades'=>array_keys($board['grades']),'reviewed_on'=>$data['reviewed_on'],'notice'=>$data['notice'],
        'coverage'=>$coverage,'tutor_available'=>$ready>0,'indexed_books'=>$ready]+$entry;
}
