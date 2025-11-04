<?php

function base_url(){
    return BASE_URL;
}

function media(){
    return BASE_URL.'/assets';
}

function dep($data)
{
    $format  = print_r('<pre>');
    $format .= print_r($data);
    $format .= print_r('</pre>');
    return $format;
}


function returnError($code, $message)
{
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit;
}

function returnSuccess($resp){
    echo json_encode(array('resp' => $resp, 'upd' => round(microtime(true)), 'status' => 200));
    die();
}

function validateField($field, $fieldName) {
    if(empty($field)){
       returnError(strtoupper($fieldName)." es requerido");
    }
}

function formatearTelefono($telefono) {
    $reemplazar = array('-', '.', ' ', '/', '(', ')');
    $telefono = str_replace($reemplazar, '', $telefono);

    if (!empty($telefono)) {
        if (substr($telefono, 0, 1) == '0') {
            $telefono = '+58' . substr($telefono, 1);
        }
        elseif (substr($telefono, 0, 2) == '58') {
            $telefono = '+' . $telefono;
        }
        elseif (substr($telefono, 0, 1) !== '+') {
            $telefono = '+58' . $telefono;
        }
    }

    if(substr($telefono,1,2) != '58'){
        returnError('Número no permitido, solo Número venezolanos');
    }

    $arrNumber = array('+584242533273','+584147091889');

    if(in_array($telefono, $arrNumber)){
        returnError('Número Bloqueado');
    }
    return $telefono;
}

function verificarHoraEnvio() {
    date_default_timezone_set("America/caracas");
    $hora = date("H");
    if(intval($hora) < 7 || intval($hora) > 19 ) {
        returnError('Hora no permitida para envio de mensajes');
    }
}


function convertToAssocArray($array) {
    $assocArray = [];
    foreach ($array as $item) {
        $parts = explode(":", $item);
        $key = trim($parts[0], '{}');
        $value = isset($parts[1]) ? trim($parts[1], '}') : '';
        $assocArray[strtoupper($key)] = $value;
    }
    return $assocArray;
}


?>