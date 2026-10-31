<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$url = $_GET['url'] ?? '';
if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    exit('Bad url');
}

// Only allow Google's generated-media bucket so this can't be used as a proxy
$host = parse_url($url, PHP_URL_HOST);
if (!is_string($host) || !preg_match('/(^|\.)googleapis\.com$/i', $host)) {
    http_response_code(400);
    exit('Bad host');
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADER         => false,
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_HEADERFUNCTION => function ($ch, $header) {
        // Forward Content-Type and Content-Length to the browser
        if (stripos($header, 'Content-Type:') === 0) {
            header($header);
        } elseif (stripos($header, 'Content-Length:') === 0) {
            header($header);
        }
        return strlen($header);
    },
    CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
        echo $data;
        return strlen($data);
    },
]);

// Decide filename from Content-Type (we'll discover it in the HEADERFUNCTION)
$ext = 'mp4';
$filename = 'video.' . $ext;
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

curl_exec($ch);
// after curl_exec, check status:
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($code >= 400) {
    http_response_code(502);
    echo 'Download link expired or unreachable';
}
curl_close($ch);
?>