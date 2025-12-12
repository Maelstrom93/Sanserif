<?php
/**
 * genera_preventivo.php
 * Genera (o ristampa) il PDF del preventivo.
 * Utilizza la logica estratta in ../assets/funzioni/funzioni_preventivo.php
 */

session_start();
require_once '../assets/funzioni/funzioni.php';                // contiene requireLogin(), formattaData(), ecc.
require_once '../assets/funzioni/funzioni_preventivo.php';     // nuova logica
requireLogin();

require('../librerie/fpdf/fpdf.php');

// === Connessione DB ===
$conn = db_connect_preventivi();

// === Utility locali ===
function pdfText($s){
    return iconv('UTF-8','ISO-8859-1//TRANSLIT',(string)$s);
}

// === CARICAMENTO DATI: da ID (GET) oppure creazione da POST ===
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Ristampa di un preventivo esistente
    $d = prepara_stampa_da_id($conn, (int)$_GET['id']);
} else {
    // Creazione nuovo preventivo da POST + stampa
    $d = prepara_stampa_da_post($conn, $_POST);
}

// Variabili “comode” per il rendering
$numero           = $d['numero'];
$anno             = $d['anno'];
$data             = $d['data'];
$valido_fino      = $d['valido_fino'];
$pagamento        = $d['pagamento'];
$iva              = (float)$d['iva'];
$sconto           = (float)$d['sconto'];
$note             = $d['note'];
$totale           = (float)$d['totale'];
$totaleFinale     = (float)$d['totaleFinale'];
$scontoVal        = (float)$d['scontoVal'];
$ivaVal           = (float)$d['ivaVal'];
$righe            = $d['righe'];
$cliente          = $d['cliente'];
$cliente_nome     = $d['cliente_nome'];
$referente_custom = $d['referente_custom'] ?? '';

// === GENERA PDF ===
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// Colori brand
$brandHex = [0x00, 0x4c, 0x60];
$brandR = $brandHex[0]; $brandG = $brandHex[1]; $brandB = $brandHex[2];

// Logo e intestazione
$logoWebp = '../../img/logo.webp';
$logoPng  = 'temp_logo.png';
if (file_exists($logoWebp) && function_exists('imagecreatefromwebp')) {
    $img = imagecreatefromwebp($logoWebp);
    if ($img) {
        imagepng($img, $logoPng);
        imagedestroy($img);
    }
}
$pdf->SetFillColor($brandR, $brandG, $brandB);
$pdf->Rect(0, 0, 210, 28, 'F'); // fascia blu superiore
if (file_exists($logoPng)) $pdf->Image($logoPng, 12, 6, 30);

$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',14);
$pdf->SetXY(50,10);
$pdf->Cell(0,6,pdfText('PREVENTIVO COMMERCIALE'),0,2,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,pdfText('Data: '.formattaData($data).'   •   N. '.$numero.'/'.$anno),0,0,'L');

// Info cliente box
$pdf->Ln(18);
$pdf->SetTextColor(15,23,42);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,pdfText('Dettagli Cliente'),0,1);
$pdf->SetFont('Arial','',11);
$pdf->SetFillColor(242,248,250);
$pdf->SetDrawColor(229,231,235);
$pdf->MultiCell(
    0,
    7,
    pdfText(
        "Cliente: $cliente_nome\n" .
        (!empty($referente_custom) ? "Referente: $referente_custom\n" : (!empty($cliente['referente_1']) ? "Referente: ".$cliente['referente_1']."\n" : "")) .
        (!empty($cliente['telefono']) ? "Telefono: ".$cliente['telefono']."\n" : "") .
        (!empty($cliente['email']) ? "Email: ".$cliente['email']."\n" : "") .
        (!empty($cliente['indirizzo']) ? "Indirizzo: {$cliente['indirizzo']}, {$cliente['cap']} {$cliente['città']}\n" : "") .
        "Validità: ".formattaData($valido_fino)."\nPagamento: ".$pagamento
    ),
    0,
    'L',
    true
);
$pdf->Ln(8);

// Tabella righe
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(230,240,255);
$pdf->Cell(85,8,pdfText('Descrizione'),1,0,'C',true);
$pdf->Cell(25,8,pdfText('Quantità'),1,0,'C',true);
$pdf->Cell(40,8,pdfText('Prezzo (€)'),1,0,'C',true);
$pdf->Cell(40,8,pdfText('Totale (€)'),1,1,'C',true);

$pdf->SetFont('Arial','',11);
$fill=false;
foreach($righe as $r){
    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 251 : 255, $fill ? 252 : 255);
    $pdf->Cell(85,8,pdfText($r['descrizione']),1,0,'L',$fill);
    $pdf->Cell(25,8,pdfText($r['quantita']),1,0,'C',$fill);
    $pdf->Cell(40,8,pdfText(number_format((float)$r['prezzo_unitario'],2,',','.')),1,0,'R',$fill);
    $pdf->Cell(40,8,pdfText(number_format((float)$r['totale_riga'],2,',','.')),1,1,'R',$fill);
    $fill=!$fill;
}
$pdf->Ln(6);

// Totali
$pdf->SetDrawColor(200,200,200);
$pdf->SetFont('Arial','',11);
$pdf->Cell(150,8,pdfText('Totale parziale'),0,0,'R');
$pdf->SetFont('Arial','B',11);
$pdf->Cell(40,8,pdfText(number_format($totale,2,',','.').' €'),0,1,'R');

if($sconto>0){
    $pdf->SetFont('Arial','',11);
    $pdf->Cell(150,8,pdfText("Sconto ($sconto%)"),0,0,'R');
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(40,8,pdfText('- '.number_format($scontoVal,2,',','.').' €'),0,1,'R');
}

$pdf->SetFont('Arial','',11);
$pdf->Cell(150,8,pdfText("IVA ($iva%)"),0,0,'R');
$pdf->SetFont('Arial','B',11);
$pdf->Cell(40,8,pdfText(number_format($ivaVal,2,',','.').' €'),0,1,'R');

$pdf->Ln(2);
$pdf->SetDrawColor($brandR,$brandG,$brandB);
$pdf->SetLineWidth(0.5);
$pdf->Line(15,$pdf->GetY(),195,$pdf->GetY());
$pdf->Ln(4);

$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor($brandR,$brandG,$brandB);
$pdf->Cell(150,10,pdfText('Totale finale'),0,0,'R');
$pdf->Cell(40,10,pdfText(number_format($totaleFinale,2,',','.').' €'),0,1,'R');
$pdf->SetTextColor(15,23,42);

// Note
if(trim($note)!==''){
    $pdf->Ln(6);
    $pdf->SetFont('Arial','I',10);
    $pdf->SetFillColor(250,252,253);
    $pdf->MultiCell(0,6,pdfText("Note: ".$note),0,'L',true);
}

// Footer
$pdf->SetY(-18);
$pdf->SetFont('Arial','I',8);
$pdf->SetTextColor(120,130,140);
$pdf->Cell(0,8,pdfText('Generato da SansSerifSE • '.date('d/m/Y H:i')),0,0,'C');

// Output
$nome_file = 'preventivo_' . preg_replace('/\s+/', '_', strtolower($cliente_nome)) . '.pdf';
$pdf->Output('I',$nome_file);
