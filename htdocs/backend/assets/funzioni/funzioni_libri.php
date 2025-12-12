<?php
// Helpers e funzioni per la sezione Libri (Portfolio)

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Normalizza il path immagine di copertina per l'uso nel frontend.
 */
function norm_img(string $raw): string {
  $p = trim($raw);
  if ($p === '') return '';
  if (preg_match('~^https?://~i', $p) || strpos($p, '//') === 0) return $p;
  if (strpos($p, '/backend/uploads/') === 0) return $p;
  if (strpos($p, 'backend/uploads/') === 0)  return '/'.$p;
  if (strpos($p, 'uploads/') === 0)          return '/backend/'.$p;
  if (strpos($p, '/') === false)             return '/backend/uploads/'.$p;
  if ($p[0] !== '/')                         return '/'.$p;
  return $p;
}

/**
 * Estrae i libri dal DB.
 * - $limit: se valorizzato, limita il numero di righe
 * Ritorna un mysqli_result oppure null.
 */

