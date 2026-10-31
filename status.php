<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

const OPENROUTER_KEY = 'sk-or-v1-REPLACEME';

header('Content-Type: application/json');

function fail(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$jobId  = $_GET['id']  ?? '';
$pollUrl = $_GET['poll'] ?? '';

// Prefer explicit polling_url if the frontend passes it
if ($pollUrl !== '') {
    $url = $pollUrl;
} elseif ($jobId !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $jobId)) {
    $url = 'https://openrouter.ai/api/v1/videos/' . $jobId;
} else {
    fail('Missing id or poll url');
}

// Restrict the URL to openrouter.ai so nobody can turn this into an SSRF proxy
$host = parse_url($url, PHP_URL_HOST);
if (!is_string($host) || !preg_match('/(^|\.)openrouter\.ai$/i', $host)) {
    fail('Only openrouter.ai polling URLs are allowed');
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . OPENROUTER_KEY,
    ],
    CURLOPT_TIMEOUT        => 20,
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode($raw, true);
if ($code >= 400) {
    fail('OpenRouter: ' . ($json['error']['message'] ?? $raw), 502);
}

$status = $json['status'] ?? 'unknown';

$out = [
    'ok'     => true,
    'status' => $status,
];

if ($status === 'completed') {
    $videoUrl = $json['urls'][0] ?? $json['unsigned_urls'][0] ?? null;
    if (!$videoUrl) fail('Completed without URL', 502);
    $out['video_url'] = $videoUrl;
} elseif ($status === 'failed') {
    $out['error'] = $json['error'] ?? 'Generation failed';
}

echo json_encode($out);