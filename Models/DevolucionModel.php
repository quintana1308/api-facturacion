<?php 

class DevolucionModel extends Mysql {

    public function __construct($conectEnterprise) {  
        parent::__construct($conectEnterprise);
    }

    // =============================================================
    // MÉTODO PARA PROCESAR TODOS LOS DATOS MAESTROS DE LA DEVOLUCIÓN
    // =============================================================
    public function procesarMaestros(DocumentoDataCollector $data) {
        try {
            // Reutilizamos la lógica de DocumentoModel para maestros
            $this->_bulkInsertOrUpdateSimple('adn_marcas', ['MAR_CODIGO', 'MAR_DESCRIPCION'], $data->marcas);
            $this->_bulkInsertOrUpdateSimple('adn_departamentos', ['DEP_CODIGO', 'DEP_DESCRIPCION'], $data->departamentos);
            $this->_bulkInsertOrUpdateSimpleGrupo('adn_grupos', ['GPO_CODIGO', 'GPO_DESCRIPCION', 'GPO_FACC_CTA_CODIGO', 'GPO_DEVC_CTA_CODIGO',
                                            'GPO_FAVV_CTA_CODIGO', 'GPO_DEVV_CTA_CODIGO', 'GPO_ACTIVO_CTA_CODIGO'], $data->grupos);

            $this->_bulkInsertOrUpdateSimple('adn_categorias', ['CAT_CODIGO', 'CAT_DESCRIPCION'], $data->categorias);
            $this->_bulkInsertOrUpdateSimple('adn_versiones', ['VER_CODIGO', 'VER_DESCRIPCION'], $data->versiones);
            $this->_bulkInsertOrUpdateSimpleAlmacen('adn_almacenes', ['AMC_CODIGO', 'AMC_NOMBRE', 'AMC_ACTIVO', 'AMC_LPT', 'AMC_TIPO'], $data->almacenes);
            
            $this->_bulkInsertOrUpdateSimpleSerieFiscal('adn_seriefiscal', ['SFI_MODELO', 'SFI_SERIE'], $data->seriesFiscal);

            $this->_bulkInsertOrUpdateClientes($data->cliente);
            $this->_bulkInsertOrUpdateVendedores($data->vendedor);

            if ($data->vehiculo) {
                $this->_bulkInsertOrUpdateVehiculo($data->vehiculo);
            }

            if ($data->paciente) {
                $this->_bulkInsertOrUpdatePaciente($data->paciente);
            }

            $this->_bulkInsertOrUpdateProductos($data->productos);
            $this->_bulkInsertUndagruLogic($data);

            // --- Fase E: Insertar Centro de Costo (Sucursal) ---
            $this->_insertarCentroCosto($data->sucursal);

        } catch (Exception $e) {
            throw $e;
        }
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
    public function validarMontosDevolucion($devolucion, $facturaOrigen) {
        $tolerancia = 0.01; // Tolerancia para comparación de decimales
        
        if (abs($devolucion->neto - $facturaOrigen[0]['DCL_NETO']) > $tolerancia) {
            throw new Exception("El monto neto de la devolución no coincide con la factura origen.");
        }

        if (abs($devolucion->base_gravada - $facturaOrigen[0]['DCL_BASEG']) > $tolerancia) {
            throw new Exception("La base gravada de la devolución no coincide con la factura origen.");
        }

        if (abs($devolucion->iva_gravado - $facturaOrigen[0]['DCL_IVAG']) > $tolerancia) {
            throw new Exception("El IVA gravado de la devolución no coincide con la factura origen.");
        }

        if (abs($devolucion->monto_bruto - $facturaOrigen[0]['DCL_BRUTO']) > $tolerancia) {
            throw new Exception("El monto bruto de la devolución no coincide con la factura origen.");
        }
    }

    // =============================================================
    // VALIDAR QUE LOS MOVIMIENTOS COINCIDAN CON LA FACTURA ORIGEN
    // =============================================================
    public function validarMovimientosDevolucion($devolucion, $numeroFacturaOrigen) {
        // Obtener movimientos de la factura origen
        $sqlOrigen = "SELECT MCL_UPP_PDT_CODIGO, MCL_CANTIDAD, MCL_BASE 
                      FROM adn_movcli 
                      WHERE MCL_DCL_NUMERO = '$numeroFacturaOrigen' 
                      AND MCL_DCL_TDT_CODIGO = 'FAV' 
                      AND MCL_DCL_TIPTRA = 'D'";
        
        $request = $this->select_all($sqlOrigen);
        $movimientosOrigen = $request;

        if (count($devolucion->movimientos) !== count($movimientosOrigen)) {
            throw new Exception("La cantidad de movimientos de la devolución no coincide con la factura origen.");
        }

        // Crear arrays para comparación
        $movimientosOrigenMap = [];
        foreach ($movimientosOrigen as $mov) {
            $key = $mov['MCL_UPP_PDT_CODIGO'] . '|' . $mov['MCL_CANTIDAD'];
            $movimientosOrigenMap[$key] = true;
        }
        
        // Validar cada movimiento de la devolución
        foreach ($devolucion->movimientos as $movDev) {
            $key = $movDev->producto->codigo . '|' . $movDev->cantidad;

            if (!isset($movimientosOrigenMap[$key])) {
                throw new Exception("El movimiento del producto {$movDev->producto->codigo} no coincide con la factura origen.");
            }
        }
    }

    // =============================================================
    // PROCESAR LA DEVOLUCIÓN COMPLETA
    // =============================================================
    public function procesarDevolucion($devolucion, $moneda_base, $numeroFacturaOrigen) {
        
        // Validar que la factura origen existe
        $facturaOrigen = $this->validarFacturaOrigen($numeroFacturaOrigen);
        if (empty($facturaOrigen)) {
            throw new Exception("La factura origen N° {$numeroFacturaOrigen} no existe en el sistema.");
        }

        $preparedData = $this->prepararDatosTransaccionalesDevolucion($devolucion, $moneda_base, $numeroFacturaOrigen, $facturaOrigen);
        $documentos = $preparedData['documentos'];
        $movimientos = $preparedData['movimientos'];

        // Validar que no exista la devolución
        foreach ($documentos as $docArray) {

            $numero = $docArray[0];
            $tipoCodigo = $docArray[1];
            $tipoTransaccion = $docArray[10];

            if ($this->_documentoExiste($numero, $tipoCodigo, $tipoTransaccion)) {
                throw new Exception("La devolución N° {$numero} (Tipo: {$tipoCodigo}, Transacción: {$tipoTransaccion}) ya existe en la base de datos.");
            }
        }

        // Validar movimientos duplicados
        if (!empty($movimientos)) {
            $duplicado = $this->_movimientosExisten($movimientos);
            if ($duplicado) {
                throw new Exception("El movimiento para el producto '{$duplicado['producto']}' con cantidad '{$duplicado['cantidad']}' ya existe para este documento.");
            }
        }

        // Procesar en transacción
       // $this->beginTransaction();
        try {
            // Insertar documentos
            $this->_bulkInsertDocumentos($documentos);
            
            // Insertar movimientos
            if (!empty($movimientos)) {
                $this->_bulkInsertMovimientos($movimientos);
            }

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

    private function _movimientosExisten($movimientos) {
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
    

            $whereClauses[] = "(
                MCL_DCL_NUMERO = {$dcl_numero} AND
                MCL_DCL_TDT_CODIGO = {$dcl_tdt_codigo} AND
                MCL_UPP_PDT_CODIGO = {$upp_pdt_codigo} AND
                MCL_CANTIDAD = {$cantidad} AND
                MCL_BASE = {$base}
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
    
    // =============================================================
    // PREPARAR DATOS TRANSACCIONALES PARA DEVOLUCIÓN
    // =============================================================
    private function prepararDatosTransaccionalesDevolucion($devolucion, $moneda_base, $numeroFacturaOrigen, $facturaOrigen) {
        
        // Obtener el tipo de documento origen
        $tipoDocumentoOrigen = $facturaOrigen[0]['DCL_TDT_CODIGO']; // 'FAV' o 'NEN'

        $documentos = [];
        $movimientos = [];

        $idsDocumentos = $this->idsDocumentos();

        $numeroDevUltimo     = $idsDocumentos['nDev'];

        // Determinar el tipo de documento principal basado en tipo_documento de la factura
        $tipoDocPrincipal = $devolucion->tipo_documento;
        $documentos[] = $this->_prepararArrayDocumentoDevolucion($devolucion, $tipoDocPrincipal, 'D', $moneda_base, $numeroFacturaOrigen, $tipoDocumentoOrigen, ['numeroDevUltimo' => $numeroDevUltimo]);

		
		if($devolucion->empresa !== 'CRM' && $devolucion->empresa !== 'ENV_TEST'){
		
        	// Documento FAV$/NEN$ (D) si moneda base es BS
			if ($moneda_base == 'BS') {
				// Determinar el tipo de documento en dólares
				$tipoDocDolar = 'DEV$';
				
				$documentos[] = $this->_prepararArrayDocumentoDevolucion($devolucion, $tipoDocDolar, 'D', 'USD', $numeroFacturaOrigen, $tipoDocumentoOrigen, [
					'brutoUsd' => $devolucion->monto_bruto / $devolucion->valor_cambiario_dolar,
					'netoUsd' => $devolucion->neto / $devolucion->valor_cambiario_dolar,
					'baseGravadoUsd' => $devolucion->base_gravada / $devolucion->valor_cambiario_dolar,
					'exentoUsd' => $devolucion->exento / $devolucion->valor_cambiario_dolar,
					'ivaGravadoUsd' => $devolucion->iva_gravado / $devolucion->valor_cambiario_dolar,
					'baseIgtfUsd' => $devolucion->base_igtf / $devolucion->valor_cambiario_dolar,
                    'numeroDevUltimo' => $numeroDevUltimo
				]);
			}
		}
	

        // Movimientos contables (detalle)
        if (!empty($devolucion->movimientos)) {
            foreach ($devolucion->movimientos as $mov) {
                $movimientos[] = $this->_prepararArrayMovimientoDevolucion($numeroDevUltimo, $devolucion->tipo_documento, $mov, $tipoDocumentoOrigen, $numeroFacturaOrigen);
            }
        }
		
        return ['documentos' => $documentos, 
                'movimientos' => $movimientos
               ];
    }

    protected function idsDocumentos(){
        
        $sql = "SELECT 
                IFNULL((SELECT LPAD(MAX(CAST(D1.DCL_NUMERO AS UNSIGNED)) + 1, 10, 0)
                FROM ADN_DOCCLI D1
                WHERE D1.DCL_TDT_CODIGO = 'FAV'), '0000000001') AS nDoc,

                IFNULL((SELECT LPAD(MAX(CAST(D1.DCL_NUMERO AS UNSIGNED)) + 1, 10, 0)
                FROM ADN_DOCCLI D1
                WHERE D1.DCL_TDT_CODIGO = 'DEV'), '0000000001') AS nDev";

        $result = $this->select($sql);
        return $result;
    }

    // =============================================================
    // PREPARAR ARRAY DE DOCUMENTO PARA DEVOLUCIÓN
    // =============================================================
    private function _prepararArrayDocumentoDevolucion($devolucion, $tipo_documento_procesar, $tipo_doc, $base_moneda, $numeroFacturaOrigen, $tipoDocumentoOrigen, $overrides = []) {
        
        $fechaVencimiento = date("Y-m-d", strtotime("{$devolucion->fecha} + {$devolucion->plazo} days"));
        $cxc = '0';
        $tdtOrigen = ($tipo_doc === 'D') ? $tipoDocumentoOrigen : '';

        if ($tipo_documento_procesar === 'DEV' || $tipo_documento_procesar === 'DEV$') {

            $origen = ($tipo_doc === 'D') ? "{$tipoDocumentoOrigen}:{$numeroFacturaOrigen}" : (($tipo_doc === 'P') ? '' : null);
            $SistemOrigen = ($tipo_doc === 'D') ? 'MOD' : null;
        }

        $moneda = ($tipo_documento_procesar === 'DEV') ? $devolucion->moneda : 'USD';
        $neto_usd = ($tipo_documento_procesar === 'DEV') ? $devolucion->neto_usd : $devolucion->neto;
        $base_gravada_usd = ($tipo_documento_procesar === 'DEV') ? $devolucion->base_gravada_usd : $devolucion->base_gravada;
        $exento_usd = ($tipo_documento_procesar === 'DEV') ? $devolucion->exento_usd : $devolucion->exento;
        $iva_gravado_usd = ($tipo_documento_procesar === 'DEV') ? $devolucion->iva_gravado_usd : $devolucion->iva_gravado;

        return [
            $overrides['numeroDevUltimo'] ?? $devolucion->numero, $tipo_documento_procesar, $overrides['referencia'] ?? '', $devolucion->vendedor->codigo, $devolucion->cliente->codigo, $devolucion->fecha, 
            $this->sanitizeNumber($overrides['netoUsd'] ?? $devolucion->neto), $this->sanitizeNumber(($overrides['baseGravadoUsd'] ?? $devolucion->base_gravada)), 
            $this->sanitizeNumber(($overrides['exentoUsd'] ?? $devolucion->exento)), $devolucion->serie_fiscal, $tipo_doc, $cxc, $devolucion->activo, $devolucion->estado_documento, 
            $this->sanitizeNumber($devolucion->descuento_porcentual ?? 0), "{$devolucion->fecha} {$devolucion->hora}", $devolucion->numero_impresion_fiscal, 
            $this->sanitizeNumber(($overrides['ivaGravadoUsd'] ?? $devolucion->iva_gravado)), $devolucion->hora, $devolucion->plazo, $devolucion->condicion, $fechaVencimiento, $tdtOrigen, $origen, '01', 
            $this->sanitizeNumber(($overrides['brutoUsd'] ?? $devolucion->monto_bruto)), $devolucion->usuario, $devolucion->estacion, $devolucion->ip, $SistemOrigen, $devolucion->sucursal->codigo,
            $this->sanitizeNumber($devolucion->valor_cambiario_dolar), $moneda, $this->sanitizeNumber($devolucion->valor_cambiario_peso), $this->sanitizeNumber($neto_usd), 
            $this->sanitizeNumber($base_gravada_usd), $this->sanitizeNumber($exento_usd), $this->sanitizeNumber($iva_gravado_usd), 
            $this->sanitizeNumber(($overrides['baseIgtfUsd'] ?? $devolucion->base_igtf))
        ];
    }

    // =============================================================
    // PREPARAR ARRAY DE MOVIMIENTO PARA DEVOLUCIÓN
    // =============================================================
    private function _prepararArrayMovimientoDevolucion($numero_doc, $tipo_doc, $mov, $tipoDocumentoOrigen, $numeroFacturaOrigen) {

        $valorInv = ($tipo_doc == 'DEV') ? "1" : "-1";
        $iva = ($mov->tipo_iva == 'GN') ? "16.00" : "0.00";
        $id_iva = ($mov->tipo_iva == 'GN') ? "13" : "1";

        return [
            $id_iva, '000001', $numero_doc, $tipo_doc, $mov->almacen->codigo, $mov->producto->codigo, 
            $mov->unidad->codigo, '', $mov->transaccion, $mov->cantidad, $valorInv, $valorInv, $valorInv, 
            $this->sanitizeNumber($mov->descuento_porcentual ?? 0), $this->sanitizeNumber($mov->precio), 'D', $mov->tipo_lista_precio, $mov->cantidad, $iva, 
            $mov->tipo_iva, '1', $mov->descripcion, $tipoDocumentoOrigen, "{$tipoDocumentoOrigen}:{$numeroFacturaOrigen}", '', $this->sanitizeNumber($mov->costo)
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

    private function _bulkInsertMovimientos(array $data) {
        if (empty($data)) return;

        $columnas = "MCL_HTI_ID, MCL_DCL_SCS_CODIGO, MCL_DCL_NUMERO, MCL_DCL_TDT_CODIGO, MCL_AMC_CODIGO, MCL_UPP_PDT_CODIGO, 
                    MCL_UPP_UND_ID, MCL_DCL_REC_NUMERO, MCL_CTR_CODIGO, MCL_CANTIDAD, MCL_FISICO, MCL_LOGICO, MCL_CONTABLE, 
                    MCL_PORDCTO, MCL_BASE, MCL_DCL_TIPTRA, MCL_PLT_LISTA, MCL_CANTXUND, MCL_PORIVA, MCL_TIVACOD, MCL_METCOS, 
                    MCL_DESCRI, MCL_TDT_DOCIMPORT, MCL_NUM_DOCIMPORT, MCL_UBP_CODIGO, MCL_COSTOUSD";
        
        $valueStrings = [];
        foreach ($data as $fila) {
            $sanitizedValues = array_map([$this->conexion, 'quote'], $fila);
            $valueStrings[] = "(" . implode(',', $sanitizedValues) . ")";
        }

        $this->insert_massive("INSERT INTO adn_movcli ($columnas) VALUES " . implode(', ', $valueStrings));
    }

    // =============================================================
    // MÉTODOS DE INSERCIÓN MASIVA DE MAESTROS (copiados de DocumentoModel)
    // =============================================================
    private function _insertarCentroCosto($data) {
        if (empty($data)) return;

        $checkSucursal = $this->select("SELECT COUNT(*) as total FROM adn_centrocostos WHERE CCT_CODIGO = '$data->codigo'");
        if ($checkSucursal['total'] == 0) {
            $sqlSucursal = "INSERT INTO adn_centrocostos (CCT_CODIGO, CCT_DESCRIPCION, CCT_DIRECCION) VALUES (?, ?, ?)";
            $this->insert($sqlSucursal, [
                $data->codigo,
                $data->nombre,
                $data->direccion ?? ''
            ]);
        }
    }
    
    private function _bulkInsertOrUpdateSimple(string $tabla, array $columnas, array $data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ") VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE {$columnas[1]} = VALUES({$columnas[1]})";

        $valueStrings = [];
        foreach ($data as $codigo => $obj) {
            $codigoSanitized = $this->conexion->quote($codigo);
            $descriSanitized = $this->conexion->quote($obj->descripcion);
            $valueStrings[] = "({$codigoSanitized}, {$descriSanitized})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    private function _bulkInsertOrUpdateSimpleGrupo(string $tabla, array $columnas, array $data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ") VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE {$columnas[1]} = VALUES({$columnas[1]})";

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
        $sql_final = " ON DUPLICATE KEY UPDATE {$columnas[1]} = VALUES({$columnas[1]})";

        $valueStrings = [];
        foreach ($data as $codigo => $obj) {
            $codigoSanitized = $this->conexion->quote($codigo);
            $nombreSanitized = $this->conexion->quote($obj->nombre);
            $activoSanitized = $this->conexion->quote($obj->activo);
            $lptSanitized = $this->conexion->quote($obj->lpt);
            $tipoSanitized = $this->conexion->quote($obj->tipo);

            $valueStrings[] = "({$codigoSanitized}, {$nombreSanitized}, {$activoSanitized}, {$lptSanitized}, {$tipoSanitized})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    private function _bulkInsertOrUpdateSimpleSerieFiscal(string $tabla, array $columnas, $data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ") VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE SFI_MODELO = VALUES(SFI_MODELO)";

        $valueStrings = [];
        $modelo = $this->conexion->quote('SERIE '.$data);
        $serie = $this->conexion->quote($data);
        $valueStrings[] = "({$modelo}, {$serie})";
        
        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    private function _bulkInsertOrUpdateClientes($data) {
        if (empty($data)) return;

        $checkCliente = $this->select("SELECT COUNT(*) as total FROM adn_clientes WHERE CLT_CODIGO = '$data->codigo'");
        if ($checkCliente['total'] == 0) {
            $sqlCliente = "INSERT INTO adn_clientes (CLT_CODIGO, CLT_NOMBRE, CLT_RIF, CLT_ACTIVO) VALUES (?, ?, ?, ?)";
            $this->insert($sqlCliente, [
                $data->codigo,
                $data->nombre,
                $data->rif,
                1
            ]);
        }
    }

    private function _bulkInsertOrUpdateVendedores($data) {
        if (empty($data)) return;

        $checkVendedor = $this->select("SELECT COUNT(*) as total FROM adn_vendedores WHERE VEN_CODIGO = '$data->codigo'");
        if ($checkVendedor['total'] == 0) {
            $sqlVendedor = "INSERT INTO adn_vendedores (VEN_CODIGO, VEN_NOMBRE, VEN_APELLIDO, VEN_ACTIVO) VALUES (?, ?, ?, ?)";
            $this->insert($sqlVendedor, [
                $data->codigo,
                $data->nombre,
                $data->apellido,
                1
            ]);
        }
    }

    private function _bulkInsertOrUpdateVehiculo($data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO adn_contrato_veh (
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

        $values = [
        $this->conexion->quote($data->documento),
        $this->conexion->quote('1'),
        $this->conexion->quote($data->marca),
        $this->conexion->quote($data->modelo),
        $this->conexion->quote($data->placa),
        $this->conexion->quote($data->puestos),
        $this->conexion->quote($data->color),
        $this->conexion->quote($data->capacidad_carga),
        $this->conexion->quote($data->peso),
        $this->conexion->quote($data->año),
        $this->conexion->quote($data->clase),
        $this->conexion->quote($data->tipo),
        $this->conexion->quote($data->uso),
        $this->conexion->quote($data->serial_carroceria),
        $this->conexion->quote($data->serial_motor),
        $this->conexion->quote($data->cuadro_poliza)
        ];

        $this->insert_massive($sql_inicio . '(' . implode(',', $values) . ')' . $sql_final);
    }

    private function _bulkInsertOrUpdatePaciente($data) {
        if (empty($data)) return;

        $sql_inicio = "INSERT INTO adn_personal (
                    PCL_CODIGO, PCL_NOMBRE, PCL_CELULAR, PCL_DIRECCION, PCL_RIF
            ) VALUES ";

        $sql_final = " ON DUPLICATE KEY UPDATE
                PCL_NOMBRE=VALUES(PCL_NOMBRE),
                PCL_DIRECCION=VALUES(PCL_DIRECCION),
                PCL_CELULAR=VALUES(PCL_CELULAR)";

        $values = [
        $this->conexion->quote($data->codeCliente),
        $this->conexion->quote($data->nombre),
        $this->conexion->quote($data->celular),
        $this->conexion->quote($data->direccion),
        $this->conexion->quote($data->rif)
        ];

        $this->insert_massive($sql_inicio . '(' . implode(',', $values) . ')' . $sql_final);
    }

    private function _bulkInsertOrUpdateProductos($data) {
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

    private function _bulkInsertUndagruLogic($data) {
        
        if (empty($data->unidadesAgru)) return;
   
        $sql_inicio = "INSERT INTO adn_undagru (UGR_PDT_CODIGO, UGR_UND_ID, UGR_CANXUND) VALUES ";
        $sql_final = " ON DUPLICATE KEY UPDATE UGR_CANXUND = VALUES(UGR_CANXUND)";

        $valueStrings = [];
        foreach ($data->unidadesAgru as $item) {
            $codigoProducto = $this->conexion->quote($item->producto->codigo);
            $codigoUnidad = $this->conexion->quote($item->unidad->codigo);
            $cantidad = $this->conexion->quote($item->cantidad);
                
            $valueStrings[] = "({$codigoProducto}, {$codigoUnidad}, {$cantidad})";
        }

        $this->insert_massive($sql_inicio . implode(', ', $valueStrings) . $sql_final);
    }

    private function sanitizeNumber($value) {
        return floatval(str_replace(',', '.', $value));
    }
}
?>
