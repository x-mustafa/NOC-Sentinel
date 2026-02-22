<?php
// Copy this file to config.php and fill in your values
define('DB_HOST', 'localhost');
define('DB_NAME', 'tabadul_noc');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_SECRET', 'change_this_to_a_random_string');
define('ZABBIX_URL_DEFAULT', 'https://your-zabbix-server');
define('ZABBIX_TOKEN_DEFAULT', 'your_zabbix_api_token_here');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function getZabbixConfig(): array {
    try {
        $db = getDB();
        $row = $db->query("SELECT * FROM zabbix_config LIMIT 1")->fetch();
        return $row ?: ['url' => ZABBIX_URL_DEFAULT, 'token' => ZABBIX_TOKEN_DEFAULT, 'refresh' => 30];
    } catch (Exception $e) {
        return ['url' => ZABBIX_URL_DEFAULT, 'token' => ZABBIX_TOKEN_DEFAULT, 'refresh' => 30];
    }
}

function callZabbix(string $method, array $params = []): mixed {
    $cfg = getZabbixConfig();
    $url = rtrim($cfg['url'], '/') . '/api_jsonrpc.php';
    $token = $cfg['token'];

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method'  => $method,
        'params'  => $params,
        'id'      => 1,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array_filter([
            'Content-Type: application/json',
            $method !== 'apiinfo.version' ? "Authorization: Bearer $token" : null,
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    curl_close($ch);

    if ($errno || !$response) return null;
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) return null;
    if (isset($decoded['error'])) return ['_zabbix_error' => $decoded['error']['data'] ?? $decoded['error']['message'] ?? 'Zabbix error'];
    return $decoded['result'] ?? [];
}

function jsonOut(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireSession(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['uid'])) jsonOut(['error' => 'Unauthorized'], 401);
    $sess = $_SESSION;
    session_write_close();
    return $sess;
}
