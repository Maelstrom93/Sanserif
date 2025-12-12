<?php

// Safe-escape HTML
if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

/**
 * Normalizza un record cliente proveniente dal DB/API
 * - Uniforma le chiavi (gestisce "città" vs "citta")
 * - Garantisce che tutte le chiavi previste esistano
 *
 * @param array $row
 * @return array
 */
function clienti_normalizza(array $row): array {
  $row = array_change_key_case($row, CASE_LOWER);

  // alias per città con/senza accento
  if (!isset($row['citta']) && isset($row['città'])) {
    $row['citta'] = $row['città'];
  }

  // campi attesi
  $defaults = [
    'id'             => null,
    'nome'           => '',
    'referente_1'    => '',
    'referente_2'    => '',
    'telefono'       => '',
    'email'          => '',
    'partita_iva'    => '',
    'codice_univoco' => '',
    'indirizzo'      => '',
    'cap'            => '',
    'citta'          => '',
  ];

  // merge preservando valori presenti
  $out = array_merge($defaults, $row);

  // cast/trim
  $out['id']   = isset($out['id']) ? (int)$out['id'] : null;
  foreach (['nome','referente_1','referente_2','telefono','email','partita_iva','codice_univoco','indirizzo','cap','citta'] as $k) {
    $out[$k] = trim((string)$out[$k]);
  }

  return $out;
}

/**
 * Restituisce tutti i clienti normalizzati come array.
 *
 * @param mysqli $conn
 * @return array<int,array>
 */
function clienti_tutti(mysqli $conn): array {
  if (function_exists('estraiclienti')) {
    $res = estraiclienti($conn);
  } else {
    $res = $conn->query("SELECT * FROM clienti ORDER BY id DESC");
  }

  $out = [];
  if ($res && $res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
      $out[] = clienti_normalizza($row);
    }
  }
  return $out;
}

/**
 * Conta i clienti.
 *
 * @param mysqli $conn
 * @return int
 */
function clienti_conta(mysqli $conn): int {
  if (function_exists('estraiclienti')) {
    $res = estraiclienti($conn);
    return ($res && $res instanceof mysqli_result) ? (int)$res->num_rows : 0;
  }
  $res = $conn->query("SELECT COUNT(*) AS n FROM clienti");
  if ($res && ($row = $res->fetch_assoc())) return (int)$row['n'];
  return 0;
}

/**
 * Valida i dati di input del cliente (minimo indispensabile).
 *
 * @param array $in
 * @return array [bool valid, array data_normalized, string error]
 */
function clienti_valida_input(array $in): array {
  $c = clienti_normalizza($in);

  if ($c['nome'] === '') {
    return [false, $c, 'Il campo "Nome / Ragione sociale" è obbligatorio.'];
  }
  if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
    return [false, $c, 'L\'indirizzo email non è valido.'];
  }
  return [true, $c, ''];
}

/**
 * Inserisce un nuovo cliente.
 *
 * @param mysqli $conn
 * @param array $in
 * @return array [bool success, ?int id, string error]
 */
function clienti_inserisci(mysqli $conn, array $in): array {
  [$ok, $c, $err] = clienti_valida_input($in);
  if (!$ok) return [false, null, $err];

  // usa il nome colonna `città` come da schema
  $sql = "INSERT INTO clienti
    (nome, referente_1, referente_2, telefono, email, partita_iva, codice_univoco, indirizzo, cap, `città`)
    VALUES (?,?,?,?,?,?,?,?,?,?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return [false, null, 'Errore prepare: '.$conn->error];

  $stmt->bind_param(
    "ssssssssss",
    $c['nome'],
    $c['referente_1'],
    $c['referente_2'],
    $c['telefono'],
    $c['email'],
    $c['partita_iva'],
    $c['codice_univoco'],
    $c['indirizzo'],
    $c['cap'],
    $c['citta']
  );

  if (!$stmt->execute()) {
    $msg = 'Errore inserimento cliente: '.$stmt->error;
    $stmt->close();
    return [false, null, $msg];
  }

  $newId = (int)$stmt->insert_id;
  $stmt->close();
  return [true, $newId, ''];
}
