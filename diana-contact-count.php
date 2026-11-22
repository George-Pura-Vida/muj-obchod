<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function dianaStatusReply(array $body, int $status): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}

if (!in_array(strtolower((string) ($_SERVER['HTTPS'] ?? '')), ['on', '1'], true)) {
    dianaStatusReply(['ok' => false], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    dianaStatusReply(['ok' => false], 405);
}

try {
    $configPath = __DIR__ . '/config.local.php';
    if (!is_file($configPath)) {
        dianaStatusReply(['ok' => false], 404);
    }
    ob_start();
    try {
        $config = require $configPath;
    } finally {
        ob_end_clean();
    }

    $tokenHash = $config['diana_contact_count_token_sha256'] ?? null;
    $ownerId = $config['diana_contact_count_owner_id'] ?? null;
    if (!is_string($tokenHash) || !preg_match('/\\A[0-9a-f]{64}\\z/', $tokenHash)
        || !is_int($ownerId) || $ownerId < 1) {
        dianaStatusReply(['ok' => false], 404);
    }

    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/\\ABearer ([0-9a-f]{64})\\z/i', $authorization, $matches)
        || !hash_equals($tokenHash, hash('sha256', $matches[1]))) {
        header('WWW-Authenticate: Bearer');
        dianaStatusReply(['ok' => false], 401);
    }

    require_once __DIR__ . '/account-admin.php';
    $db = new PDO('sqlite:' . dirname(__DIR__) . '/moje-schuzka-data/database.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
    ]);
    $account = account($db, $ownerId);
    if (!$account || !$account['active'] || !permissions($account)['screens']['people']) {
        dianaStatusReply(['ok' => false], 403);
    }

    $query = $db->prepare('SELECT COUNT(*) FROM contacts WHERE user_id = ?');
    $query->execute([$ownerId]);
    dianaStatusReply(['ok' => true, 'contact_count' => (int) $query->fetchColumn()], 200);
} catch (Throwable $error) {
    error_log('Diana contact count check failed: ' . get_class($error));
    dianaStatusReply(['ok' => false], 503);
}
