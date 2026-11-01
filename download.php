<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    exit('Unauthorized');
}

const OPENROUTER_KEY = 'sk-or-v1-replace_ME';   // same key as generate.php

$url    = $_GET['url']    ?? '';
$stream = !empty($_GET['stream']);   // 1 = inline for <video>, 0/absent = download

if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    exit('Bad url');
}

$host = parse_url($url, PHP_URL_HOST);
$path = parse_url($url, PHP_URL_PATH) ?: '';

$isGoogle     = is_string($host) && preg_match('/(^|\.)googleapis\.com$/i', $host);
$isOpenRouter = is_string($host) && $host === 'openrouter.ai'
              && preg_match('#^/api/v1/videos/[^/]+/content#', $path);

if (!$isGoogle && !$isOpenRouter) {
    http_response_code(400);
    exit('Bad host');
}

$headers = [];
if ($isOpenRouter) {
    $headers[] = 'Authorization: Bearer ' . OPENROUTER_KEY;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADER         => false,
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_HEADERFUNCTION => function ($ch, $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            header($header);
        } elseif (stripos($header, 'Content-Length:') === 0) {
            header($header);
        }
        // Some upstreams (e.g. Google Cloud Storage) honor Range requests
        // for seeking in <video>. Forward the status so the browser sees 206s.
        return strlen($header);
    },
    CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
        echo $data;
        return strlen($data);
    },
]);

if (!$stream) {
    // Download mode: force attachment filename
    $ext = 'mp4';
    if (stripos($url, '.png') !== false) $ext = 'png';
    elseif (stripos($url, '.jpg') !== false || stripos($url, '.jpeg') !== false) $ext = 'jpg';
    elseif (stripos($url, '.gif') !== false) $ext = 'gif';
    header('Content-Disposition: attachment; filename="media.' . $ext . '"');
} else {
    // Inline mode: let the browser play the video. Still block sniffing.
    header('Content-Disposition: inline');
}

header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');

curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($code >= 400) {
    http_response_code(502);
    echo 'Download link expired or unreachable';
}
curl_close($ch);
