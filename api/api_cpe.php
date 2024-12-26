<?php

class api_cpe
{
    function enviar_invoice($emisor, $nombreXML, $ruta_certificado, $ruta_archivo_xml, $ruta_archivo_cdr) {
        $estado_envio = 0; //inicia el proceso

        //FIRMAR DIGITALMENTE
        require_once('signature.php');
        $objFirma = new Signature();
        $flg_firma = 0; //indica la posicion de la etiqueta del XML donde ser firmará digitalmente
        $ruta_certificado = $ruta_certificado . 'certificado_prueba_sunat.pfx'; //debemos modificar cuando hagamos el pase a produccion
        $pass_certificado = 'ceti';
        $ruta_xml = $ruta_archivo_xml . $nombreXML . '.XML';
        $resp_hash = $objFirma->signature_xml($flg_firma, $ruta_xml, $ruta_certificado, $pass_certificado);
        $estado_envio = 1; 
        $estado_envio_mensaje = 'XML SE FIRMO DIGITALMENTE ' . date('Y-m-d hh:mn');

        //COMPRIMIR EN FORMATO ZIP
        $zip = new ZipArchive();
        $ruta_zip = $ruta_archivo_xml . $nombreXML . '.ZIP';
        if ($zip->open($ruta_zip, ZipArchive::CREATE) == TRUE) {
            $zip->addFile($ruta_xml, $nombreXML . '.XML');
            $zip->close();
        }
        $estado_envio = 2; 
        $estado_envio_mensaje = 'XML SE COMPRIMIÓ EN FORMATO ZIP ' . date('Y-m-d hh:mn');

        //CODIFICAR EN BASE64
        $zip_codificado = base64_encode(file_get_contents($ruta_zip));
        $estado_envio = 3; 
        $estado_envio_mensaje = 'ZIP DEL XML CODIFICADO EN BASE64 ' . date('Y-m-d hh:mn');

        //CONSUMO DE WEB SERVICES DE SUNAT - ARQUITECTUTA SOAP - XML
        //METODO USUADO: SENDBILL
        $filename_zip = $nombreXML . '.ZIP';

        //1. URL DE CONSUMO
        $ws_url = "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService";

        //2. SOAP PARA CONSUMIR EL SERVICIO
        $xml_envelope = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
        xmlns:ser="http://service.sunat.gob.pe" xmlns:wsse="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
            <soapenv:Header>
                <wsse:Security>
                    <wsse:UsernameToken>
                        <wsse:Username>' . $emisor['nrodoc'] . $emisor['usuario_secundario'] . '</wsse:Username>
                        <wsse:Password>' . $emisor['clave_usuario_secundario'] . '</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>
            </soapenv:Header>
            <soapenv:Body>
                <ser:sendBill>
                    <fileName>'. $filename_zip .'</fileName>
                    <contentFile>' . $zip_codificado . '</contentFile>
                </ser:sendBill>
            </soapenv:Body>
        </soapenv:Envelope>';

        //3. CURL
        //3.1. INICIAR EL CURL
        $ch = curl_init();

        //3.2 CONFIGURAR LAS OPCIONES DE CURL
        curl_setopt($ch, CURLOPT_URL, $ws_url); //SE INDICAR LA URL QUE SE VA A CONSUMIR
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //RETORNA O DA RPTA DEL XML
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_envelope); //METODO POST, ENVIAMOS EL SOPA-XMLENVELOPE

        //3.3 EJECUTAMOS EL CURL
        $output = curl_exec($ch); //ENVIAMOS EL XML A SUNAT Y RECIBIMOS RESPUESTA
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); //OBTENEMOS EL CODIGO DE RESPUESTA HTTP

        $estado_envio = 4; 
        $estado_envio_mensaje = 'SOAP PARA CONSUMIR EL SERVICIO SENDBILL ' . date('Y-m-d hh:mn');

        //RESPUESTA DE SUNAT
        $descripcion = ''; //Si sunat envia un mensaje, aprobando el documento
        $nota = ''; //Es cuando hay una obsevacionn
        $codigo_error = '';
        $mensaje_error = '';

        $doc = new DOMDocument();
        $doc->loadXML($output);//cargamos y convertimos en un documento XML la rpta de sunat

        if ($http_code == 200) { //ok
            if (isset($doc->getElementsByTagName('applicationResponse')->item(0)->nodeValue)) {
                //CDR: constancia de recepcion, enviado por sunat cuando el comprobante es aprobado
                $cdr = $doc->getElementsByTagName('applicationResponse')->item(0)->nodeValue;
                $estado_envio = 5; 
                $estado_envio_mensaje = 'OBTUVIMOS EL CDR CODIFICADO DE SUNAT ' . date('Y-m-d hh:mn');

                //DECODIFICAR
                $cdr = base64_decode($cdr);
                $estado_envio = 6; 
                $estado_envio_mensaje = 'CDR DECODIFICADO, OBTENEMOS EL ZIP ' . date('Y-m-d hh:mn');

                //COPIAR EN DISCO EL ZIP
                file_put_contents($ruta_archivo_cdr . 'R-' . $filename_zip, $cdr);
                $estado_envio = 7; 
                $estado_envio_mensaje = 'CDR EN FORMATO ZIP, FUÉ COPIADO A DISCO ' . date('Y-m-d hh:mn');

                //EXTRAER EL CONTENIDO DEL ZIP : XML(CDR)
                $zip = new ZipArchive();
                if ($zip->open($ruta_archivo_cdr . 'R-' . $filename_zip) == TRUE) {
                    $zip->extractTo($ruta_archivo_cdr);
                    $zip->close();
                }

                //VALIDACIONES SI EL COMPROBANTE HA SIDO ACEPTADO
                $xml_cdr = $ruta_archivo_cdr . 'R-' . $nombreXML . '.XML';
                $doc_cdr = new DOMDocument();
                $doc_cdr->load($xml_cdr);

                if (isset($doc_cdr->getElementsByTagName('Description')->item(0)->nodeValue)) {
                    $descripcion = $doc_cdr->getElementsByTagName('Description')->item(0)->nodeValue;
                }

                //VALIDACIONENS SI EXISTE OBSERVACIONES
                if (isset($doc_cdr->getElementsByTagName('Note')->item(0)->nodeValue)) {
                    $nota = $doc_cdr->getElementsByTagName('Note')->item(0)->nodeValue;
                }
                $estado_envio = 8; 
                $estado_envio_mensaje = 'PROCESO TERMINADO ' . date('Y-m-d hh:mn');
            }else{
                $codigo_error = $doc->getElementsByTagName('faultcode')->item(0)->nodeValue;
                $mensaje_error = $doc->getElementsByTagName('faultstring')->item(0)->nodeValue;
                $estado_envio = 9; 
                $estado_envio_mensaje = 'ERROR/RECHAZO DE SUNAT ' . date('Y-m-d hh:mn');
            }
        }else{ //problemas de consumo
            curl_error($ch);
            $estado_envio = 10; 
            $estado_envio_mensaje = 'ERROR/CONSUMO DEL WEB SERVICE/RED/CONEXION ' . date('Y-m-d hh:mn');
            $codigo_error = $doc->getElementsByTagName('faultcode')->item(0)->nodeValue;
            $mensaje_error = $doc->getElementsByTagName('faultstring')->item(0)->nodeValue;

            $output = 'ERROR/CONSUMO DEL WEB SERVICE/RED/CONEXION ' . date('Y-m-d hh:mn');
        }
        curl_close($ch);

        //creamos un array con todos los mensajes
        $estado_envio = array(
            'estado'                =>  $estado_envio,
            'estado_mensaje'        =>  $estado_envio_mensaje,
            'hash_cpe'              =>  $resp_hash['hash_cpe'],
            'descripcion'           =>  $descripcion,
            'nota'                  =>  $nota,
            'codigo_error'          =>  str_replace('soap-env:Client.','', $codigo_error),
            'mensaje_error'         =>  $mensaje_error,
            'http_code'             =>  $http_code,
            'output'                =>  $output
        );

        return $estado_envio;
    }
}

?>