<?php
// funzioni_articoli.php

function norm_img($p) {
  $p = trim((string)$p);
  if ($p === '') return '';

  // URL assoluti
  if (preg_match('~^https?://~i', $p) || substr($p, 0, 2) === '//') return $p;

  // normalizzazione
  $p = preg_replace('~^\.\/+~', '', $p);     // ./foo -> foo
  $p = preg_replace('~(^|/)../~', '$1', $p); // ../foo -> foo
  $p = preg_replace('~/{2,}~', '/', $p);     // // -> /

  if (strpos($p, '/backend/uploads/') === 0) return $p;
  if (strpos($p, 'backend/uploads/') === 0)  return '/'.$p;
  if (strpos($p, 'uploads/') === 0)          return '/backend/'.$p;

  if (strpos($p, '/') === false) return '/backend/uploads/'.$p;
  if ($p[0] !== '/') return '/'.$p;
  return $p;
}
?>
