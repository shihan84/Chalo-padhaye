<?php
declare(strict_types=1);
// Public metadata only. Never fetch arbitrary URLs or enable tutor retrieval here.
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
    return ['curriculum'=>$curriculum,'board'=>$board['label'],'grade'=>$grade,
        'grades'=>array_keys($board['grades']),'reviewed_on'=>$data['reviewed_on'],'notice'=>$data['notice'],
        'coverage'=>$coverage,'tutor_available'=>false]+$entry;
}
