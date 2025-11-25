<?php
// assets/funzioni/authz.php
// Gestione autorizzazioni e permessi (RBAC) con fallback legacy.
// Richiede: una funzione db() o una variabile $conn definita da db.php

// === UTILITY BASE ============================================================
if (!function_exists('authz_db_column')) {
  function authz_db_column(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    $out = [];
    if ($params) {
      $st = $conn->prepare($sql);
      if (!$st) return $out;
      if ($types === '') {
        foreach ($params as $p) $types .= is_int($p) ? 'i' : 's';
      }
      $st->bind_param($types, ...$params);
      if (!$st->execute()) { $st->close(); return $out; }
      $res = $st->get_result();
      while ($r = $res->fetch_row()) $out[] = $r[0];
      $st->close();
    } else {
      $res = $conn->query($sql);
      if (!$res) return $out;
      while ($r = $res->fetch_row()) $out[] = $r[0];
    }
    return $out;
  }
}

// === ID E RUOLO UTENTE =======================================================
if (!function_exists('currentUserId')) {
  function currentUserId(): ?int {
    return isset($_SESSION['utente']['id']) ? (int)$_SESSION['utente']['id'] : null;
  }
}

if (!function_exists('currentUserIsAdminFallback')) {
  function currentUserIsAdminFallback(): bool {
    $ruolo = $_SESSION['utente']['ruolo'] ?? null;
    return $ruolo === 'admin';
  }
}

// === RUOLI ===================================================================
if (!function_exists('userRoles')) {
  function userRoles(?mysqli $conn, int $userId): array {
    $conn = $conn ?: (function_exists('db') ? db() : $GLOBALS['conn']);
    if (!$conn instanceof mysqli) return [];

    if (tableExists($conn, 'utente_ruoli') && tableExists($conn, 'ruoli')) {
      return authz_db_column(
        $conn,
        "SELECT r.slug
           FROM ruoli r
           JOIN utente_ruoli ur ON ur.ruolo_id = r.id
          WHERE ur.utente_id = ?",
        [$userId],
        'i'
      );
    }

    // Fallback: usa il vecchio campo ruolo in sessione
    $legacy = $_SESSION['utente']['ruolo'] ?? null;
    if ($legacy === 'admin') return ['admin'];
    if ($legacy) return [$legacy];
    return [];
  }
}

// === PERMESSI ================================================================
if (!function_exists('userPermissions')) {
  function userPermissions(?mysqli $conn, int $userId): array {
    $conn = $conn ?: (function_exists('db') ? db() : $GLOBALS['conn']);
    if (!$conn instanceof mysqli) return [];

    // Cache sessione
    if (isset($_SESSION['__perms_cache']) && is_array($_SESSION['__perms_cache'])) {
      return $_SESSION['__perms_cache'];
    }

    // Admin legacy: tutti i permessi
    if (currentUserIsAdminFallback()) {
      $_SESSION['__perms_cache'] = ['*'];
      return $_SESSION['__perms_cache'];
    }

    $perms = [];

    if (tableExists($conn, 'permessi')) {
      // Permessi via ruoli
      if (
        tableExists($conn, 'ruoli') &&
        tableExists($conn, 'ruolo_permessi') &&
        tableExists($conn, 'utente_ruoli')
      ) {
        $sqlRoles = "
          SELECT DISTINCT p.slug
          FROM permessi p
          JOIN ruolo_permessi rp ON rp.permesso_id = p.id
          JOIN utente_ruoli ur ON ur.ruolo_id = rp.ruolo_id
          WHERE ur.utente_id = ?
        ";
        $perms = authz_db_column($conn, $sqlRoles, [$userId], 'i');
      }

      // Permessi assegnati direttamente
      if (tableExists($conn, 'utente_permessi')) {
        $sqlUser = "
          SELECT DISTINCT p.slug
          FROM permessi p
          JOIN utente_permessi up ON up.permesso_id = p.id
          WHERE up.utente_id = ?
        ";
        $direct = authz_db_column($conn, $sqlUser, [$userId], 'i');
        $perms = array_values(array_unique(array_merge($perms, $direct)));
      }
    }

    $_SESSION['__perms_cache'] = $perms;
    return $perms;
  }
}

// === CHECK PERMESSI ==========================================================
if (!function_exists('currentUserCan')) {
  function currentUserCan(string $perm, ?mysqli $conn = null): bool {
    $uid = currentUserId();
    if (!$uid) return false;

    $perms = userPermissions($conn ?: (function_exists('db') ? db() : $GLOBALS['conn']), $uid);
    if (in_array('*', $perms, true)) return true;
    return in_array($perm, $perms, true);
  }
}

if (!function_exists('requirePermission')) {
  function requirePermission(string $perm): void {
    if (!currentUserCan($perm)) {
      http_response_code(403);
      echo "403 Permesso negato ({$perm})";
      exit;
    }
  }
}

// === CACHE ===================================================================
if (!function_exists('authzInvalidateCache')) {
  function authzInvalidateCache(): void {
    unset($_SESSION['__perms_cache']);
  }
}
