<?php
// api/lista_lavori.php
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
if (!isLogged()) { http_response_code(401); echo 'Non autenticato'; exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function money_it($v){ if ($v===null||$v==='') return ''; return number_format((float)$v,2,',','.').' €'; }

$myId = isset($_SESSION['utente']['id']) ? (int)$_SESSION['utente']['id'] : 0;

/* ====== filtri come index_lavori ====== */
$qTitolo = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$fUtente = (isset($_GET['utente_id']) && $_GET['utente_id']!=='') ? (int)$_GET['utente_id'] : null;
$fCliente= (isset($_GET['cliente_id']) && $_GET['cliente_id']!=='') ? (int)$_GET['cliente_id'] : null;
$fCat    = (isset($_GET['categoria_id']) && $_GET['categoria_id']!=='') ? (int)$_GET['categoria_id'] : null;
$fDa     = isset($_GET['scad_da']) ? trim((string)$_GET['scad_da']) : '';
$fA      = isset($_GET['scad_a'])  ? trim((string)$_GET['scad_a']) : '';
$onlyClosed = (isset($_GET['stato']) && $_GET['stato']==='chiusi');
$assignedToMe = ($myId>0) && (isset($_GET['assigned_to_me']) && $_GET['assigned_to_me']=='1');
$applyAssigneeFilter = !$onlyClosed && ($assignedToMe || $fUtente!==null);

/* sort */
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'ricezione_desc';
$sortMap = [
  'ricezione_desc' => 'l.id DESC',              // equivalente al tuo ordine corrente
  'scadenza_asc'   => 'l.scadenza IS NULL, l.scadenza ASC, l.id DESC',
  'prezzo_desc'    => 'l.prezzo IS NULL, l.prezzo DESC, l.id DESC',
  'titolo_asc'     => 'l.titolo ASC, l.id DESC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['ricezione_desc'];

/* pagina */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(30, max(6, (int)($_GET['per_page'] ?? 9)));
$offset  = ($page-1)*$perPage;

/* build where */
$where=[]; $bind=[]; $types='';
if ($qTitolo!==''){ $where[]="l.titolo LIKE ?"; $bind[]='%'.$qTitolo.'%'; $types.='s'; }
if ($fCliente!==null){ $where[]="l.cliente_id = ?"; $bind[]=$fCliente; $types.='i'; }
if ($fCat!==null){
  if (tableExists($conn,'lavori_categorie') && tableExists($conn,'lavori_attivita')){
    $where[]="(EXISTS(SELECT 1 FROM lavori_categorie lc WHERE lc.lavoro_id=l.id AND lc.categoria_id=?)
            OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id=l.id AND la.categoria_id=?))";
    $bind[]=$fCat; $types.='i'; $bind[]=$fCat; $types.='i';
  } elseif (tableExists($conn,'lavori_categorie')){
    $where[]="EXISTS(SELECT 1 FROM lavori_categorie lc WHERE lc.lavoro_id=l.id AND lc.categoria_id=?)";
    $bind[]=$fCat; $types.='i';
  } elseif (tableExists($conn,'lavori_attivita')){
    $where[]="EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id=l.id AND la.categoria_id=?)";
    $bind[]=$fCat; $types.='i';
  }
}
if ($applyAssigneeFilter){
  $uid = $assignedToMe ? $myId : $fUtente;
  if ($uid!==null){
    if (tableExists($conn,'lavori_attivita')){
      $where[]="(l.assegnato_a=? OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id=l.id AND la.utente_id=?))";
      $bind[]=$uid; $types.='i'; $bind[]=$uid; $types.='i';
    } else { $where[]="l.assegnato_a=?"; $bind[]=$uid; $types.='i'; }
  }
}
if ($fDa!=='' || $fA!==''){
  if (tableExists($conn,'lavori_attivita')){
    if ($fDa!==''){ $where[]="((l.scadenza>=?) OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id=l.id AND la.scadenza>=?))"; $bind[]=$fDa; $types.='s'; $bind[]=$fDa; $types.='s'; }
    if ($fA!==''){  $where[]="((l.scadenza<=?) OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id=l.id AND la.scadenza<=?))"; $bind[]=$fA;  $types.='s'; $bind[]=$fA;  $types.='s'; }
  } else {
    if ($fDa!==''){ $where[]="l.scadenza>=?"; $bind[]=$fDa; $types.='s'; }
    if ($fA!==''){  $where[]="l.scadenza<=?"; $bind[]=$fA;  $types.='s'; }
  }
}
if ($onlyClosed){ $where[]="(l.stato IN ('completato','chiuso'))"; }
$WHERE = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$joinCliente = tableExists($conn,'clienti') ? "
  , (SELECT TRIM(COALESCE(nome,".(tableHasColumn($conn,'clienti','rgs')?'rgs, ':'')." CONCAT('Cliente #', c.id)))
       FROM clienti c WHERE c.id = l.cliente_id LIMIT 1) AS cliente_nome" : "";

$joinCategorie = (tableExists($conn,'lavori_categorie') && tableExists($conn,'categorie_libri')) ? "
  , (SELECT GROUP_CONCAT(DISTINCT cl.nome ORDER BY cl.nome SEPARATOR ', ')
       FROM lavori_categorie lc JOIN categorie_libri cl ON cl.id = lc.categoria_id
      WHERE lc.lavoro_id = l.id) AS categorie" : "";

$aggAtt = tableExists($conn,'lavori_attivita') ? "
  , (SELECT COUNT(*) FROM lavori_attivita la WHERE la.lavoro_id=l.id) AS att_count
  , (SELECT COUNT(DISTINCT la.utente_id) FROM lavori_attivita la WHERE la.lavoro_id=l.id AND la.utente_id IS NOT NULL) AS assignees
  , (SELECT CAST(SUM(la.prezzo) AS DECIMAL(10,2)) FROM lavori_attivita la WHERE la.lavoro_id=l.id) AS sum_righe
" : ", 0 AS att_count, 0 AS assignees, NULL AS sum_righe";

$sql = "
  SELECT l.id, l.titolo, l.cliente_id, l.assegnato_a, l.data_ricezione, l.scadenza, l.prezzo, l.stato, l.descrizione, l.cartelle_json
    $joinCliente
    $joinCategorie
    $aggAtt
  FROM lavori l
  $WHERE
  ORDER BY $orderSql
  LIMIT $perPage OFFSET $offset
";

/* run */
function runQ(mysqli $c,string $sql,string $t,array $b){ if($b){$st=$c->prepare($sql);$st->bind_param($t,...$b);$st->execute();$r=$st->get_result();$st->close();return $r;} return $c->query($sql); }
$res = runQ($conn,$sql,$types,$bind);

/* render HTML card identica a index */
if ($res) {
  while($r=$res->fetch_assoc()){
    $id=(int)$r['id']; $tit=(string)$r['titolo']; $cli=(string)($r['cliente_nome']??''); $st=(string)($r['stato']??'');
    $cats=(string)($r['categorie']??''); $ricev=(string)($r['data_ricezione']??''); $scadRaw=(string)($r['scadenza']??'');
    $prz=($r['prezzo']!==null&&$r['prezzo']!=='')?(float)$r['prezzo']:null; $sumRig=($r['sum_righe']!==null&&$r['sum_righe']!=='')?(float)$r['sum_righe']:null;
    $attC=(int)($r['att_count']??0); $assC=(int)($r['assignees']??0); $totShow = $prz!==null ? $prz : $sumRig;
    $scadAttr = $scadRaw ? e($scadRaw) : '';
    echo '<article class="job-card" data-archivio data-scadenza="'.$scadAttr.'">';
    echo '  <div class="head">';
    echo '    <h4 class="title">'.e($tit).'</h4>';
    echo '    <span class="badge state-'.e($st?:'aperto').'">'.e($st?:'aperto').'</span>';
    echo '  </div>';
    echo '  <div class="body">';
    echo '    <div class="meta">'.($cli?'<i class="fa-regular fa-user"></i> '.e($cli).' &nbsp;&middot;&nbsp;':'').($cats?'<i class="fa-solid fa-tags"></i> '.e($cats):'').'</div>';
    echo '    <div class="meta">'.($ricev?'<i class="fa-regular fa-inbox"></i> Ricevuto: '.e(formattaData($ricev)):'').($scadRaw?' &nbsp;&middot;&nbsp; <i class="fa-regular fa-calendar"></i> Scadenza: '.e(formattaData($scadRaw)):'').($totShow!==null?' &nbsp;&middot;&nbsp; <i class="fa-solid fa-euro-sign"></i> '.e(money_it($totShow)):'').'</div>';
    echo '    <div class="meta"><i class="fa-regular fa-list-check"></i> '.$attC.' attività &nbsp;&middot;&nbsp; <i class="fa-regular fa-user"></i> '.$assC.' assegnatari <span class="sla chip sm" aria-label="SLA scadenza"></span></div>';
    echo '    <div class="actions">';
    echo '      <a class="chip open-view" href="#" data-id="'.$id.'"><i class="fa-regular fa-eye"></i> Dettagli</a>';
    echo '      <a class="chip open-edit" href="#" data-id="'.$id.'"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>';
    echo '      <button class="chip danger js-del-job" type="button" data-id="'.$id.'" data-title="'.e($tit).'"><i class="fa-regular fa-trash-can"></i> Elimina</button>';
    echo '    </div>';
    echo '  </div>';
    echo '</article>';
  }
}
