<?php 

class NotaCreditoModel extends Mysql {

    public function __construct($conectEnterprise) {  
        parent::__construct($conectEnterprise);
    }

    // =============================================================
    // VALIDAR QUE LA FACTURA ORIGEN EXISTE
    // =============================================================
    public function validarFacturaOrigen($numeroFacturaOrigen) {
        $sql = "SELECT * FROM adn_doccli 
                WHERE DCL_NUMERO = '$numeroFacturaOrigen'
                AND DCL_TIPTRA = 'D'";
        
        $request = $this->select_all($sql);
        return $request;
    }

    // =============================================================
    // VALIDAR QUE LOS MONTOS COINCIDAN CON LA FACTURA ORIGEN
    // =============================================================
    public function validarMontosNotaCredito($notacredito, $facturaOrigen) {
        $tolerancia = 0.01; // Tolerancia para comparación de decimales
        
        if (abs($notacredito->neto - $facturaOrigen[0]['DCL_NETO']) > $tolerancia) {
            throw new Exception("El monto neto de la nota de crédito no coincide con la factura origen.");
        }

        if (abs($notacredito->base_gravada - $facturaOrigen[0]['DCL_BASEG']) > $tolerancia) {
            throw new Exception("La base gravada de la nota de crédito no coincide con la factura origen.");
        }

        if (abs($notacredito->iva_gravado - $facturaOrigen[0]['DCL_IVAG']) > $tolerancia) {
            throw new Exception("El IVA gravado de la nota de crédito no coincide con la factura origen.");
        }

        if (abs($notacredito->monto_bruto - $facturaOrigen[0]['DCL_BRUTO']) > $tolerancia) {
            throw new Exception("El monto bruto de la nota de crédito no coincide con la factura origen.");
        }
    }

    // =============================================================
    // PROCESAR LA DEVOLUCIÓN COMPLETA
    // =============================================================
    public function procesarNotaCredito($notacredito, $moneda_base, $numeroFacturaOrigen) {
        
        // Validar que la factura origen existe
        $facturaOrigen = $this->validarFacturaOrigen($numeroFacturaOrigen);
        if (empty($facturaOrigen)) {
            throw new Exception("La factura origen N° {$numeroFacturaOrigen} no existe en el sistema.");
        }

        $preparedData = $this->prepararDatosTransaccionalesNotaCredito($notacredito, $moneda_base, $numeroFacturaOrigen, $facturaOrigen);
        $documentos = $preparedData['documentos'];

        // Validar que no exista la devolución
        foreach ($documentos as $docArray) {
            $numero = $docArray[0];
            $tipoCodigo = $docArray[1];
            $tipoTransaccion = $docArray[10];

            if ($this->_documentoExiste($numero, $tipoCodigo, $tipoTransaccion)) {
                throw new Exception("La nota de crédito N° {$numero} (Tipo: {$tipoCodigo}, Transacción: {$tipoTransaccion}) ya existe en la base de datos.");
            }
        }

        // Procesar en transacción
       // $this->beginTransaction();
        try {
            // Insertar documentos
            $this->_bulkInsertDocumentos($documentos);

            //$this->commit();
        } catch (Exception $e) {
            //$this->rollBack();
            throw $e;
        }
    }

        // =============================================================
    // MÉTODOS AUXILIARES REUTILIZADOS DE DOCUMENTOMODEL
    // =============================================================
    private function _documentoExiste($numero, $tipo, $transaccion) {
        $sql = "SELECT COUNT(*) as count FROM adn_doccli 
                WHERE DCL_NUMERO = '$numero' AND DCL_TDT_CODIGO = '$tipo' AND DCL_TIPTRA = '$transaccion'";
        $request = $this->select($sql);
        return $request['count'] > 0;
    }
    
    // =============================================================
    // PREPARAR DATOS TRANSACCIONALES PARA DEVOLUCIÓN
    // =============================================================
    private function prepararDatosTransaccionalesNotaCredito($notacredito, $moneda_base, $numeroFacturaOrigen, $facturaOrigen) {
        
        // Obtener el tipo de documento origen
        $tipoDocumentoOrigen = $facturaOrigen[0]['DCL_TDT_CODIGO']; // 'FAV' o 'NEN'

        $documentos = [];
        
        // Determinar el tipo de documento principal basado en tipo_documento de la factura
        $tipoDocPrincipal = $notacredito->tipo_documento;
        $documentos[] = $this->_prepararArrayDocumentoNotaCredito($notacredito, $tipoDocPrincipal, 'D', $moneda_base, $numeroFacturaOrigen, $tipoDocumentoOrigen);

		
		if($notacredito->empresa !== 'CRM'){
		
        	// Documento FAV$/NEN$ (D) si moneda base es BS
			if ($moneda_base == 'BS') {
				// Determinar el tipo de documento en dólares
				$tipoDocDolar = 'CREV$';
				
				$documentos[] = $this->_prepararArrayDocumentoNotaCredito($notacredito, $tipoDocDolar, 'D', 'USD', $numeroFacturaOrigen, $tipoDocumentoOrigen, [
					'brutoUsd' => $notacredito->monto_bruto / $notacredito->valor_cambiario_dolar,
					'netoUsd' => $notacredito->neto / $notacredito->valor_cambiario_dolar,
					'baseGravadoUsd' => $notacredito->base_gravada / $notacredito->valor_cambiario_dolar,
					'exentoUsd' => $notacredito->exento / $notacredito->valor_cambiario_dolar,
					'ivaGravadoUsd' => $notacredito->iva_gravado / $notacredito->valor_cambiario_dolar,
					'baseIgtfUsd' => $notacredito->base_igtf / $notacredito->valor_cambiario_dolar
				]);
			}
		}
		
        return ['documentos' => $documentos];
    }

    // =============================================================
    // PREPARAR ARRAY DE DOCUMENTO PARA DEVOLUCIÓN
    // =============================================================
    private function _prepararArrayDocumentoNotaCredito($notacredito, $tipo_documento_procesar, $tipo_doc, $base_moneda, $numeroFacturaOrigen, $tipoDocumentoOrigen, $overrides = []) {
        
        $fechaVencimiento = date("Y-m-d", strtotime("{$notacredito->fecha} + {$notacredito->plazo} days"));
        $cxc = '-1';
        $tdtOrigen = ($tipo_documento_procesar === 'CREV') ? $tipoDocumentoOrigen : $notacredito->tipo_documento;

        if ($tipo_documento_procesar === 'CREV' || $tipo_documento_procesar === 'CREV$') {

            $origen = ($tipo_documento_procesar === 'CREV') ? "{$tipoDocumentoOrigen}:{$numeroFacturaOrigen}" : "{$notacredito->tipo_documento}:{$notacredito->numero}";
            $SistemOrigen = 'MOD';
            $cxc = ($base_moneda === 'USD') ? "-1" : "0";
        }

        $moneda = ($tipo_documento_procesar === 'CREV') ? $notacredito->moneda : 'USD';
        $neto_usd = ($tipo_documento_procesar === 'CREV') ? $notacredito->neto_usd : $notacredito->neto;
        $base_gravada_usd = ($tipo_documento_procesar === 'CREV') ? $notacredito->base_gravada_usd : $notacredito->base_gravada;
        $exento_usd = ($tipo_documento_procesar === 'CREV') ? $notacredito->exento_usd : $notacredito->exento;
        $iva_gravado_usd = ($tipo_documento_procesar === 'CREV') ? $notacredito->iva_gravado_usd : $notacredito->iva_gravado;

        return [
            $notacredito->numero, $tipo_documento_procesar, $overrides['referencia'] ?? '', $notacredito->vendedor->codigo, $notacredito->cliente->codigo, $notacredito->fecha, 
            $this->sanitizeNumber($overrides['netoUsd'] ?? $notacredito->neto), $this->sanitizeNumber(($overrides['baseGravadoUsd'] ?? $notacredito->base_gravada)), 
            $this->sanitizeNumber(($overrides['exentoUsd'] ?? $notacredito->exento)), $notacredito->serie_fiscal, $tipo_doc, $cxc, $notacredito->activo, $notacredito->estado_documento, 
            $this->sanitizeNumber($notacredito->descuento_porcentual ?? 0), "{$notacredito->fecha} {$notacredito->hora}", $notacredito->numero_impresion_fiscal, 
            $this->sanitizeNumber(($overrides['ivaGravadoUsd'] ?? $notacredito->iva_gravado)), $notacredito->hora, $notacredito->plazo, $notacredito->condicion, $fechaVencimiento, $tdtOrigen, $origen, '01', 
            $this->sanitizeNumber(($overrides['brutoUsd'] ?? $notacredito->monto_bruto)), $notacredito->usuario, $notacredito->estacion, $notacredito->ip, $SistemOrigen, $notacredito->sucursal->codigo,
            $this->sanitizeNumber($notacredito->valor_cambiario_dolar), $moneda, $this->sanitizeNumber($notacredito->valor_cambiario_peso), $this->sanitizeNumber($neto_usd), 
            $this->sanitizeNumber($base_gravada_usd), $this->sanitizeNumber($exento_usd), $this->sanitizeNumber($iva_gravado_usd), 
            $this->sanitizeNumber(($overrides['baseIgtfUsd'] ?? $notacredito->base_igtf))
        ];
    }

    // Métodos de inserción masiva (reutilizados de DocumentoModel)
    private function _bulkInsertDocumentos($data) {
        if (empty($data)) return;

        $columnas = "DCL_NUMERO, DCL_TDT_CODIGO, DCL_REC_NUMERO, DCL_VEN_CODIGO, DCL_CLT_CODIGO, DCL_FECHA, DCL_NETO, DCL_BASEG, DCL_EXENTO, 
                    DCL_SERFIS, DCL_TIPTRA, DCL_CXC, DCL_ACTIVO, DCL_STD_ESTADO, DCL_PORDESC, DCL_FECHAHORA, DCL_NUMFIS, DCL_IVAG, DCL_HORA,
                    DCL_PLAZO, DCL_CONDICION, DCL_FECHAVEN, DCL_TDT_ORIGEN, DCL_ORIGENNUM, DCL_IDCAJA, DCL_BRUTO, DCL_USUARIO, DCL_ESTACION, DCL_IP, DCL_ORIGEN,
                    DCL_CCT_CODIGO, DCL_VALORCAM, DCL_MONEDA, DCL_VALORCAM2, DCL_NETOUSD, DCL_BASEGUSD, DCL_EXENTOUSD, DCL_IVAGUSD, DCL_IGTF";

        $valueStrings = [];
        foreach ($data as $fila) {
            $sanitizedValues = array_map([$this->conexion, 'quote'], $fila);
            $valueStrings[] = "(" . implode(',', $sanitizedValues) . ")";
        }

        $this->insert_massive("INSERT INTO adn_doccli ($columnas) VALUES " . implode(', ', $valueStrings));
    }

    private function sanitizeNumber($value) {
        return floatval(str_replace(',', '.', $value));
    }
}
?>
