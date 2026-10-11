<?php
declare(strict_types=1);
// This file belongs in public_html; implementation and .env are siblings outside it.
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if (!preg_match('#^/api(?:/|$)#',$path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo '{"error":"Not found"}';
    exit;
}
$_SERVER['HTTP_AUTHORIZATION']=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
require dirname(__DIR__).'/chalo-private/php/api/index.php';
