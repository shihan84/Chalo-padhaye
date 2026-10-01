<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/api#', '', $uri);
$path = '/' . ltrim((string)$path, '/');

try {
    if ($method === 'GET' && $path === '/health') {
        $database = false;
        try {
            db()->query('SELECT 1');
            $database = true;
        } catch (Throwable $e) {
            $database = false;
        }
        json_response([
            'ok' => true,
            'runtime' => 'php',
            'database' => $database,
            'custom_voice' => (bool)(env_value('FISH_AUDIO_API_KEY') && env_value('FISH_AUDIO_VOICE_ID')),
        ]);
    }

    if ($method === 'GET' && $path === '/config') {
        json_response([
            'backend' => 'hostinger-php',
            'auth' => 'mysql',
            'custom_voice' => (bool)(env_value('FISH_AUDIO_API_KEY') && env_value('FISH_AUDIO_VOICE_ID')),
        ]);
    }

    json_response(['error' => 'API route not implemented', 'path' => $path], 404);
} catch (Throwable $e) {
    error_log('Chalo Padhaye API error: ' . $e->getMessage());
    json_response(['error' => 'Internal server error'], 500);
}
