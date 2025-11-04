<?php
// ARCHIVO: Libraries/Core/Validators.php

class Validators {
    
    public static function validateFacturaCompleta($factura) {
        $numFactura = $factura->numero ?? 'SIN_NUMERO';
        $missing = [];

        // Validaciones de la cabecera de la factura
		if (empty($factura->numero)) $missing[] = 'numero';
		if (empty($factura->tipo_documento)) $missing[] = 'tipo_documento';
		if (empty($factura->ip)) $missing[] = 'ip';
		if (empty($factura->serie_fiscal)) $missing[] = 'serie_fiscal';
		if (empty($factura->estado_documento)) $missing[] = 'estado_documento';
		if (empty($factura->igtf)) $missing[] = 'igtf';
		if (empty($factura->fecha)) $missing[] = 'fecha';
		if (empty($factura->hora)) $missing[] = 'hora';
		if (empty($factura->hora)) $missing[] = 'hora';
		if (empty($factura->monto_bruto)) $missing[] = 'monto_bruto';
		if (empty($factura->neto_usd)) $missing[] = 'neto_usd';
		if (empty($factura->base_gravada)) $missing[] = 'base_gravada';
		if (empty($factura->base_gravada_usd)) $missing[] = 'base_gravada_usd';
		if (empty($factura->exento)) $missing[] = 'exento';
		if (empty($factura->exento_usd)) $missing[] = 'exento_usd';
		if (empty($factura->iva_gravado)) $missing[] = 'iva_gravado';
		if (empty($factura->iva_gravado_usd)) $missing[] = 'iva_gravado_usd';
		if (empty($factura->valor_cambiario_dolar)) $missing[] = 'valor_cambiario_dolar';
		if (empty($factura->valor_cambiario_peso)) $missing[] = 'valor_cambiario_peso';
		if (empty($factura->moneda)) $missing[] = 'moneda';

        // ... añade todas las validaciones de cabecera que necesites

        if (!empty($missing)) {
            throw new Exception("Factura [{$numFactura}]: Faltan campos de cabecera: " . implode(', ', $missing));
        }

        // Validaciones de objetos anidados
        self::validateCliente($factura->cliente, $numFactura);
        self::validateVendedor($factura->vendedor, $numFactura);

        if (!empty($factura->movimientos) && is_array($factura->movimientos)) {
            foreach ($factura->movimientos as $mov) {
                self::validateMovimiento($mov, $numFactura);
            }
        } else {
            throw new Exception("Factura [{$numFactura}]: Debe contener un array de 'movimientos'.");
        }
    }
    
    public static function validateCliente($data, $numFactura) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}]: Objeto 'cliente' es obligatorio.");
        $missing = [];

        if (empty($data->codigo)) $missing[] = 'cliente.codigo';
        if (empty($data->nombre)) $missing[] = 'cliente.nombre';
        if (empty($data->rif)) $missing[] = 'cliente.rif';
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

    public static function validateMovimiento($data, $numFactura) {
        //$numMov = $data->codigo ?? 'SIN_CODIGO';
        if (empty($data)) throw new Exception("Factura [{$numFactura}]: Movimiento inválido.");
        $missing = [];

        $codProdu = $data->producto->codigo ?? 'SIN CODIGO DE PRODUCTO';

        //if (empty($data->codigo)) $missing[] = 'movimiento.codigo';
        if (empty($data->precio)) $missing[] = 'movimiento.precio';
        if (empty($data->tipo_lista_precio)) $missing[] = 'movimiento.tipo_lista_precio';
        if (empty($data->tipo_iva)) $missing[] = 'movimiento.tipo_iva';
        if (empty($data->descripcion)) $missing[] = 'movimiento.descripcion';
        if (empty($data->costo)) $missing[] = 'movimiento.costo';
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

        if (!empty($missing)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: " . implode(', ', $missing));
    }

    public static function validateUnidad($data, $numFactura, $codProdu) {
        if (empty($data)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: Objeto 'Unidad' es obligatorio.");
        $missing = [];
        if (empty($data->codigo)) $missing[] = 'producto.codigo';

        if (!empty($missing)) throw new Exception("Factura [{$numFactura}], Mov del Producto[{$codProdu}]: " . implode(', ', $missing));
    }
}
?>