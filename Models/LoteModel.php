<?php 

class LoteModel extends Mysql {

    public function __construct($conectEnterprise) {  

        parent::__construct($conectEnterprise);
    }

    // =============================================================
    // MÉTODO PARA PROCESAR TODOS LOS DATOS MAESTROS DEL LOTE
    // =============================================================
    public function procesarMaestrosEnLote(LoteDataCollector $data) {
        //$this->beginTransaction();

        try {
            // Aquí va toda la lógica de inserción masiva de maestros
            $this->_bulkInsertOrUpdateSimple('adn_marcas', ['MAR_CODIGO', 'MAR_DESCRIPCION'], $data->marcas);
            $this->_bulkInsertOrUpdateSimple('adn_departamentos', ['DEP_CODIGO', 'DEP_DESCRIPCION'], $data->departamentos);
            $this->_bulkInsertOrUpdateSimpleGrupo('adn_grupos', ['GPO_CODIGO', 'GPO_DESCRIPCION', 'GPO_FACC_CTA_CODIGO', 'GPO_DEVC_CTA_CODIGO',
                                            'GPO_FAVV_CTA_CODIGO', 'GPO_DEVV_CTA_CODIGO', 'GPO_ACTIVO_CTA_CODIGO'], $data->grupos);

            $this->_bulkInsertOrUpdateSimple('adn_categorias', ['CAT_CODIGO', 'CAT_DESCRIPCION'], $data->categorias);
            $this->_bulkInsertOrUpdateSimple('adn_versiones', ['VER_CODIGO', 'VER_DESCRIPCION'], $data->versiones);
            $this->_bulkInsertOrUpdateSimpleAlmacen('adn_almacenes', ['AMC_CODIGO', 'AMC_NOMBRE', 'AMC_ACTIVO', 'AMC_LPT', 'AMC_TIPO'], $data->almacenes);

            $this->_bulkInsertOrUpdateSimpleSerieFiscal('adn_seriefiscal', ['SFI_MODELO', 'SFI_SERIE'], $data->seriesFiscales);

            // --- Fase B: Insertar maestros que son un poco más complejos ---
            $this->_bulkInsertOrUpdateClientes($data->clientes);
            $this->_bulkInsertOrUpdateVendedores($data->vendedores);

            if (!empty($data->vehiculos)) {
                $this->_bulkInsertOrUpdateVehiculos($data->vehiculos);
            }

            if (!empty($data->pacientes)) {
                $this->_bulkInsertOrUpdatePacientes($data->pacientes);
            }

            // --- Fase C: Insertar maestros dependientes (Productos dependen de marcas, deptos, etc.) ---
            $this->_bulkInsertOrUpdateProductos($data->productos);
            $this->_bulkInsertUndagruLogic($data);

            // --- Fase D: Insertar Bancos ---
            $this->_bulkInsertOrUpdateBancos($data->bancos);

            //$this->commit();
        } catch (Exception $e) {
            //$this->rollBack();
            throw $e; // Si los maestros fallan, todo el lote falla.
        }
    }
    
    // =============================================================
    // MÉTODO PARA PROCESAR UNA ÚNICA FACTURA CON SUS DOCUMENTOS
    // =============================================================
    public function procesarFacturaIndividual($factura, $moneda_base, $empresa) {

        // 1. Preparamos los datos de los documentos para esta factura
        $preparedData = $this->prepararDatosTransaccionalesFactura($factura, $moneda_base, $empresa);
        $documentos = $preparedData['documentos'];
        $movimientos = $preparedData['movimientos'];
        $recibo = $preparedData['recibo'];
        $movimientosRecibos = $preparedData['movimientosRecibos'];
        $documentoRecibo = $preparedData['documentoRecibo'];
        $documentosIgtf = $preparedData['documentosIgtf'];

        // --- PASO 1: VALIDACIÓN DE EXISTENCIA (TU LÓGICA ORIGINAL) ---
        // Antes de intentar cualquier inserción, validamos cada documento que se va a crear.
        foreach ($documentos as $docArray) {
            // Extraemos los datos clave del array preparado
            $numero = $docArray[0];         // DCL_NUMERO
            $tipoCodigo = $docArray[1];    // DCL_TDT_CODIGO (PED, FAV, FAV$)
            $tipoTransaccion = $docArray[10]; // DCL_TIPTRA (D, P)

            if ($this->_documentoExiste($numero, $tipoCodigo, $tipoTransaccion)) {
                // Si el documento ya existe, lanzamos un error específico.
                // El controlador lo capturará y marcará esta factura como fallida.
                throw new Exception("El documento N° {$numero} (Tipo: {$tipoCodigo}, Transacción: {$tipoTransaccion}) ya existe en la base de datos.");
            }
        }

        // --- PASO 2: VALIDACIÓN DE EXISTENCIA DE MOVIMIENTOS (NUEVO) ---
        // Validamos todos los movimientos de esta factura en una sola consulta.
        if (!empty($movimientos)) {
            $duplicado = $this->_movimientosExisten($movimientos);
            if ($duplicado) {
                // Si la función devuelve un duplicado, lanzamos un error.
                throw new Exception("El movimiento para el producto '{$duplicado['producto']}' con cantidad '{$duplicado['cantidad']}' ya existe para este documento.");
            }
        }

        // --- PASO 3: VALIDACIÓN DE EXISTENCIA DE RECIBO ---
        if (!empty($recibo)) {
            $numeroRecibo = $recibo[0][0][0] ?? null;
            $duplicado = $this->_reciboExiste($numeroRecibo);
            if ($duplicado) {
                // Si la función devuelve un duplicado, lanzamos un error.
                throw new Exception("El recibo con número '{$numeroRecibo}' ya existe en la base de datos.");
            }
        }

        // --- PASO 4: VALIDACIÓN DE MOVIMIENTOS DE RECIBO ---
        if (!empty($movimientosRecibos)) {
            $duplicadoRecibo = $this->_movimientosReciboExisten($movimientosRecibos);

            if ($duplicadoRecibo) {
                throw new Exception("El movimiento del recibo: '{$duplicadoRecibo['MBC_NUMERO']}' del recibo '{$duplicadoRecibo['MBC_REC_NUMERO']}' ya existe.");
            }
        }

        // --- PASO 5: VALIDACIÓN DE DOCUMENTOS IGTFV ---
        if (!empty($documentosIgtf)) {
            foreach ($documentosIgtf as $docIgtf) {

                $numero = $docIgtf[0] ?? null;       // DCL_NUMERO
                $tipTra = $docIgtf[10] ?? 'D';       // DCL_TIPTRA

                if ($numero && $this->_documentoIgtfExiste($numero, $tipTra)) {
                    throw new Exception("El documento IGTFV con número '{$numero}' y tipo transacción '{$tipTra}' ya existe en la base de datos.");
                }
            }
        }

        // --- PASO 6: PERSISTENCIA (Si todas las validaciones pasaron) ---
        try {
            //$this->beginTransaction();
            // Insertar documentos principales (PED, FAV, FAV$, etc.)

            $this->_bulkInsertDocumentos($documentos);

            // Insertar movimientos contables (detalle factura)
            $this->_bulkInsertMovimientos($movimientos);
            //$this->commit();
            // Insertar recibo y obtener ID

            $reciboId = null;
            if (!empty($recibo)) {
                $reciboId = $this->_insertRecibo($recibo); // Asumimos un solo recibo por factura
            }

            // Insertar documentos IGTF
            if (!empty($documentoRecibo)) {
                $this->_bulkInsertDocumentoRecibo($documentoRecibo);
            }

            // Insertar movimientos del recibo, asignando el ID del recibo si es necesario
            if (!empty($movimientosRecibos)) {
                foreach ($movimientosRecibos as &$movRecibo) {
                    if (isset($reciboId)) {
                        $movRecibo['MRE_REC_ID'] = $reciboId;
                    }
                }
                $this->_bulkInsertMovimientosRecibo($movimientosRecibos, $documentosIgtf);
            }
            //$this->commit();
            
        } catch (Exception $e) {
            $this->rollBack();
            throw $e; 
        }
    }

    /**
     * Helper privado que ejecuta el SELECT COUNT(*) para verificar si un documento existe.
     * Replica tu lógica de validación original.
     */
    private function _documentoExiste($numero, $tipoDocCodigo, $tipoTransaccion) {
        $numeroSanitized = $this->conexion->quote($numero);
        $tipoDocCodigoSanitized = $this->conexion->quote($tipoDocCodigo);
        $tipoTransaccionSanitized = $this->conexion->quote($tipoTransaccion);

        $sql = "SELECT 1 FROM adn_doccli 
                WHERE DCL_NUMERO = {$numeroSanitized} 
                  AND DCL_TDT_CODIGO = {$tipoDocCodigoSanitized} 
                  AND DCL_TIPTRA = {$tipoTransaccionSanitized} 
                LIMIT 1";
        
        $this->strquery = $sql; // Guardar para depuración
        $result = $this->select($sql);

        return !empty($result); // Devuelve true si encuentra algo, false si no.
    }

    /**
     * Valida si alguna de las combinaciones de movimientos ya existe en la BD.
     * Devuelve los datos del primer duplicado que encuentra, o `false` si no hay ninguno.
     */
    private function _movimientosExisten(array $movimientos) {
        if (empty($movimientos)) {
            return false;
        }

        // Construimos una serie de cláusulas (OR) para buscar todas las combinaciones en una sola consulta.
        $whereClauses = [];

        foreach ($movimientos as $mov) {
            // Extraemos los datos del array preparado en sus posiciones correctas
            $dcl_numero = $this->conexion->quote($mov[3]);  // MCL_DCL_NUMERO
            $dcl_tdt_codigo = $this->conexion->quote($mov[4]);  // MCL_DCL_TDT_CODIGO
            $upp_pdt_codigo = $this->conexion->quote($mov[6]);  // MCL_UPP_PDT_CODIGO
            $cantidad = $this->conexion->quote($mov[10]); // MCL_CANTIDAD
            $base = $this->conexion->quote($mov[15]); // MCL_BASE (precio)
            
            $lote_default       = $this->conexion->quote('');
            $fechaLote_default  = $this->conexion->quote('0000-00-00');

            $whereClauses[] = "(
                MCL_DCL_NUMERO = {$dcl_numero} AND
                MCL_DCL_TDT_CODIGO = {$dcl_tdt_codigo} AND
                MCL_UPP_PDT_CODIGO = {$upp_pdt_codigo} AND
                MCL_CANTIDAD = {$cantidad} AND
                MCL_BASE = {$base} AND
                MCL_LOTE = {$lote_default} AND
                MCL_FECHALOTE = {$fechaLote_default}
            )"; 
        }

        $sql = "SELECT MCL_UPP_PDT_CODIGO, MCL_CANTIDAD FROM adn_movcli WHERE " . implode(" OR ", $whereClauses) . " LIMIT 1";
        
        $this->strquery = $sql; // Guardar para depuración
        $result = $this->select($sql);

        if (!empty($result)) {
            // Devolvemos información útil para el mensaje de error
            return ['producto' => $result['MCL_UPP_PDT_CODIGO'], 'cantidad' => $result['MCL_CANTIDAD']];
        }

        return false;
    }

    /**
     * Helper privado que ejecuta el SELECT COUNT(*) para verificar si un recibo existe.
     * Replica tu lógica de validación original.
     */
    private function _reciboExiste($numeroRecibo) {
        $numeroSanitized = $this->conexion->quote($numeroRecibo);

        $sql = "SELECT 1 FROM adn_recibos 
                WHERE REC_NUMERO = {$numeroSanitized} 
                LIMIT 1";

        $this->strquery = $sql; // Guardar para depuración
        $result = $this->select($sql);
        return !empty($result); // Devuelve true si encuentra el recibo
    }

    private function _movimientosReciboExisten(array $movimientos) {  
        if (empty($movimientos)) {
            return false;
        }

        // Construimos una serie de cláusulas (OR) para buscar todas las combinaciones en una sola consulta.
        $whereClauses = [];
        foreach ($movimientos as $mov) {
            // Extraemos los datos del array preparado en sus posiciones correctas
            $mbc_numero = $this->conexion->quote($mov[0]);  // MBC_NUMERO
            $rec_numero = $this->conexion->quote($mov[9]);  // MBC_REC_NUMERO

            $whereClauses[] = "(
                MBC_NUMERO = {$mbc_numero} AND
                MBC_REC_NUMERO = {$rec_numero}
            )"; 
        }

        $sql = "SELECT MBC_NUMERO, MBC_REC_NUMERO FROM adn_movbco WHERE " . implode(" OR ", $whereClauses) . " LIMIT 1";
        
        $this->strquery = $sql; // Guardar para depuración
        $result = $this->select($sql);

        if (!empty($result)) {
            // Devolvemos información útil para el mensaje de error
            return ['MBC_NUMERO' => $result['MBC_NUMERO'], 'MBC_REC_NUMERO' => $result['MBC_REC_NUMERO']];
        }

        return false;
    }

    private function _documentoIgtfExiste($numero, $tipTra = 'D')
    {

        $sql = "SELECT COUNT(*) AS total 
                FROM adn_doccli 
                WHERE DCL_NUMERO = '$numero' 
                AND DCL_TDT_CODIGO = 'IGTFV' 
                AND DCL_TIPTRA = '$tipTra'";

        $result = $this->select($sql);
        return $result['total'] > 0;
    }
    
    // =================================================================================
    // MÉTODOS "PREPARADORES" DE DATOS
    // =================================================================================
    public function prepararDatosTransaccionalesFactura($factura, $moneda_base, $empresa) {
        $documentos = [];
        $movimientos = [];
        $movimientos_recibo = [];
        $documentosIgtf = [];
        $documentoRecibo = [];
        $recibo = [];

        $idsDocumentos = $this->idsDocumentos();

        $numeroDocUltimo     = $idsDocumentos['nDoc'];
        $numeroPedUltimo     = $idsDocumentos['nPed'];
        $numeroReciboUltimo  = $idsDocumentos['nRecibo'];

        $sql = "SELECT TIME('$factura->hora') - INTERVAL (FLOOR(RAND() * (120 - 60 + 1)) + 60) SECOND as tiempo";
        $horaPed = $this->select($sql);

        $sql1 = "SELECT TIME('$factura->hora') + INTERVAL (FLOOR(RAND() * (120 - 60 + 1)) + 60) SECOND as tiempo";
        $horaFav = $this->select($sql1);

        // Documento tipo PED y FAV/NEN (D)
        $documentos[] = $this->_prepararArrayDocumento($factura, 'PED', 'D', $moneda_base, ['numeroPedUltimo' => $numeroPedUltimo, 'horaPed' => $horaPed['tiempo']], $empresa);
        
        //PROCESO DE REGISTRA LAS D BIEN SEA NEN O FAV
        if($factura->tipo_documento === 'NEN'){
            $documentos[] = $this->_prepararArrayDocumento($factura, 'NEN', 'D', $moneda_base, ['numeroPedUltimo' => $numeroPedUltimo, 'horaFav' => $horaFav['tiempo']], $empresa);
            $documentos[] = $this->_prepararArrayDocumento($factura, 'FAV', 'D', $moneda_base, ['numeroDocUltimo' => $numeroDocUltimo, 'horaFav' => $horaFav['tiempo']], $empresa);
        }else{
            $documentos[] = $this->_prepararArrayDocumento($factura, 'FAV', 'D', $moneda_base, ['numeroDocUltimo' => $numeroDocUltimo, 'horaFav' => $horaFav['tiempo']], $empresa);
        }	

		if($empresa !== 'CRM' && $factura->empresa !== 'ENV_TEST'){
        	// Documento FAV$/NEN$ (D) si moneda base es BS
			if ($moneda_base == 'BS') {
				// Determinar el tipo de documento en dólares
				$tipoDocDolar = ($factura->tipo_documento === 'NEN') ? 'NEN$' : 'FAV$';

				//PROCESO DE REGISTRA LAS D DE DOLAR SI LO APLICA BIEN SEA NEN O FAV
                if($factura->tipo_documento === 'NEN'){
                    $documentos[] = $this->_prepararArrayDocumento($factura, 'NEN$', 'D', 'USD', [
                        'brutoUsd' => $factura->monto_bruto / $factura->valor_cambiario_dolar,
                        'netoUsd' => $factura->neto / $factura->valor_cambiario_dolar,
                        'baseGravadoUsd' => $factura->base_gravada / $factura->valor_cambiario_dolar,
                        'exentoUsd' => $factura->exento / $factura->valor_cambiario_dolar,
                        'ivaGravadoUsd' => $factura->iva_gravado / $factura->valor_cambiario_dolar,
                        'baseIgtfUsd' => $factura->base_igtf / $factura->valor_cambiario_dolar,
                        'horaFav' => $horaFav['tiempo']
                    ], $empresa);
                    $documentos[] = $this->_prepararArrayDocumento($factura, 'FAV$', 'D', 'USD', [
                        'brutoUsd' => $factura->monto_bruto / $factura->valor_cambiario_dolar,
                        'netoUsd' => $factura->neto / $factura->valor_cambiario_dolar,
                        'baseGravadoUsd' => $factura->base_gravada / $factura->valor_cambiario_dolar,
                        'exentoUsd' => $factura->exento / $factura->valor_cambiario_dolar,
                        'ivaGravadoUsd' => $factura->iva_gravado / $factura->valor_cambiario_dolar,
                        'baseIgtfUsd' => $factura->base_igtf / $factura->valor_cambiario_dolar,
                        'numeroDocUltimo' => $numeroDocUltimo,
                        'horaFav' => $horaFav['tiempo']
                    ], $empresa);
                }else{
                    $documentos[] = $this->_prepararArrayDocumento($factura, 'FAV$', 'D', 'USD', [
                        'brutoUsd' => $factura->monto_bruto / $factura->valor_cambiario_dolar,
                        'netoUsd' => $factura->neto / $factura->valor_cambiario_dolar,
                        'baseGravadoUsd' => $factura->base_gravada / $factura->valor_cambiario_dolar,
                        'exentoUsd' => $factura->exento / $factura->valor_cambiario_dolar,
                        'ivaGravadoUsd' => $factura->iva_gravado / $factura->valor_cambiario_dolar,
                        'baseIgtfUsd' => $factura->base_igtf / $factura->valor_cambiario_dolar,
                        'numeroDocUltimo' => $numeroDocUltimo,
                        'horaFav' => $horaFav['tiempo']
                    ], $empresa);
                }
			}
		}
	

        // Movimientos contables (detalle)
        if (!empty($factura->movimientos)) {
            foreach ($factura->movimientos as $mov) {

                if($factura->tipo_documento === 'NEN'){
                    $movimientos[] = $this->_prepararArrayMovimiento($factura->tipo_documento, $factura->numero, 'NEN', $mov);
                    $movimientos[] = $this->_prepararArrayMovimiento($factura->tipo_documento, $factura->numero, 'FAV', $mov, ['numeroDocUltimo' => $numeroDocUltimo]);
                }else{
                    $movimientos[] = $this->_prepararArrayMovimiento($factura->tipo_documento, $factura->numero, 'FAV', $mov, ['numeroDocUltimo' => $numeroDocUltimo]);
                }
            }
        }

         // Recibo y movimientos del recibo
        if (!empty($factura->recibo)) {

            if($factura->tipo_documento === 'NEN'){
                $recibo[] = $this->_prepararArrayRecibo($factura, 'NEN');
                $recibo[] = $this->_prepararArrayRecibo($factura, 'FAV', ['numeroReciboUltimo' => $numeroReciboUltimo]);
            }else{
                $recibo[] = $this->_prepararArrayRecibo($factura, 'FAV', ['numeroReciboUltimo' => $numeroReciboUltimo]);
            }

            foreach ($factura->recibo->movimientos as $mov) {
                if($factura->tipo_documento === 'NEN'){
                    $movimientos_recibo[] = $this->_prepararArrayMovimientoRecibo($factura, $mov, 'NEN');
                    $movimientos_recibo[] = $this->_prepararArrayMovimientoRecibo($factura, $mov, 'FAV', ['numeroReciboUltimo' => $numeroReciboUltimo]);
                }else{
                    $movimientos_recibo[] = $this->_prepararArrayMovimientoRecibo($factura, $mov, 'FAV', ['numeroReciboUltimo' => $numeroReciboUltimo]);
                }

                if (strtoupper($mov->moneda) !== 'BS') {
                    $fechaMovimiento = $mov->fecha ?? $factura->fecha;
                    $horaMovimiento = $horaFav['tiempo'];
                    $codigoIgtf = date('dmyHis', strtotime($fechaMovimiento . ' ' . $horaMovimiento)) . 'UT';
                    $tipoOperacion = $mov->tipo_operacion ?? 'NO_DEF';
                    $igtf = $mov->igtf ?? 0;
                    $baseIgtf = $mov->base_igtf ?? 0;

                    //$igtfNetoUsd = $moneda_base === 'BS' ? $igtf / $valorDolar : $igtf;
                    //$netoIgtfUsd = $moneda_base === 'BS' ? $baseIgtf / $valorDolar : $baseIgtf;
                    $igtfNetoUsd = $igtf;
                    $netoIgtfUsd = $baseIgtf;

                    // Documento IGTFV tipo D (Devengo)
                    $documentosIgtf[] = $this->_prepararArrayDocumentoIgtf($codigoIgtf, $factura, 'IGTFV', 'D', [
                        'tipo_operacion' => $tipoOperacion,
                        'netoUsd'        => $igtfNetoUsd,
                        'baseIgtfUsd'    => $netoIgtfUsd,
                        'fechaMovimiento' => $fechaMovimiento,
                        'horaMovimiento' => $horaMovimiento
                    ]);

                    if($mov->pago_igtf == 1){

                        $nRecibo = ($factura->tipo_documento === 'NEN') ? $numeroReciboUltimo : $factura->recibo->codigo;
                        // Documento IGTFV tipo P (Pago)
                        $documentosIgtf[] = $this->_prepararArrayDocumentoIgtf($codigoIgtf, $factura, 'IGTFV', 'P', [
                            'referencia'     => $nRecibo,
                            'netoUsd'        => $igtfNetoUsd,
                            'baseIgtfUsd'    => '0.000',
                            'fechaMovimiento' => $fechaMovimiento,
                            'horaMovimiento' => $horaMovimiento
                        ]);
                    }
                }
            }
            
            if($factura->tipo_documento === 'NEN'){

                if($factura->empresa !== 'CRM' && $factura->empresa !== 'ENV_TEST'){
                    if ($moneda_base == 'BS') {
                        $netoPago = $factura->neto / $factura->valor_cambiario_dolar;
                        $tipoDocPagoNen = 'NEN$';
                        $tipoDocPagoFav = 'FAV$';
                    } else {
                        $netoPago = $factura->neto;
                        $tipoDocPagoNen = 'NEN';
                        $tipoDocPagoFav = 'FAV';
                    }
                }else{
                    $netoPago = $factura->neto;
                    $tipoDocPagoNen = 'NEN';
                    $tipoDocPagoFav = 'FAV';
                }

                $documentoRecibo[] = $this->_prepararArrayDocumento($factura, $tipoDocPagoNen, 'P', $moneda_base, [
                    'referencia' => $factura->recibo->codigo,
                    'netoUsd' => $netoPago,
                    'horaFav' => $horaFav['tiempo']
                ], $empresa);
                $documentoRecibo[] = $this->_prepararArrayDocumento($factura, $tipoDocPagoFav, 'P', $moneda_base, [
                    'referencia' => $numeroReciboUltimo,
                    'netoUsd' => $netoPago,
                    'numeroDocUltimo' => $numeroDocUltimo,
                    'horaFav' => $horaFav['tiempo']
                ], $empresa);

            }else{

                if($factura->empresa !== 'CRM' && $factura->empresa !== 'ENV_TEST'){
                    if ($moneda_base == 'BS') {
                        $netoPago = $factura->neto / $factura->valor_cambiario_dolar;
                        $tipoDocPagoFav = 'FAV$';
                    } else {
                        $netoPago = $factura->neto;
                        $tipoDocPagoFav = 'FAV';
                    }
                }else{
                    $netoPago = $factura->neto;
                    $tipoDocPagoFav = 'FAV';
                }

                $documentoRecibo[] = $this->_prepararArrayDocumento($factura, $tipoDocPagoFav, 'P', $moneda_base, [
                    'referencia' => $factura->recibo->codigo,
                    'netoUsd' => $netoPago,
                    'numeroDocUltimo' => $numeroDocUltimo,
                    'horaFav' => $horaFav['tiempo']
                ], $empresa);
            }
        }
		
        return ['documentos' => $documentos, 
                'movimientos' => $movimientos, 
                'recibo' => $recibo, 
                'movimientosRecibos' => $movimientos_recibo,
                'documentosIgtf' => $documentosIgtf,
                'documentoRecibo' => $documentoRecibo
               ];
    }

    protected function idsDocumentos(){
        
        $sql = "SELECT 
                IFNULL((SELECT LPAD(MAX(CAST(D1.DCL_NUMERO AS UNSIGNED)) + 1, 10, 0)
                FROM ADN_DOCCLI D1
                WHERE D1.DCL_TDT_CODIGO = 'FAV'), '0000000001') AS nDoc,

                IFNULL((SELECT LPAD(MAX(CAST(D1.DCL_NUMERO AS UNSIGNED)) + 1, 10, 0)
                FROM ADN_DOCCLI D1
                WHERE D1.DCL_TDT_CODIGO = 'PED'), '0000000001') AS nPed,

                IFNULL((SELECT
                CONCAT(LPAD('1', 3, 0), LPAD(MAX(CAST(RIGHT(R1.REC_NUMERO, 17) AS UNSIGNED)) + 1, 17, 0))
                FROM ADN_RECIBOS R1
                WHERE R1.REC_NUMERO IN (SELECT DISTINCT D1.DCL_REC_NUMERO FROM ADN_DOCCLI D1 
                WHERE D1.DCL_TDT_CODIGO NOT IN('NEN','NEN$') AND D1.DCL_REC_NUMERO != '')), '00100000000000000001') AS nRecibo"; 

        $result = $this->select($sql);
        return $result;
    }
    
    protected function _prepararArrayDocumentoIgtf($numero, $factura, $tipoDoc, $tipoTransaccion, $data = [])
    {   
        $estado = 'PAG';
        $cxc = ($tipoTransaccion == 'D') ? '1' : '-1';
        $fechaVencimiento = date("Y-m-d", strtotime("$factura->fecha + $factura->plazo days"));
        $idCaja = '01';
        $SistemOrigen = 'REC';
        $moneda = ($factura->moneda === 'BS') ? 'USD' : 'BS';

        return [
            $numero, $tipoDoc, $data['referencia'] ?? '', $factura->vendedor->codigo, $factura->cliente->codigo, $factura->fecha, 
            $this->sanitizeNumber($data['netoUsd']), '0.000', '0.000', $factura->serie_fiscal, $tipoTransaccion, $cxc, $factura->activo,
            $estado, "{$data['fechaMovimiento']} {$data['horaMovimiento']}",'', '0.000', $data['horaMovimiento'], '0', '',
            $fechaVencimiento, $data['tipo_operacion'] ?? '', 'ID DEL MOVIMIENTO', $idCaja, '0.000', $factura->usuario, $factura->estacion, $factura->ip,
            $SistemOrigen, $factura->sucursal->codigo, $this->sanitizeNumber($factura->valor_cambiario_dolar), $moneda, $this->sanitizeNumber($factura->valor_cambiario_peso), 
            '0.000', '0.000', '0.000', '0.000', $this->sanitizeNumber($data['baseIgtfUsd'])
        ];
    }

    // Método privado para preparar un array de un solo documento
    private function _prepararArrayDocumento($factura, $tipo_documento_procesar, $tipo_doc, $base_moneda, $overrides = [], $empresa) {
        $fechaVencimiento = date("Y-m-d", strtotime("{$factura->fecha} + {$factura->plazo} days"));
        $cxc = ($tipo_doc === 'D') ? (($base_moneda === 'USD') ? "1" : "0") : (($tipo_doc === 'P') ? "-1" : null);  

        if($tipo_documento_procesar === 'FAV' || $tipo_documento_procesar === 'FAV$'){
            $SistemOrigen = 'IDL';
            $activo = '1';
            $numeroDocUltimo = $overrides['numeroDocUltimo'] ?? '';
            $tipo_inventario = ($tipo_documento_procesar === 'FAV$') ? '0' : '1';
            $tipoDocumentoOrigen = ($factura->tipo_documento === 'NEN' && $tipo_doc != 'P') ? (($tipo_documento_procesar === 'FAV') ? 'NEN' : 'FAV') : '';
            $tipoNumeroOrigen = ($factura->tipo_documento === 'NEN' && $tipo_doc != 'P') ? (($tipo_documento_procesar === 'FAV') ? "NEN:{$factura->numero}" : "FAV:{$numeroDocUltimo}") : '';
            $horaDoc = $overrides['horaFav'];

        }else if($tipo_documento_procesar === 'NEN' || $tipo_documento_procesar === 'NEN$'){
            $SistemOrigen = ($tipo_doc === 'D') ? 'MOD' : (($tipo_doc === 'P') ? 'REC' : null);
            $activo = '0';
            $tipo_inventario = ($tipo_documento_procesar === 'NEN$') ? '0' : '1';
            $numeroDocUltimo = $factura->numero ?? '';
            $tipoDocumentoOrigen = ($tipo_documento_procesar === 'NEN') ? 'PED' : (($tipo_doc != 'P') ? 'NEN' : '');
            $tipoNumeroOrigen = ($tipo_documento_procesar === 'NEN') ? "PED:{$overrides['numeroPedUltimo']}" : (($tipo_doc != 'P') ? "NEN:{$factura->numero}" : '');
            $horaDoc = $factura->hora;

        }else if($tipo_documento_procesar === 'PED'){
            $SistemOrigen = ($tipo_doc === 'D') ? 'MOD' : (($tipo_doc === 'P') ? 'REC' : null);
            $activo = '1';
            $tipo_inventario = '1';
            $numeroDocUltimo = $overrides['numeroPedUltimo'] ?? '';
            $tipoDocumentoOrigen = '';
            $tipoNumeroOrigen = '';
            $horaDoc = $overrides['horaPed'];
        }

        $moneda = ($tipo_documento_procesar === 'PED' || $tipo_documento_procesar === 'FAV' || $tipo_documento_procesar === 'NEN') ? $factura->moneda : 'USD';
        $neto_usd = ($tipo_documento_procesar === 'PED' || $tipo_documento_procesar === 'FAV' || $tipo_documento_procesar === 'NEN') ? $factura->neto_usd : $factura->neto;
        $base_gravada_usd = ($tipo_documento_procesar === 'PED' || $tipo_documento_procesar === 'FAV' || $tipo_documento_procesar === 'NEN') ? $factura->base_gravada_usd : $factura->base_gravada;
        $exento_usd = ($tipo_documento_procesar === 'PED' || $tipo_documento_procesar === 'FAV' || $tipo_documento_procesar === 'NEN') ? $factura->exento_usd : $factura->exento;
        $iva_gravado_usd = ($tipo_documento_procesar === 'PED' || $tipo_documento_procesar === 'FAV' || $tipo_documento_procesar === 'NEN') ? $factura->iva_gravado_usd : $factura->iva_gravado;

        if($empresa === 'MPC' || $empresa === 'MPC_TEST'){
            $serieFiscal = ($tipo_documento_procesar === 'FAV' || $tipo_documento_procesar === 'FAV$') ? 'A' : $factura->serie_fiscal;
        }else{
            $serieFiscal = $factura->serie_fiscal;
        }

        return [
            $numeroDocUltimo, $tipo_documento_procesar, $overrides['referencia'] ?? '', $factura->vendedor->codigo, $factura->cliente->codigo, $factura->fecha, 
            $this->sanitizeNumber($overrides['netoUsd'] ?? $factura->neto), $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : ($overrides['baseGravadoUsd'] ?? $factura->base_gravada)), 
            $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : ($overrides['exentoUsd'] ?? $factura->exento)), $serieFiscal, $tipo_doc, $cxc, $activo, $factura->estado_documento, 
            $this->sanitizeNumber($factura->descuento_porcentual ?? 0), "{$factura->fecha} {$horaDoc}", $factura->numero_impresion_fiscal, 
            $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : ($overrides['ivaGravadoUsd'] ?? $factura->iva_gravado)), $horaDoc, $factura->plazo, $factura->condicion, $tipo_inventario, $fechaVencimiento, 
            $tipoDocumentoOrigen, $tipoNumeroOrigen, '01', $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : ($overrides['brutoUsd'] ?? $factura->monto_bruto)), $factura->usuario, $factura->estacion, 
            $factura->ip, $SistemOrigen, $factura->sucursal->codigo, $this->sanitizeNumber($factura->valor_cambiario_dolar), $moneda, $this->sanitizeNumber($factura->valor_cambiario_peso), 
            $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : $neto_usd), $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : $base_gravada_usd), $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : $exento_usd), 
            $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : $iva_gravado_usd), $this->sanitizeNumber(($tipo_doc == 'P') ? '0.000' : ($overrides['baseIgtfUsd'] ?? $factura->base_igtf))
        ];
    }

    // Método privado para preparar un array de un solo movimiento
    private function _prepararArrayMovimiento($tipo_documento, $numero_doc, $tipo_doc, $mov, $overrides = []) {
        $valorInv = ($tipo_doc == 'FAV') ? "-1" : '0';
        $iva = ($mov->tipo_iva == 'GN') ? "16.00" : "0.00";
        $id_iva = ($mov->tipo_iva == 'GN') ? "13" : "1";
        $activo = ($tipo_doc == 'FAV') ? '1' : '0';
        $export = ($tipo_doc == 'FAV') ? '0' : $mov->cantidad;
        $tipoDocumentoImport = ($tipo_documento === 'NEN') ? (($tipo_doc == 'FAV') ? 'NEN' : '') : '';
        $numeroDocImport = ($tipo_documento === 'NEN') ? (($tipo_doc == 'FAV') ? "NEN:{$numero_doc}" : '') : '';

        return [
            $id_iva, '000001', $overrides['numeroDocUltimo'] ?? $numero_doc, $tipo_doc, $mov->almacen->codigo, $mov->producto->codigo, 
            $mov->unidad->codigo, '', $mov->transaccion, $mov->cantidad, $valorInv, $valorInv, $valorInv, $activo,
            $this->sanitizeNumber($mov->descuento_porcentual ?? 0), $this->sanitizeNumber($mov->precio), 'D', $mov->tipo_lista_precio, $mov->cantidad, $iva, 
            $mov->tipo_iva, '1', $mov->descripcion, $export, $tipoDocumentoImport, $numeroDocImport, '', $this->sanitizeNumber($mov->costo)
        ];
    }

    private function _prepararArrayRecibo($factura, $tipo_documento, $overrides = []) {
        
        $activo = ($tipo_documento == 'NEN') ? '0' : '1';

        return [[
            $overrides['numeroReciboUltimo'] ?? $factura->recibo->codigo,
            $this->sanitizeNumber($factura->recibo->monto),
            $factura->cliente->codigo,
            $factura->recibo->fecha,
            "{$factura->fecha} {$factura->hora}",
            'P',
            $activo,
            $factura->vendedor->codigo,
            $factura->ip,
            $factura->usuario,
            $factura->estacion,
            $factura->sucursal->codigo,
            $this->sanitizeNumber($factura->valor_cambiario_dolar),
            $this->sanitizeNumber($factura->valor_cambiario_peso)
        ]];
    }

    private function _prepararArrayMovimientoRecibo($factura, $movimiento, $tipo_documento, $overrides = []) {
        
        $activo = ($tipo_documento == 'NEN') ? '0' : '1';
        return [
            $movimiento->referencia,$movimiento->fecha,$movimiento->hora,$this->sanitizeNumber($movimiento->monto),$movimiento->tipo_operacion,
            $movimiento->banco->codigo,$movimiento->banco->cuenta->numero,$activo,$movimiento->tipo_movimiento,
            $overrides['numeroReciboUltimo'] ?? $factura->recibo->codigo, $movimiento->codigo_caja,'REC',$factura->usuario,$factura->estacion,$factura->ip,
            $this->sanitizeNumber($movimiento->monto_usd),$movimiento->moneda,$this->sanitizeNumber($factura->valor_cambiario_dolar),
            $this->sanitizeNumber($factura->valor_cambiario_peso),''
            ];
    }

    private function sanitizeNumber($value) 
    {
        return floatval(str_replace(',', '.', $value));
    }

    // =================================================================================
    // 3. MÉTODOS DE INSERCIÓN MASIVA (privados)
    // =================================================================================
    private function _bulkInsertOrUpdateBancos(array $data) {
        if (empty($data)) return;

        foreach ($data as $codigoBanco => $banco) {
            // Validar existencia del banco
            $checkBanco = $this->select("SELECT COUNT(*) as total FROM adn_bancos WHERE BCO_CODIGO = '$banco->codigo'");
            if ($checkBanco['total'] == 0) {
                $sqlBanco = "INSERT INTO adn_bancos (BCO_CODIGO, BCO_NOMBRE, BCO_ACTIVO) VALUES (?, ?, ?)";
                $this->insert($sqlBanco, [
                    $banco->codigo,
                    $banco->nombre,
                    $banco->activo
                ]);
            }

            // Verificar si tiene cuenta asociada
            if (isset($banco->cuenta)) {
                $cuenta = $banco->cuenta;

                // Validar existencia de la cuenta
                $checkCuenta = $this->select("SELECT COUNT(*) AS total FROM adn_ctabanco WHERE CBC_BCO_CODIGO = '$banco->codigo' AND CBC_CUENTA = '$cuenta->numero'");
                if ($checkCuenta['total'] == 0) {
                    $sqlCuenta = "INSERT INTO adn_ctabanco (CBC_BCO_CODIGO, CBC_CUENTA, CBC_TITULAR, CBC_ACTIVO, CBC_SUCURSAL) VALUES (?, ?, ?, ?, ?)";
                    $this->insert($sqlCuenta, [
                        $banco->codigo,       // CBC_BCO_CODIGO
                        $cuenta->numero,      // CBC_CUENTA
                        $cuenta->titular,     // CBC_TITULAR
                        $cuenta->activo,      // CBC_ACTIVO
                        '000001'
                    ]);
                }
            }
        }
    }

    private function _bulkInsertOrUpdateSimpleSerieFiscal(string $tabla, array $columnas, array $data) {

        if (empty($data)) return;
        
        $sql_inicio = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ") VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE {$columnas[1]} = VALUES({$columnas[1]})"; // Actualiza la segunda columna (descripción)

        $valueStrings = [];
        foreach ($data as $codigo => $obj) {

            $modelo = $this->conexion->quote('SERIE '.$obj);
            $serie = $this->conexion->quote($obj);
            
            $valueStrings[] = "({$modelo}, {$serie})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);

    }

    private function _bulkInsertOrUpdateSimpleGrupo(string $tabla, array $columnas, array $data) {

        if (empty($data)) return;
        
        $sql_inicio = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ") VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE {$columnas[1]} = VALUES({$columnas[1]})"; // Actualiza la segunda columna (descripción)

        $valueStrings = [];
        foreach ($data as $codigo => $obj) {

            $codigoSanitized = $this->conexion->quote($codigo);
            $descriSanitized = $this->conexion->quote($obj->descripcion);
            
            $valueStrings[] = "({$codigoSanitized}, {$descriSanitized}, NULL, NULL, NULL, NULL, NULL)";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    private function _bulkInsertOrUpdateSimpleAlmacen(string $tabla, array $columnas, array $data) {
        if (empty($data)) return;
        
        $sql_inicio = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ") VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE {$columnas[1]} = VALUES({$columnas[1]})"; // Actualiza la segunda columna (descripción)

        $valueStrings = [];
        foreach ($data as $codigo => $obj) {

            $codigoSanitized = $this->conexion->quote($codigo);
            $nombre = $this->conexion->quote($obj->nombre);
            $activo = $this->conexion->quote($obj->activo);
            $lpt = $this->conexion->quote($obj->lpt);
            $tipo = $this->conexion->quote($obj->tipo);
            $valueStrings[] = "({$codigoSanitized}, {$nombre}, {$activo}, {$lpt}, {$tipo})";
        }
        
        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    // Método genérico para entidades simples (código, descripción)
    private function _bulkInsertOrUpdateSimple(string $tabla, array $columnas, array $data) {

        if (empty($data)) return;
        
        $sql_inicio = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ") VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE {$columnas[1]} = VALUES({$columnas[1]})"; // Actualiza la segunda columna (descripción)

        $valueStrings = [];
        foreach ($data as $codigo => $obj) {
            $codigoSanitized = $this->conexion->quote($codigo);
            $descriSanitized = $this->conexion->quote($obj->descripcion);
            $valueStrings[] = "({$codigoSanitized}, {$descriSanitized})";
        }
        
        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    // =================================================================================
    // MÉTODO ESPECÍFICO PARA MANEJAR LA LÓGICA DE UNDAGRU
    // =================================================================================
    private function _bulkInsertUndagruLogic(LoteDataCollector $data) {

        if (empty($data->unidadesAgru)) return;

        // --- Parte 1: Insertar en adn_undagru ---
        $this->_insertIgnoreUndMedida($data->unidadesAgru);

        // --- Parte 2: Insertar en adn_undagru ---
        $this->_insertIgnoreUndagru($data->unidadesAgru);

        // --- Parte 3: Insertar en adn_undmulti ---
        $this->_insertIgnoreUndmulti($data->unidadesAgru);
        
        // --- Parte 4: Insertar en adn_undprod ---
        $this->_insertIgnoreUndprod($data->unidadesAgru);
    }

    // Método privado para insertar en la tabla adn_unidadmed
    private function _insertIgnoreUndMedida(array $data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO adn_unidadmed (UND_ID, UND_DESCRIPCION) VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE UND_DESCRIPCION = VALUES(UND_DESCRIPCION)";

        $valueStrings = [];
        foreach ($data as $item) {
            $codigoUnidad = $this->conexion->quote($item->unidad->codigo);
            $descriUnidad = $this->conexion->quote($item->unidad->codigo);
                
            $valueStrings[] = "({$codigoUnidad}, {$descriUnidad})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    // Método privado para insertar en la tabla adn_undagru
    private function _insertIgnoreUndagru(array $data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO adn_undagru (UGR_PDT_CODIGO, UGR_UND_ID, UGR_CANXUND) VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE UGR_CANXUND = VALUES(UGR_CANXUND)";

        $valueStrings = [];
        foreach ($data as $item) {
            $codigoProducto = $this->conexion->quote($item->producto->codigo);
            $codigoUnidad = $this->conexion->quote($item->unidad->codigo);
            $cantidad = $this->conexion->quote($item->cantidad);
            
            $valueStrings[] = "({$codigoProducto}, {$codigoUnidad}, {$cantidad})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    // Método privado para insertar en la tabla adn_undmulti
    private function _insertIgnoreUndmulti(array $data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO adn_undmulti (mul_ugr_pdt_codigo, mul_ugr_und_id) VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE mul_ugr_pdt_codigo = VALUES(mul_ugr_pdt_codigo)"; 

        $valueStrings = [];
        foreach ($data as $item) {
            $codigoProducto = $this->conexion->quote($item->producto->codigo);
            $codigoUnidad = $this->conexion->quote($item->unidad->codigo);
            
            $valueStrings[] = "({$codigoProducto}, {$codigoUnidad})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    // Método privado para insertar en la tabla adn_undprod
    private function _insertIgnoreUndprod(array $data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO adn_undprod (UPP_PDT_CODIGO, UPP_UND_ID) VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE UPP_PDT_CODIGO = VALUES(UPP_PDT_CODIGO)";

        $valueStrings = [];
        foreach ($data as $item) {
            $codigoProducto = $this->conexion->quote($item->producto->codigo);
            $codigoUnidad = $this->conexion->quote($item->unidad->codigo);
            
            $valueStrings[] = "({$codigoProducto}, {$codigoUnidad})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }
    
    // Métodos específicos para entidades más complejas
    private function _bulkInsertOrUpdateClientes(array $data) {

        if (empty($data)) return;
        
        // --- PASO 1: Ajustar la lista de columnas para que coincida 100% con tu tabla ---
        $sql_inicio = "INSERT INTO  adn_clientes (
                            CLT_CODIGO, CLT_NOMBRE, CLT_RIF, 
                            CLT_DIRECCION1, CLT_TELEFONO1, CLT_CELULAR, CLT_EMAIL, 
                            CLT_TIPOPER, CLT_ACTIVO, CLT_CCL_CODIGO, CLT_CONDICION
                    ) VALUES ";
        
        // --- PASO 2: Ajustar la sección UPDATE para que coincida con las columnas ---
        $sql_final = " ON DUPLICATE KEY UPDATE 
                            CLT_NOMBRE=VALUES(CLT_NOMBRE),
                            CLT_RIF=VALUES(CLT_RIF), 
                            CLT_DIRECCION1=VALUES(CLT_DIRECCION1), 
                            CLT_TELEFONO1=VALUES(CLT_TELEFONO1), 
                            CLT_CELULAR=VALUES(CLT_CELULAR), 
                            CLT_EMAIL=VALUES(CLT_EMAIL),
                            CLT_TIPOPER=VALUES(CLT_TIPOPER),
                            CLT_ACTIVO=VALUES(CLT_ACTIVO), 
                            CLT_CCL_CODIGO=VALUES(CLT_CCL_CODIGO),
                            CLT_CONDICION=VALUES(CLT_CONDICION)";
        
        $valueStrings = [];
        foreach ($data as $codigo => $cliente) {
            // --- PASO 3: Extraer y sanitizar TODOS los valores del objeto cliente ---
            $values = [
                $this->conexion->quote($cliente->codigo), 
                $this->conexion->quote($cliente->nombre ?? ''), 
                $this->conexion->quote($cliente->rif ?? ''),
                $this->conexion->quote($cliente->direccion ?? ''), // El JSON no tiene el '1'
                $this->conexion->quote($cliente->telefono ?? ''),  // El JSON no tiene el '1'
                $this->conexion->quote($cliente->celular ?? ''), 
                $this->conexion->quote($cliente->email ?? ''),
                $this->conexion->quote($cliente->tipo_persona ?? 'N'), // Nuevo campo
                $this->conexion->quote($cliente->activo ?? '1'),
                $this->conexion->quote($cliente->clasificacion_contribuyente ?? '000001'), // Nuevo campo
                $this->conexion->quote($cliente->condicion ?? 'CONTADO')
            ];
            $valueStrings[] = "(" . implode(',', $values) . ")";
        }
        
        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }
    
    private function _bulkInsertOrUpdatePacientes($data) {
        if (empty($data)) return;
        
        $sql = "INSERT INTO adn_personal (
                            PCL_CODIGO, PCL_NOMBRE, PCL_CELULAR, PCL_DIRECCION, PCL_RIF
                    ) VALUES ";
        
        $sql_final = " ON DUPLICATE KEY UPDATE
                        PCL_NOMBRE=VALUES(PCL_NOMBRE),
                        PCL_DIRECCION=VALUES(PCL_DIRECCION),
                        PCL_CELULAR=VALUES(PCL_CELULAR)";

        foreach ($data as $paciente) {

            $value = [
                $this->conexion->quote($paciente->codeCliente),
                $this->conexion->quote($paciente->nombre),
                $this->conexion->quote($paciente->celular),
                $this->conexion->quote($paciente->direccion),
                $this->conexion->quote($paciente->rif)
            ];

            $valueStrings[] = "(" . implode(',', $value) . ")";
        }

        $sql .= implode(', ', $valueStrings) . " {$sql_final}";
        $this->insert_massive($sql);
    }

    private function _bulkInsertOrUpdateVendedores(array $data) {
        if (empty($data)) return;

        // --- PASO 1: Listar todas las columnas de la tabla 'adn_vendedores' ---
        $sql_inicio = "INSERT INTO adn_vendedores (
                            VEN_CODIGO, VEN_NOMBRE, VEN_APELLIDO, 
                            VEN_ACTIVO, VEN_EMAIL, VEN_TELEFONO
                    ) VALUES ";

        // --- PASO 2: Listar todas las columnas que se deben actualizar si la clave ya existe ---
        $sql_final = " ON DUPLICATE KEY UPDATE 
                            VEN_NOMBRE = VALUES(VEN_NOMBRE), 
                            VEN_APELLIDO = VALUES(VEN_APELLIDO), 
                            VEN_ACTIVO = VALUES(VEN_ACTIVO), 
                            VEN_EMAIL = VALUES(VEN_EMAIL), 
                            VEN_TELEFONO = VALUES(VEN_TELEFONO)";

        $valueStrings = [];
        foreach ($data as $codigo => $vendedor) {
            // --- PASO 3: Crear un array de valores sanitizados para cada vendedor ---
            $values = [
                $this->conexion->quote($vendedor->codigo), 
                $this->conexion->quote($vendedor->nombre ?? ''),
                $this->conexion->quote($vendedor->apellido ?? ''),
                $this->conexion->quote($vendedor->activo ?? '1'),
                $this->conexion->quote($vendedor->email ?? ''),
                $this->conexion->quote($vendedor->telefono ?? '')
            ];
            $valueStrings[] = "(" . implode(',', $values) . ")";
        }

        // --- PASO 4: Unir todo y ejecutar la consulta masiva ---
        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    private function _bulkInsertOrUpdateVehiculos($data) {
        if (empty($data)) return;
        
        $sql = "INSERT INTO adn_contrato_veh (
                            CTT_DCL_NUMERO, CTT_CANTIDAD, CTT_MARCA, CTT_MODELO, CTT_PLACA, 
                            CTT_PUESTOS, CTT_COLOR, CTT_CAPACIDAD, CTT_PESO, CTT_ANO, CTT_CLASE, 
                            CTT_TIPO, CTT_USO, CTT_SERIAL_CARROCERIA, CTT_SERIAL_MOTOR, CTT_CUADRO_POLIZA
                    ) VALUES ";

        $sql_final = " ON DUPLICATE KEY UPDATE
                            CTT_CANTIDAD=VALUES(CTT_CANTIDAD), CTT_MARCA=VALUES(CTT_MARCA), CTT_MODELO=VALUES(CTT_MODELO), 
                            CTT_PLACA=VALUES(CTT_PLACA), CTT_PUESTOS=VALUES(CTT_PUESTOS), CTT_COLOR=VALUES(CTT_COLOR),
                            CTT_CAPACIDAD=VALUES(CTT_CAPACIDAD), CTT_PESO=VALUES(CTT_PESO), CTT_ANO=VALUES(CTT_ANO),
                            CTT_CLASE=VALUES(CTT_CLASE), CTT_TIPO=VALUES(CTT_TIPO), CTT_USO=VALUES(CTT_USO),
                            CTT_SERIAL_CARROCERIA=VALUES(CTT_SERIAL_CARROCERIA), CTT_SERIAL_MOTOR=VALUES(CTT_SERIAL_MOTOR),
                            CTT_CUADRO_POLIZA=VALUES(CTT_CUADRO_POLIZA)";

        foreach ($data as $codigo => $obj) {
             
            $value = [
                $this->conexion->quote($obj->documento),
                $this->conexion->quote('1'),
                $this->conexion->quote($obj->marca),
                $this->conexion->quote($obj->modelo),
                $this->conexion->quote($obj->placa),
                $this->conexion->quote($obj->puestos),
                $this->conexion->quote($obj->color),
                $this->conexion->quote($obj->capacidad_carga),
                $this->conexion->quote($obj->peso),
                $this->conexion->quote($obj->año),
                $this->conexion->quote($obj->clase),
                $this->conexion->quote($obj->tipo),
                $this->conexion->quote($obj->uso),
                $this->conexion->quote($obj->serial_carroceria),
                $this->conexion->quote($obj->serial_motor),
                $this->conexion->quote($obj->cuadro_poliza)
            ];
            
            $valueStrings[] = "(" . implode(',', $value) . ")";
        }

        $sql .= implode(', ', $valueStrings) . " {$sql_final}";
        $this->insert_massive($sql);
    }

    private function _bulkInsertOrUpdateProductos(array $data) {
        if (empty($data)) return;

        // --- PASO 1: Ajustar la lista de columnas para que coincida 100% con tu tabla ---
        $sql_inicio = "INSERT INTO adn_productos (
                            PDT_CODIGO, PDT_TIV_CODIGO, PDT_DESCRIPCION, PDT_ESTADO, 
                            PDT_DEP_CODIGO, PDT_CAT_CODIGO, PDT_MAR_CODIGO, PDT_VER_CODIGO, 
                            PDT_UPT_CODIGO, PDT_TIPCOSTO, PDT_GPO_CODIGO
                    ) VALUES ";

        // --- PASO 2: Ajustar la sección UPDATE para que coincida con las columnas ---
        $sql_final = " ON DUPLICATE KEY UPDATE 
                            PDT_TIV_CODIGO = VALUES(PDT_TIV_CODIGO),
                            PDT_DESCRIPCION = VALUES(PDT_DESCRIPCION), 
                            PDT_ESTADO = VALUES(PDT_ESTADO), 
                            PDT_DEP_CODIGO = VALUES(PDT_DEP_CODIGO), 
                            PDT_CAT_CODIGO = VALUES(PDT_CAT_CODIGO), 
                            PDT_MAR_CODIGO = VALUES(PDT_MAR_CODIGO), 
                            PDT_VER_CODIGO = VALUES(PDT_VER_CODIGO), 
                            PDT_UPT_CODIGO = VALUES(PDT_UPT_CODIGO), 
                            PDT_TIPCOSTO = VALUES(PDT_TIPCOSTO), 
                            PDT_GPO_CODIGO = VALUES(PDT_GPO_CODIGO)";

        $valueStrings = [];
        foreach ($data as $codigo => $prod) {
            
            // --- PASO 3: Extraer y sanitizar TODOS los valores del objeto producto ---
            $values = [
                $this->conexion->quote($prod->codigo),
                $this->conexion->quote($prod->tipo_iva ?? 'EX'), // Columna Faltante Añadida
                $this->conexion->quote($prod->descripcion ?? ''),
                $this->conexion->quote($prod->estado ?? '1'),
                $this->conexion->quote($prod->departamento->codigo ?? null),
                $this->conexion->quote($prod->categoria->codigo ?? null),
                $this->conexion->quote($prod->marca->codigo ?? null),
                $this->conexion->quote($prod->version->codigo ?? null),
                $this->conexion->quote('000001'), // Columna Faltante con valor fijo
                $this->conexion->quote($prod->tipo_costo ?? 'P'),
                $this->conexion->quote($prod->grupo->codigo ?? null)
            ];
            $valueStrings[] = "(" . implode(',', $values) . ")";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    private function _bulkInsertDocumentos(array $data) {
        if (empty($data)) return;

        $columnas = "DCL_NUMERO, DCL_TDT_CODIGO, DCL_REC_NUMERO, DCL_VEN_CODIGO, DCL_CLT_CODIGO, DCL_FECHA, 
                    DCL_NETO, DCL_BASEG, DCL_EXENTO, DCL_SERFIS, DCL_TIPTRA, DCL_CXC, DCL_ACTIVO, DCL_STD_ESTADO, 
                    DCL_PORDESC, DCL_FECHAHORA, DCL_NUMFIS, DCL_IVAG, DCL_HORA, DCL_PLAZO, DCL_CONDICION, DCL_TIPOINV, 
                    DCL_FECHAVEN, DCL_TDT_ORIGEN, DCL_ORIGENNUM, DCL_IDCAJA, DCL_BRUTO, DCL_USUARIO, DCL_ESTACION, 
                    DCL_IP, DCL_ORIGEN, DCL_CCT_CODIGO, DCL_VALORCAM, DCL_MONEDA, DCL_VALORCAM2, DCL_NETOUSD, 
                    DCL_BASEGUSD, DCL_EXENTOUSD, DCL_IVAGUSD, DCL_IGTF";
        
        $valueStrings = [];
        foreach ($data as $fila) {
            $sanitizedValues = array_map([$this->conexion, 'quote'], $fila);
            $valueStrings[] = "(" . implode(',', $sanitizedValues) . ")";
        }

        $this->insert_massive("INSERT INTO adn_doccli ($columnas) VALUES " . implode(', ', $valueStrings));
    }

    private function _bulkInsertMovimientos(array $data) {
        if (empty($data)) return;


        $columnas = "MCL_HTI_ID, MCL_DCL_SCS_CODIGO, MCL_DCL_NUMERO, MCL_DCL_TDT_CODIGO, MCL_AMC_CODIGO, MCL_UPP_PDT_CODIGO, 
                    MCL_UPP_UND_ID, MCL_DCL_REC_NUMERO, MCL_CTR_CODIGO, MCL_CANTIDAD, MCL_FISICO, MCL_LOGICO, MCL_CONTABLE, MCL_ACTIVO,
                    MCL_PORDCTO, MCL_BASE, MCL_DCL_TIPTRA, MCL_PLT_LISTA, MCL_CANTXUND, MCL_PORIVA, MCL_TIVACOD, MCL_METCOS, 
                    MCL_DESCRI, MCL_EXPORT, MCL_TDT_DOCIMPORT, MCL_NUM_DOCIMPORT, MCL_UBP_CODIGO, MCL_COSTOUSD";
        
        $valueStrings = [];
        foreach ($data as $fila) {
            $sanitizedValues = array_map([$this->conexion, 'quote'], $fila);
            $valueStrings[] = "(" . implode(',', $sanitizedValues) . ")";
           
        }

        $this->insert_massive("INSERT INTO adn_movcli ($columnas) VALUES " . implode(', ', $valueStrings));
    }

    private function _insertRecibo($data) {
        if (empty($data)) return;
        
        // --- PASO 1: Listar todas las columnas de la tabla 'adn_recibos' ---
        $columnas = "REC_NUMERO, REC_MONTO, REC_CLT_CODIGO, REC_FECHA, REC_FECHAHORA, REC_TIPO, REC_ACTIVO, REC_VEN_CODIGO,
                        REC_IP, REC_USUARIO, REC_ESTACION, REC_CCT_CODIGO, REC_VALORCAM, REC_VALORCAM2";
        
        // --- PASO 3: Crear un array de valores sanitizados para cada recibos ---
        $valueStrings = [];
        foreach ($data as $fila) {
            $valores = is_array($fila[0]) ? $fila[0] : $fila; // detecta el nivel automáticamente
            $sanitizedValues = array_map([$this->conexion, 'quote'], $valores);
            $valueStrings[] = "(" . implode(',', $sanitizedValues) . ")";
        }

        $this->insert_massive("INSERT INTO adn_recibos ($columnas) VALUES " . implode(', ', $valueStrings));
    }

    private function _bulkInsertDocumentoRecibo($data) {
        if (empty($data)) return;

        $columnas = "DCL_NUMERO, DCL_TDT_CODIGO, DCL_REC_NUMERO, DCL_VEN_CODIGO, DCL_CLT_CODIGO, DCL_FECHA, 
                    DCL_NETO, DCL_BASEG, DCL_EXENTO, DCL_SERFIS, DCL_TIPTRA, DCL_CXC, DCL_ACTIVO, DCL_STD_ESTADO, 
                    DCL_PORDESC, DCL_FECHAHORA, DCL_NUMFIS, DCL_IVAG, DCL_HORA, DCL_PLAZO, DCL_CONDICION, DCL_TIPOINV,
                    DCL_FECHAVEN, DCL_TDT_ORIGEN, DCL_ORIGENNUM, DCL_IDCAJA, DCL_BRUTO, DCL_USUARIO, DCL_ESTACION, 
                    DCL_IP, DCL_ORIGEN, DCL_CCT_CODIGO, DCL_VALORCAM, DCL_MONEDA, DCL_VALORCAM2, DCL_NETOUSD, 
                    DCL_BASEGUSD, DCL_EXENTOUSD, DCL_IVAGUSD, DCL_IGTF";
        
        $valueStrings = [];
        foreach ($data as $fila) {
            $sanitizedValues = array_map([$this->conexion, 'quote'], $fila);
            $valueStrings[] = "(" . implode(',', $sanitizedValues) . ")";
        }

        $this->insert_massive("INSERT INTO adn_doccli ($columnas) VALUES " . implode(', ', $valueStrings));
    }

    private function _bulkInsertMovimientosRecibo(array $data, array $documentosIgtf)
    {
        if (empty($data)) return;

        $idsMovimientos = [];

        // Paso 1: Insertamos cada movimiento bancario y guardamos el ID
        foreach ($data as $fila) {
            $sql = "INSERT INTO adn_movbco (
                        MBC_NUMERO, MBC_FECHA, MBC_HORA, MBC_MONTO, MBC_OBC_TIPO, MBC_CBC_BCO_CODIGO, MBC_CBC_CUENTA,
                        MBC_ACTIVO, MBC_TTB_CODIGO, MBC_REC_NUMERO, MBC_IDCAJA, MBC_ORIGEN, MBC_USUARIO, MBC_ESTACION, MBC_IP,
                        MBC_MONTOOTRAMONEDA, MBC_OTRAMONEDA, MBC_VALORCAM, MBC_VALORCAM2, MBC_SERIAL
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $idInsertado = $this->insertMovRecibo($sql, $fila); // <-- Este método debe devolver el lastInsertId()
            $idsMovimientos[] = $idInsertado;
        }

        // Paso 2: Asociamos documentos IGTF con cada movimiento insertado
        $igtfDocumentosConId = [];

        foreach ($idsMovimientos as $i => $idMovimiento) {
            // Se asume que vienen 2 documentos por movimiento (D y P)
            $docD = $documentosIgtf[$i * 2]     ?? null;
            $docP = $documentosIgtf[$i * 2 + 1] ?? null;

            if ($docD !== null) {
                $docD[22] = $idMovimiento; // ← Asegúrate de que esta posición sea la correcta para el campo: DCL_ORIGENNUM
                $igtfDocumentosConId[] = $docD;
            }

            if ($docP !== null) {
                $docP[22] = $idMovimiento;
                $igtfDocumentosConId[] = $docP;
            }
        }

        // Paso 3: Insertamos los documentos IGTF ya con sus ID asignados
        if (!empty($igtfDocumentosConId)) {
            $this->_bulkInsertDocumentosIgtf($igtfDocumentosConId);
        }
    }

    private function _bulkInsertDocumentosIgtf(array $data)
    {
        if (empty($data)) return;

        $columnas = "DCL_NUMERO, DCL_TDT_CODIGO, DCL_REC_NUMERO, DCL_VEN_CODIGO, DCL_CLT_CODIGO, DCL_FECHA, DCL_NETO,DCL_BASEG,
                    DCL_EXENTO, DCL_SERFIS, DCL_TIPTRA, DCL_CXC, DCL_ACTIVO, DCL_STD_ESTADO, DCL_FECHAHORA, DCL_NUMFIS, DCL_IVAG,
                    DCL_HORA, DCL_PLAZO, DCL_CONDICION, DCL_FECHAVEN, DCL_TDT_ORIGEN, DCL_ORIGENNUM, DCL_IDCAJA, DCL_BRUTO,
                    DCL_USUARIO, DCL_ESTACION, DCL_IP, DCL_ORIGEN, DCL_CCT_CODIGO, DCL_VALORCAM, DCL_MONEDA, DCL_VALORCAM2, DCL_NETOUSD,
					DCL_BASEGUSD, DCL_EXENTOUSD, DCL_IVAGUSD, DCL_IGTF";
        
        $valueStrings = [];
        foreach ($data as $fila) {
            $sanitizedValues = array_map([$this->conexion, 'quote'], $fila);
            $valueStrings[] = "(" . implode(',', $sanitizedValues) . ")";
        }

        $this->insert_massive("INSERT INTO adn_doccli ($columnas) VALUES " . implode(', ', $valueStrings));
    }
}
?>