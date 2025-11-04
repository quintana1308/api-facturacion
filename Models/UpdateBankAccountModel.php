<?php 

class UpdateBankAccountModel extends Mysql {

    public function __construct($conectEnterprise) {  

        parent::__construct($conectEnterprise);
    }

    // =============================================================
    // MÉTODO PARA PROCESAR UNA ÚNICA FACTURA CON SUS DOCUMENTOS
    // =============================================================
    public function procesarBankAccount($factura) {
	
        // 1. Preparamos los datos de los documentos para esta factura
        $preparedData = $this->prepararDatosTransaccionalesBancarios($factura);
        $movBancario = $preparedData['movBancario'];

        try {

            $this->_bulkUpdateMovBank($movBancario);

        } catch (Exception $e) {
            $this->rollBack();
            throw $e; 
        }
    }
    
    // =================================================================================
    // MÉTODOS "PREPARADORES" DE DATOS
    // =================================================================================
    public function prepararDatosTransaccionalesBancarios($factura) {
        $movBancario = [];

        $movBancario[] = $this->_prepararArrayMovBancario($factura);

        return ['movBancario' => $movBancario];
    }

    private function _prepararArrayMovBancario($factura) {

        return [
            $factura
        ];
    }

    private function _bulkUpdateMovBank($data) {
        if (empty($data)) return;

        foreach ($data as $key => $value) {

            $codBanco = $value[$key]->movimiento->banco->codigo;
            $nameBanco = $value[$key]->movimiento->banco->nombre;
            $estadoBanco = $value[$key]->movimiento->banco->activo;

            $checkBanco = $this->select("SELECT COUNT(*) as total FROM adn_bancos WHERE BCO_CODIGO = '$codBanco'");
            if ($checkBanco['total'] == 0) {
                $sqlBanco = "INSERT INTO adn_bancos (BCO_CODIGO, BCO_NOMBRE, BCO_ACTIVO) VALUES (?, ?, ?)";
                $this->insert($sqlBanco, [
                $codBanco,
                $nameBanco,
                $estadoBanco
                ]);
            }

            $codCuenta = $value[$key]->movimiento->banco->cuenta->numero;
            $nameCuenta = $value[$key]->movimiento->banco->cuenta->titular;
            $estadoCuenta = $value[$key]->movimiento->banco->cuenta->activo;
            $modCuenta = $value[$key]->movimiento->banco->cuenta->moneda;

            $checkCuenta = $this->select("SELECT COUNT(*) AS total FROM adn_ctabanco WHERE CBC_BCO_CODIGO = '$codBanco' AND CBC_CUENTA = '$codCuenta'");
            if ($checkCuenta['total'] == 0) {
                $sqlCuenta = "INSERT INTO adn_ctabanco (CBC_BCO_CODIGO, CBC_CUENTA, CBC_TITULAR, CBC_ACTIVO, CBC_SUCURSAL, CBC_TIPOM) VALUES (?, ?, ?, ?, ?, ?)";
                $this->insert($sqlCuenta, [
                $codBanco,       // CBC_BCO_CODIGO
                $codCuenta,      // CBC_CUENTA
                $nameCuenta,     // CBC_TITULAR
                $estadoCuenta,      // CBC_ACTIVO
                '000001',
                $modCuenta
            ]);
            }

            $codRecibo = $value[$key]->recibo;
            $refTransaccionAntigua = $value[$key]->movimiento->referencia_antigua;
            $refTransaccion = $value[$key]->movimiento->referencia_nueva;
            $dateTransaccion = $value[$key]->movimiento->fecha;
            $hourTransaccion = $value[$key]->movimiento->hora;
            $typeTransaccion = $value[$key]->movimiento->tipo_operacion;

            $sql = "UPDATE adn_movbco 
					SET MBC_NUMERO = ?, MBC_FECHA = ?, MBC_HORA = ?, MBC_OBC_TIPO = ?, MBC_CBC_BCO_CODIGO = ?, MBC_CBC_CUENTA = ?
					WHERE MBC_NUMERO = ?
                    AND MBC_REC_NUMERO = ?";

            $arrayValues = array($refTransaccion, $dateTransaccion, $hourTransaccion, $typeTransaccion, $codBanco, $codCuenta, $refTransaccionAntigua, $codRecibo);
            $this->update($sql, $arrayValues);
        }
    }
}
?>