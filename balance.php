<?php
declare(strict_types=1);
session_start();

// Reuse your existing auth guard
if (empty($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

const MANAGEMENT_KEY = 'sk-or-v1-replace_ME'; // Do NOT use your inference key

header('Content-Type: application/json');

$ch = curl_init('https://openrouter.ai/api/v1/credits');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . MANAGEMENT_KEY,
    ],
    CURLOPT_TIMEOUT        => 10,
]);

$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 400) {
    echo json_encode(['ok' => false, 'error' => 'Failed to fetch balance']);
    exit;
}

$json = json_decode($raw, true);
$total = $json['data']['total_credits'] ?? 0;
$used  = $json['data']['total_usage'] ?? 0;
$remaining = $total - $used;

echo json_encode(['ok' => true, 'remaining' => round($remaining, 2)]);
