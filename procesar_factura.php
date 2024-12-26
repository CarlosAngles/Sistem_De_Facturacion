<?php
// Obtener los datos del formulario
$emisor = array(
 
    'tipodoc'                   =>  '6',
    'nrodoc' => $_POST['nrodoc_emisor'],
    'razon_social' => $_POST['razon_social_emisor'],
    'nombre_comercial' => $_POST['nombre_comercial_emisor'],
    'direccion' => $_POST['direccion_emisor'],
    'ubigeo'                    =>  '130101',
    'departamento'       => $_POST['departamento_emisor'],
    'provincia'          => $_POST['provincia_emisor'],
    'distrito'            => $_POST['distrito_emisor'],
    'pais'                      =>  'PE',
    'usuario_secundario'        =>  'MODDATOS',
    'clave_usuario_secundario'  =>  'MODDATOS'
);

$cliente = array(
    'tipodoc' => $_POST['tipodoc_cliente'],
    'nrodoc' => $_POST['nrodoc_cliente'],
    'razon_social' => 'CLIENTE CON ' . $_POST['tipodoc_cliente'],
    'direccion'                 =>  'VIRTUAL',
    'pais' => 'PE',
);
if($_POST['tipodoc_comprobante']=='01'){
    $comp='FACT';
}
else {
    $comp='B001';
}
$comprobante = array(
    'tipodoc'                   =>  $_POST['tipodoc_comprobante'],
    'serie'                     => $comp,
    'correlativo'               =>  1,
    'fecha_emision'             =>  $_POST['fecha_emision'],  // La fecha de emisión del formulario
    'hora'                      =>  $_POST['hora_emision'],
    'fecha_vencimiento'         =>  date('Y-m-d'),
    'moneda'                    =>  'PEN',
    'total_opgravadas'          =>  0.00,
    'total_opexoneradas'        =>  0.00,
    'total_opinafectas'         =>  0.00,
    'total_impbolsas'           =>  0.00,
    'total_opgratuitas1'        =>  0.00,
    'total_opgratuitas2'        =>  0.00,
    'igv'                       =>  0.00,
    'total'                     =>  0.00,
    'total_texto'               =>  '',
    'forma_pago'                =>  'Contado', 
    'monto_pendiente'           =>  0 //Contado: 0 y no hay cuotas
);

// Detalles de productos
$detalle = [];


foreach ($_POST['producto_descripcion'] as $key => $descripcion) {
    $precio_unitario = $_POST['producto_precio_unitario'][$key]; 
    $cantidad = $_POST['producto_cantidad'][$key]; 
    $tipo_afectacion_igv = $_POST['Tipo_afectacion'][$key]; 

    $codigo = 'PRO' . str_pad($key + 1, 3, '0', STR_PAD_LEFT); 


    if($tipo_afectacion_igv=='10'){
        $igv = 18; 

    $valor_unitario = $precio_unitario / (1 + ($igv / 100));
    $nt="IGV";
    $tt="VAT";
    $CTT="1000";
    }
    else if($tipo_afectacion_igv=='20'){
        $igv = 0;
        $valor_unitario = $precio_unitario ;
        $CTT="9997";
        $nt="EXO";
        $tt="VAT";
    }
    else if($tipo_afectacion_igv=='30'){
        $igv = 0;
        $valor_unitario = $precio_unitario;
        $CTT="9998";
        $nt="INA";
        $tt="FRE";
    }



    $base_imponible = $cantidad * $valor_unitario;


    $monto_igv = $base_imponible * ($igv / 100);

    $importe_total = $base_imponible + $monto_igv;


    $detalle[] = array(
        'item'                      => $key + 1, // Número de ítem
        'codigo'                    => $codigo, // Código del producto generado
        'descripcion'               => $descripcion, // Descripción
        'cantidad'                  => $cantidad, // Cantidad
        'precio_unitario'           => round($precio_unitario, 2), 
        'valor_unitario'            => round($valor_unitario, 2), 
        'igv'                       => round($monto_igv, 2), 
        'tipo_precio'               => '01', 
        'porcentaje_igv'            => $igv, 
        'importe_total'             => round($importe_total, 2),
        'valor_total'               => round($base_imponible, 2), 
        'unidad'                    => 'NIU', 
        'bolsa_plastica'            => 'NO', // Sin impuesto ICBPER
        'total_impuesto_bolsas'     => 0.00, 
        'tipo_afectacion_igv'       => $tipo_afectacion_igv, 
        'codigo_tipo_tributo'       => $CTT, 
        'tipo_tributo'              => $tt, 
        'nombre_tributo'            => $nt, 
    );
}


echo '<pre>';
print_r($detalle);
echo '</pre>';





//inicializar varibles totales
$total_opgravadas = 0.00;
$total_opexoneradas = 0.00;
$total_opinafectas = 0.00;
$total_opimpbolsas = 0.00;
$total = 0.00;
$igv = 0.00;
$op_gratuitas1 = 0.00;
$op_gratuitas2 = 0.00;

foreach ($detalle as $key => $value) {
    
    if ($value['tipo_afectacion_igv'] == 10) { //op gravadas
        $total_opgravadas += $value['valor_total'];
    }

    if ($value['tipo_afectacion_igv'] == 20) { //op exoneradas
        $total_opexoneradas += $value['valor_total'];
    }

    if ($value['tipo_afectacion_igv'] == 30) { //op inafectas
        $total_opinafectas += $value['valor_total'];
    }

    $igv += $value['igv'];
    $total_opimpbolsas = $value['total_impuesto_bolsas'];
    $total += $value['importe_total'] + $total_opimpbolsas;
}

$comprobante['total_opgravadas'] = $total_opgravadas;
$comprobante['total_opexoneradas'] = $total_opexoneradas;
$comprobante['total_opinafectas'] = $total_opinafectas;
$comprobante['total_impbolsas'] = $total_opimpbolsas;
$comprobante['total_opgratuitas_1'] = $op_gratuitas1;
$comprobante['total_opgratuitas_2'] = $op_gratuitas2;
$comprobante['igv'] = $igv;
$comprobante['total'] = $total;

require_once('cantidad_en_letras.php');
$comprobante['total_texto'] = CantidadEnLetra($total);

//PARTE 1: CREAR EL XML DE FACTURA
require_once('./api/api_genera_xml.php');
$obj_xml = new api_genera_xml();

//nombre del XML segun SUNAT
$nombreXML = $emisor['nrodoc'] . '-' . $comprobante['tipodoc'] . '-' . $comprobante['serie'] . '-' . $comprobante['correlativo'];
$rutaXML = 'xml/';

$obj_xml->crea_xml_invoice($rutaXML . $nombreXML, $emisor, $cliente, $comprobante, $detalle);

echo '</br> PARTE 01: XML DE FACTURA CREADO SATISFACTORIAMENTE';

//PARTE 2: ENVIO CPE A SUNAT
require_once('./api/api_cpe.php');
$objEnvio = new api_cpe();
$estado_envio = $objEnvio->enviar_invoice($emisor, $nombreXML, 'certificado_digital/', 'xml/', 'cdr/');

echo '</br> PARTE 2: ENVIO CPE-SUNAT';
echo '</br> Estado de envío: ' . $estado_envio['estado'];
echo '</br> Mensaje: ' . $estado_envio['estado_mensaje'];
echo '</br> HASH_CPE: ' . $estado_envio['hash_cpe'];
echo '</br> Descripción: ' . $estado_envio['descripcion'];
echo '</br> Nota: ' . $estado_envio['nota'];
echo '</br> Código de error: ' . $estado_envio['codigo_error'];
echo '</br> Mensaje de error: ' . $estado_envio['mensaje_error'];
echo '</br> HTTP CODE: ' . $estado_envio['http_code'];
echo '</br> OUTPUT: ' . $estado_envio['output'];


// Agrega el formulario para generar el PDF
?>
<form method="post" action="generate_pdf.php">
    <!-- Incluye los datos necesarios como campos ocultos -->
    <input type="hidden" name="emisor" value='<?php echo json_encode($emisor); ?>'>
    <input type="hidden" name="cliente" value='<?php echo json_encode($cliente); ?>'>
    <input type="hidden" name="comprobante" value='<?php echo json_encode($comprobante); ?>'>
    <input type="hidden" name="detalle" value='<?php echo json_encode($detalle); ?>'>

    <button type="submit" style="
        background-color: #007bff; 
        color: white; 
        border: none; 
        padding: 10px 20px; 
        border-radius: 5px; 
        font-size: 16px; 
        cursor: pointer; 
        transition: background-color 0.3s ease;
    " 
    onmouseover="this.style.backgroundColor='#0056b3';" 
    onmouseout="this.style.backgroundColor='#007bff';">
        Generar PDF
    </button>
</form>
