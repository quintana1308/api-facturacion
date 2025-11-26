<?php
// ARCHIVO: Libraries/Core/DocumentoValidators.php

class DocumentoValidators {
    
    public static function validateFacturaCompleta($factura) {
        $numFactura = $factura->numero ?? 'SIN_NUMERO';
        $missing = [];

        // Validaciones de la cabecera de la factura
		if (empty($factura->numero)) $missing[] = 'numero';
		if (empty($factura->tipo_documento)) $missing[] = 'tipo_documento';
		if (empty($factura->ip)) $missing[] = 'ip';
		if (empty($factura->serie_fiscal)) $missing[] = 'serie_fiscal';
		if (empty($factura->estado_documento)) $missing[] = 'estado_documento';
        if (empty($factura->sucursal)) $missing[] = 'sucursal';
		if (empty($factura->fecha)) $missing[] = 'fecha';
		if (empty($factura->hora)) $missing[] = 'hora';
		if (empty($factura->activo)) $missing[] = 'activo';
        if (!property_exists($factura, 'numero_impresion_fiscal') || is_null($factura->numero_impresion_fiscal)) { $missing[] = 'numero_impresion_fiscal'; }
        if (empty($factura->plazo)) $missing[] = 'plazo';
        if (empty($factura->condicion)) $missing[] = 'condicion';
        if (empty($factura->igtf)) $missing[] = 'igtf';
        if (empty($factura->base_igtf)) $missing[] = 'base_igtf';
        if (empty($factura->usuario)) $missing[] = 'usuario';
        if (empty($factura->estacion)) $missing[] = 'estacion';
        if (empty($factura->moneda)) $missing[] = 'moneda';
		if (empty($factura->monto_bruto)) $missing[] = 'monto_bruto';
        if (empty($factura->neto)) $missing[] = 'neto';
		if (!property_exists($factura, 'neto_usd') || is_null($factura->neto_usd)) $missing[] = 'neto_usd';
		if (empty($factura->base_gravada)) $missing[] = 'base_gravada';
		if (!property_exists($factura, 'base_gravada_usd') || is_null($factura->base_gravada_usd)) $missing[] = 'base_gravada_usd';
		if (empty($factura->exento)) $missing[] = 'exento';
		if (!property_exists($factura, 'exento_usd') || is_null($factura->exento_usd)) $missing[] = 'exento_usd';
		if (empty($factura->iva_gravado)) $missing[] = 'iva_gravado';
		if (!property_exists($factura, 'iva_gravado_usd') || is_null($factura->iva_gravado_usd)) $missing[] = 'iva_gravado_usd';
        if (empty($factura->descuento_porcentual)) $missing[] = 'descuento_porcentual';
		if (empty($factura->valor_cambiario_dolar)) $missing[] = 'valor_cambiario_dolar';
		if (empty($factura->valor_cambiario_peso)) $missing[] = 'valor_cambiario_peso';
		

        // ... añade todas las validaciones de cabecera que necesites

        if (!empty($missing)) {
            throw new Exception("Factura [{$numFactura}]: Faltan campos de cabecera: " . implode(', ', $missing));
        }

        // Validaciones de objetos anidados
        self::validateCliente($factura->cliente, $numFactura);
        self::validateVendedor($factura->vendedor, $numFactura);
        self::validateVehiculo($factura->vehiculo ?? null, $numFactura);

        if (!empty($factura->movimientos) && is_array($factura->movimientos)) {
            foreach ($factura->movimientos as $mov) {
                self::validateMovimiento($mov, $numFactura);
            }
        } else {
            throw new Exception("Factura [{$numFactura}]: Debe contener un array de 'movimientos'.");
        }

        if (!empty($factura->recibo)) {
            self::validateRecibo($factura->recibo, $numFactura);
        }
    }
    
    public static function validateCliente($data, $numFactura) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}]: Objeto 'cliente' es obligatorio.");
        $missing = [];

        if (empty($data->codigo)) $missing[] = 'cliente.codigo';
        if (empty($data->nombre)) $missing[] = 'cliente.nombre';
        if (empty($data->rif)) $missing[] = 'cliente.rif';

        // Validar paciente si viene
        if (!empty($data->paciente)) {
            $paciente = $data->paciente;
            if (empty($paciente->rif))       $missing[] = 'cliente.paciente.rif';
            if (empty($paciente->nombre))    $missing[] = 'cliente.paciente.nombre';
        }

        if (!empty($missing)) throw new Exception("Factura [{$numFactura}]: " . implode(', ', $missing));
    }
    
    public static function validateVendedor($data, $numFactura) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}]: Objeto 'vendedor' es obligatorio.");
        $missing = [];

        if (empty($data->codigo)) $missing[] = 'vendedor.codigo';
        if (empty($data->nombre)) $missing[] = 'vendedor.nombre';
        if (!isset($data->apellido)) $missing[] = 'vendedor.apellido';
        if (!empty($missing)) throw new Exception("Factura [{$numFactura}]: " . implode(', ', $missing));
    }

    public static function validateVehiculo($data, $numFactura) {
        if (empty($data)) return; // No es obligatorio, así que si no viene, simplemente no validar

        $missing = [];

        if (empty($data->cuadro_poliza))       $missing[] = 'vehiculo.cuadro_poliza';
        if (empty($data->placa))               $missing[] = 'vehiculo.placa';
        if (empty($data->marca))               $missing[] = 'vehiculo.marca';
        if (empty($data->modelo))              $missing[] = 'vehiculo.modelo';
        if (empty($data->puestos))             $missing[] = 'vehiculo.puestos';
        if (empty($data->color))               $missing[] = 'vehiculo.color';
        if (empty($data->capacidad_carga))     $missing[] = 'vehiculo.capacidad_carga';
        if (empty($data->peso))                $missing[] = 'vehiculo.peso';
        if (empty($data->año))                 $missing[] = 'vehiculo.año';
        if (empty($data->clase))               $missing[] = 'vehiculo.clase';
        if (empty($data->tipo))                $missing[] = 'vehiculo.tipo';
        if (empty($data->uso))                 $missing[] = 'vehiculo.uso';
        if (empty($data->serial_carroceria))   $missing[] = 'vehiculo.serial_carroceria';
        if (empty($data->serial_motor))        $missing[] = 'vehiculo.serial_motor';

        if (!empty($missing)) {
            throw new Exception("Factura [{$numFactura}]: " . implode(', ', $missing));
        }
    }

    public static function validateMovimiento($data, $numFactura) {
        //$numMov = $data->codigo ?? 'SIN_CODIGO';
        if (empty($data)) throw new Exception("Factura [{$numFactura}]: Movimiento inválido.");
        $missing = [];

        $codProdu = $data->producto->codigo ?? 'SIN CODIGO DE PRODUCTO';

        //if (empty($data->codigo)) $missing[] = 'movimiento.codigo';
        if (empty($data->cantidad)) $missing[] = 'movimiento.cantidad';
        if (empty($data->precio)) $missing[] = 'movimiento.precio';
        if (empty($data->costo)) $missing[] = 'movimiento.costo';
        if (empty($data->tipo_iva)) $missing[] = 'movimiento.tipo_iva';
        if (empty($data->descripcion)) $missing[] = 'movimiento.descripcion';
         if (empty($data->descuento_porcentual)) $missing[] = 'movimiento.descuento_porcentual';
        if (empty($data->tipo_lista_precio)) $missing[] = 'movimiento.tipo_lista_precio';
        if (empty($data->transaccion)) $missing[] = 'movimiento.transaccion';
        if (empty($data->almacen)) $missing[] = 'movimiento.almacen';
        if (empty($data->unidad)) $missing[] = 'movimiento.unidad';
        if (empty($data->producto)) $missing[] = 'movimiento.producto';

        if (!empty($missing)) throw new Exception("Factura [{$numFactura}], Movimiento del producto [{$codProdu}]: " . implode(', ', $missing));
        
        // Validar sub-objetos del movimiento
        self::validateAlmacen($data->almacen, $numFactura, $codProdu);
        self::validateUnidad($data->unidad, $numFactura, $codProdu);
        self::validateProducto($data->producto, $numFactura, $codProdu);
    }
    
    public static function validateProducto($data, $numFactura, $codProdu) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: Objeto 'producto' es obligatorio.");
        $missing = [];

        if (empty($data->codigo)) $missing[] = 'producto.codigo';
        if (empty($data->descripcion)) $missing[] = 'producto.descripcion';
        if (empty($data->descripcion)) $missing[] = 'producto.estado';
        if (empty($data->descripcion)) $missing[] = 'producto.tipo_costo';
        if (empty($data->marca) || empty($data->marca->codigo)) $missing[] = 'producto.marca.codigo';
        if (empty($data->departamento) || empty($data->departamento->codigo)) $missing[] = 'producto.departamento.codigo';
        if (empty($data->grupo) || empty($data->grupo->codigo)) $missing[] = 'producto.grupo.codigo';
        if (empty($data->categoria) || empty($data->categoria->codigo)) $missing[] = 'producto.categoria.codigo';
        if (empty($data->version) || empty($data->version->codigo)) $missing[] = 'producto.version.codigo';
        
        if (!empty($missing)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: " . implode(', ', $missing));
    }

    public static function validateAlmacen($data, $numFactura, $codProdu) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: Objeto 'almacen' es obligatorio.");
        $missing = [];
        if (empty($data->codigo)) $missing[] = 'almacen.codigo';
        if (empty($data->nombre)) $missing[] = 'almacen.nombre';
        if (!isset($data->lpt)) $missing[] = 'almacen.lpt';
        if (empty($data->tipo)) $missing[] = 'almacen.tipo';
        if (empty($data->activo)) $missing[] = 'almacen.activo';

        if (!empty($missing)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: " . implode(', ', $missing));
    }

    public static function validateUnidad($data, $numFactura, $codProdu) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: Objeto 'Unidad' es obligatorio.");
        $missing = [];
        if (empty($data->codigo)) $missing[] = 'unidad.codigo';

        if (!empty($missing)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: " . implode(', ', $missing));
    }

    public static function validateRecibo($data, $numFactura) {
        if (empty($data)) return;
        
        $missing = [];

        if (empty($data->codigo)) $missing[] = 'recibo.codigo';
        if (empty($data->monto)) $missing[] = 'recibo.monto';
        if (empty($data->fecha)) $missing[] = 'recibo.fecha';

        if (!empty($missing)) {
            throw new Exception("Factura [{$numFactura}]: " . implode(', ', $missing));
        }

        if (!isset($data->movimientos) || !is_array($data->movimientos) || count($data->movimientos) === 0) {
            throw new Exception("Factura [{$numFactura}]: El recibo debe contener al menos un movimiento.");
        }

        foreach ($data->movimientos as $mov) {
            self::validateMovimientoRecibo($mov, $numFactura, $mov->referencia);
        }
    }

    public static function validateMovimientoRecibo($data, $numFactura, $refMovimientos) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}] del movimento [{$refMovimientos}]: Movimiento de recibo inválido.");

        $missing = [];

        if (empty($data->referencia)) $missing[] = 'recibo.movimiento.referencia';
        if (empty($data->fecha)) $missing[] = 'recibo.movimiento.fecha';
        if (empty($data->hora)) $missing[] = 'recibo.movimiento.hora';
        if (empty($data->monto)) $missing[] = 'recibo.movimiento.monto';
        if (empty($data->monto_usd)) $missing[] = 'recibo.movimiento.monto_usd';
        if (empty($data->moneda)) $missing[] = 'recibo.movimiento.moneda';
        if (empty($data->tipo_operacion)) $missing[] = 'recibo.movimiento.tipo_operacion';
        if (empty($data->codigo_caja)) $missing[] = 'recibo.movimiento.codigo_caja';
        if (!isset($data->pago_igtf)) $missing[] = 'recibo.movimiento.pago_igtf';
        if (empty($data->igtf)) $missing[] = 'recibo.movimiento.igtf';
        if (empty($data->base_igtf)) $missing[] = 'recibo.movimiento.base_igtf';
        if (!isset($data->activo)) $missing[] = 'recibo.movimiento.activo';
        if (empty($data->tipo_movimiento)) $missing[] = 'recibo.movimiento.tipo_movimiento';

        // Validar banco y cuenta si existen
        if (empty($data->banco)) {
            $missing[] = 'recibo.movimiento.banco';
        } else {
            if (empty($data->banco->codigo)) $missing[] = 'recibo.movimiento.banco.codigo';
            if (empty($data->banco->nombre)) $missing[] = 'recibo.movimiento.banco.nombre';
            if (!isset($data->banco->activo)) $missing[] = 'recibo.movimiento.banco.activo';

            if (empty($data->banco->cuenta)) {
                $missing[] = 'recibo.movimiento.banco.cuenta';
            } else {
                $cuenta = $data->banco->cuenta;
                if (empty($cuenta->numero)) $missing[] = 'recibo.movimiento.banco.cuenta.numero';
                if (empty($cuenta->tipo)) $missing[] = 'recibo.movimiento.banco.cuenta.tipo';
                if (empty($cuenta->titular)) $missing[] = 'recibo.movimiento.banco.cuenta.titular';
                if (empty($cuenta->moneda)) $missing[] = 'recibo.movimiento.banco.cuenta.moneda';
                if (!isset($cuenta->activo)) $missing[] = 'recibo.movimiento.banco.cuenta.activo';
            }
        }

        if (!empty($missing)) {
            throw new Exception("Factura [{$numFactura}]: " . implode(', ', $missing));
        }
    }
}
?>