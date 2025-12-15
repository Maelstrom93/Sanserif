<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../assets/funzioni/authz.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';

requireLogin();

const ERR_INTERNAL = 'Errore interno';

/**
 * Connessione DB sicura
 */
function apiDb(): mysqli {
    try {
        return db();
    } catch (Throwable $e) {
        error_log("API DB error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>ERR_INTERNAL], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Risposta OK
 */
function apiOk(array $data = []): void {
    echo json_encode(array_merge(['success'=>true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Risposta errore
 */
function apiErr(int $code = 400, string $msg = 'Errore'): void {
    http_response_code($code);
    echo json_encode(['success'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}
