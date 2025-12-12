<?php
// file: backend/admin/backup.php
session_start();
if (!isset($_SESSION['utente']) || ($_SESSION['utente']['ruolo'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Accesso negato.');
}

require_once __DIR__ . '/../assets/funzioni/db/db.php'; // deve valorizzare $conn (mysqli)

/* ====== Setup esecuzione lunga/stream ====== */
@set_time_limit(0);
@ini_set('memory_limit', '512M');
while (ob_get_level() > 0) { @ob_end_clean(); }

$now = date('Ymd_His');
$filename = "backup_{$now}.sql";

/* ====== Header download ====== */
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* ====== Helper ====== */
function out($s){ echo $s; }
function qi($name){ return '`' . str_replace('`','``',$name) . '`'; } // quote identifier
function qv($conn, $v){
    if (is_null($v))      return 'NULL';
    if (is_bool($v))      return $v ? '1' : '0';
    if (is_int($v))       return (string)$v;
    if (is_float($v))     return rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.'); // evita locale
    // string/binary → escape + quotes
    return "'" . $conn->real_escape_string($v) . "'";
}

/* ====== Intestazione dump ====== */
out("-- ------------------------------------------------------\n");
out("-- Dump creato via PHP (senza mysqldump)\n");
out("-- Data: ".date('Y-m-d H:i:s')."\n");
out("-- Host: ".$conn->host_info."\n");
out("-- DB:   ".$conn->query("SELECT DATABASE()")->fetch_row()[0]."\n");
out("-- ------------------------------------------------------\n\n");

out("SET NAMES utf8mb4;\n");
out("SET FOREIGN_KEY_CHECKS=0;\n");
out("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

/* ====== Elenco tabelle (solo BASE TABLE, no VIEW) ====== */
$tables = [];
$res = $conn->query("SHOW FULL TABLES");
if ($res) {
    $colTable = 0; $colType = 1; // SHOW FULL TABLES ritorna: [Tables_in_db, Table_type]
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
        $tName = (string)$row[$colTable];
        $tType = strtolower((string)$row[$colType]);
        if ($tType === 'base table') $tables[] = $tName;
    }
    $res->free();
} else {
    // Fallback: SHOW TABLES
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_array(MYSQLI_NUM)) $tables[] = (string)$row[0];
    $res->free();
}

foreach ($tables as $t) {
    out("--\n-- Struttura tabella ".qi($t)."\n--\n\n");

    // DROP + CREATE
    out("DROP TABLE IF EXISTS ".qi($t).";\n");
    $rsCreate = $conn->query("SHOW CREATE TABLE ".qi($t));
    if ($rsCreate && ($cr = $rsCreate->fetch_array(MYSQLI_NUM))) {
        // $cr[1] contiene il DDL completo
        out($cr[1].";\n\n");
        $rsCreate->free();
    } else {
        // Se il CREATE fallisce, passa oltre ma segnala
        out("-- [WARN] Impossibile ottenere CREATE TABLE per {$t}\n\n");
    }

    // Dati
    out("--\n-- Dati per la tabella ".qi($t)."\n--\n");

    // Colonne in ordine
    $cols = [];
    $rsCols = $conn->query("SHOW COLUMNS FROM ".qi($t));
    while ($c = $rsCols->fetch_assoc()) $cols[] = $c['Field'];
    $rsCols->free();
    $colList = implode(',', array_map('qi', $cols));

    // Estrai a blocchi per non saturare la memoria
    $batch = 1000;
    $offset = 0;
    $total = 0;

    // Scopri numero righe (per info)
    $rsCnt = $conn->query("SELECT COUNT(*) FROM ".qi($t));
    $totRows = $rsCnt ? (int)$rsCnt->fetch_row()[0] : 0;
    if ($rsCnt) $rsCnt->free();
    if ($totRows === 0) { out("-- (0 righe)\n\n"); continue; }

    out("-- (".$totRows." righe)\n");

    while (true) {
        $rs = $conn->query("SELECT * FROM ".qi($t)." LIMIT {$batch} OFFSET {$offset}");
        if (!$rs) break;
        if ($rs->num_rows === 0) { $rs->free(); break; }

        $valsRows = [];
        while ($row = $rs->fetch_assoc()) {
            $vals = [];
            foreach ($cols as $c) { $vals[] = qv($conn, $row[$c]); }
            $valsRows[] = '('.implode(',', $vals).')';
        }
        $rs->free();

        // Scrivi un unico INSERT per batch (più compatto/veloce)
        out("INSERT INTO ".qi($t)." ({$colList}) VALUES\n  ".implode(",\n  ", $valsRows).";\n");

        $offset += $batch;
        $total  += count($valsRows);

        // Flush per inviare a streaming
        if (function_exists('flush')) { @flush(); }
        if (function_exists('ob_flush')) { @ob_flush(); }
    }
    out("\n");
}

/* ====== Footer dump ====== */
out("SET FOREIGN_KEY_CHECKS=1;\n");
out("-- Fine dump\n");
exit;
