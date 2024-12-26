<?php
require('fpdf186/fpdf.php');

// Verificar si se recibieron datos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decodificar los datos enviados desde el formulario
    $emisor = json_decode($_POST['emisor'], true);
    $cliente = json_decode($_POST['cliente'], true);
    $comprobante = json_decode($_POST['comprobante'], true);
    $detalle = json_decode($_POST['detalle'], true);

    // Validar que las variables no estén vacías
    if (!$emisor || !$cliente || !$comprobante || !$detalle) {
        die('Error: Datos incompletos para generar el PDF.');
    }
} else {
    die('Error: Acceso no permitido.');
}


if($comprobante['tipodoc']=="01"){
    $imp="Factura";
    $co="RUC: ";
}
else{
    $imp="Boleta";
    $co="DNI: ";
}

// Configurar el PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);

// Título de la factura o boleta
$pdf->Cell(0, 10, $imp.' de venta Electronica', 0, 1, 'C');
$pdf->Ln(10);

// Información del emisor
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Emisor', 0, 1);
$pdf->Cell(100, 6, 'Razon Social:', 0, 0);
$pdf->Cell(0, 6, $emisor['razon_social'], 0, 1);
$pdf->Cell(100, 6, 'RUC:', 0, 0);
$pdf->Cell(0, 6, $emisor['nrodoc'], 0, 1);
$pdf->Cell(100, 6, 'Direccion:', 0, 0);
$pdf->Cell(0, 6, $emisor['direccion'], 0, 1);
$pdf->Ln(5);

// Información del cliente
$pdf->Cell(0, 6, 'Cliente', 0, 1);
$pdf->Cell(100, 6, $co, 0, 0);
$pdf->Cell(0, 6, $cliente['nrodoc'], 0, 1);
$pdf->Cell(100, 6, 'Direccion:', 0, 0);
$pdf->Cell(0, 6, $cliente['direccion'], 0, 1);
$pdf->Cell(100, 6, 'Fecha de emision:', 0, 0);
$pdf->Cell(0, 6, $comprobante['fecha_emision'], 0, 1);
$pdf->Ln(5);



// Detalles de productos
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(10, 6, 'Item', 1);
$pdf->Cell(70, 6, 'Descripcion', 1);
$pdf->Cell(30, 6, 'Cantidad', 1);
$pdf->Cell(30, 6, 'Precio U.', 1);
$pdf->Cell(30, 6, 'Total', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
foreach ($detalle as $item) {
    $pdf->Cell(10, 6, $item['item'], 1);
    $pdf->Cell(70, 6, $item['descripcion'], 1);
    $pdf->Cell(30, 6, $item['cantidad'], 1);
    $pdf->Cell(30, 6, number_format($item['precio_unitario'], 2), 1);
    $pdf->Cell(30, 6, number_format($item['importe_total'], 2), 1);
    $pdf->Ln();
}
$pdf->SetFont('Arial', 'B', 10);
$pdf->Ln(5);
$pdf->Cell(128, 6, 'Total Gravadas:', 0, 0, 'R'); // Texto alineado a la derecha
$pdf->Cell(28, 6, number_format($comprobante['total_opgravadas'], 2), 0, 1, 'R'); // Valor alineado a la derecha

$pdf->Cell(128, 6, 'IGV:', 0, 0, 'R'); // Texto alineado a la derecha
$pdf->Cell(28, 6, number_format($comprobante['igv'], 2), 0, 1, 'R'); // Valor alineado a la derecha

$pdf->Cell(128, 6, 'Total:', 0, 0, 'R'); // Texto alineado a la derecha
$pdf->Cell(28, 6, number_format($comprobante['total'], 2), 0, 1, 'R'); // Valor alineado a la derecha


$pdf->Output('I', 'Comprobante.pdf'); // Mostrar el PDF en el navegador
