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
const UPLOAD_DIR     = __DIR__ . '/uploads/';
const UPLOAD_URL     = 'https://videogen.22web.org/uploads/';
const ALLOWED_MIME   = ['image/png', 'image/jpeg', 'image/gif'];

const RATELIMIT_DIR     = __DIR__ . '/.ratelimit/';   // outside uploads/
const RATELIMIT_SECONDS = 20;

header('Content-Type: application/json');

// ---------- helpers (defined before use) ----------
function fail(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function rateLimitOrFail(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = 'unknown';
    }

    if (!is_dir(RATELIMIT_DIR) && !mkdir(RATELIMIT_DIR, 0755, true)) {
        fail('Rate limiter unavailable', 500);
    }

    $file = RATELIMIT_DIR . hash('sha256', $ip);

    if (is_file($file)) {
        $age = time() - filemtime($file);
        if ($age >= 0 && $age < RATELIMIT_SECONDS) {
            $retry = RATELIMIT_SECONDS - $age;
            header('Retry-After: ' . $retry);
            fail("Rate limit: please wait {$retry}s before generating another video.", 429);
        }
    }

    // opportunistic cleanup
    if (random_int(1, 100) === 1) {
        foreach (glob(RATELIMIT_DIR . '*') as $f) {
            if (is_file($f) && time() - filemtime($f) > 3600) {
                @unlink($f);
            }
        }
    }

    if (!@touch($file)) {
        fail('Rate limiter unavailable', 500);
    }
    @chmod($file, 0644);
}

function orPost(string $url, array $body, string $apiKey): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://videogen.22web.org',
            'X-Title: AI Video Generator',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 60,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($raw, true);
    if ($code >= 400) fail('OpenRouter: ' . ($json['error']['message'] ?? $raw), 502);
    return $json;
}

// ---------- validate input ----------
$mode        = $_POST['mode']         ?? '';
$prompt      = trim($_POST['prompt']  ?? '');
$duration    = (int)($_POST['duration'] ?? 5);
$resolution  = $_POST['resolution']    ?? '768p';
$aspectRatio = $_POST['aspect_ratio']  ?? '16:9';

if (!in_array($mode, ['text', 'image'], true))    fail('Bad mode');
if ($prompt === '' || mb_strlen($prompt) > 5000)  fail('Bad prompt');
if ($duration < 5 || $duration > 15)              fail('Bad duration');
if (!in_array($resolution, ['480p', '768p', '2K'], true)) fail('Bad resolution');
if (!in_array($aspectRatio, ['21:9','16:9','4:3','1:1','3:4','9:16'], true)) fail('Bad aspect ratio');

// ---------- rate limit the expensive part ----------
rateLimitOrFail();


// ---- upload image (image mode) ----
$frameImages = null;
if ($mode === 'image') {
    if (empty($_FILES['image']['tmp_name'])) fail('Missing image');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['image']['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME, true)) fail('Only PNG, JPG, GIF allowed');
    if ($_FILES['image']['size'] > 10 * 1024 * 1024) fail('Image > 10 MB');
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) fail('Cannot create upload dir', 500);

    $ext  = match ($mime) {
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/gif'  => 'gif',
    };
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $name)) {
        fail('Failed to store image', 500);
    }
    $publicUrl = rtrim(UPLOAD_URL, '/') . '/' . $name;
    $frameImages = [[
        'type'       => 'image_url',
        'image_url'  => ['url' => $publicUrl],
        'frame_type' => 'first_frame',
    ]];
}

// ---- submit job ----
$body = [
    'model'      => 'heygen/heygen-video-1',
    'prompt'     => $prompt,
    'duration'   => $duration,
    'resolution' => $resolution,
];
if ($frameImages) {
    $body['frame_images'] = $frameImages;
} else {
    $body['aspect_ratio'] = $aspectRatio;
}

$job = orPost('https://openrouter.ai/api/v1/videos', $body, OPENROUTER_KEY);

// The OpenRouter /videos response gives us at least { id, status }.
// polling_url is used when present; otherwise we build it.
$jobId     = $job['id'] ?? null;
$pollUrl   = $job['polling_url'] ?? ($jobId ? "https://openrouter.ai/api/v1/videos/{$jobId}" : null);

if (!$jobId || !$pollUrl) fail('OpenRouter did not return a job id', 502);

echo json_encode([
    'ok'          => true,
    'job_id'      => $jobId,
    'polling_url' => $pollUrl,
    'status'      => $job['status'] ?? 'pending',
]);
?>