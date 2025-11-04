<?php

class NotaCreditoValidators {
    
    public static function validateNotaCreditoCompleta($notaCredito) {
        $missing = [];

        // Validaciones de la cabecera de la nota de crédito
		if (empty($notaCredito->numero)) $missing[] = 'numero';
		if (empty($notaCredito->tipo_documento)) $missing[] = 'tipo_documento';
		if (empty($notaCredito->ip)) $missing[] = 'ip';
		if (empty($notaCredito->serie_fiscal)) $missing[] = 'serie_fiscal';
		if (empty($notaCredito->estado_documento)) $missing[] = 'estado_documento';
        if (empty($notaCredito->sucursal)) $missing[] = 'sucursal';
		if (empty($notaCredito->fecha)) $missing[] = 'fecha';
		if (empty($notaCredito->hora)) $missing[] = 'hora';
		if (empty($notaCredito->activo)) $missing[] = 'activo';
        if (!property_exists($notaCredito, 'numero_impresion_fiscal') || is_null($notaCredito->numero_impresion_fiscal)) { $missing[] = 'numero_impresion_fiscal'; }
        if (empty($notaCredito->plazo)) $missing[] = 'plazo';
        if (empty($notaCredito->condicion)) $missing[] = 'condicion';
        if (empty($notaCredito->igtf)) $missing[] = 'igtf';
        if (empty($notaCredito->base_igtf)) $missing[] = 'base_igtf';
        if (empty($notaCredito->usuario)) $missing[] = 'usuario';
        if (empty($notaCredito->estacion)) $missing[] = 'estacion';
        if (empty($notaCredito->moneda)) $missing[] = 'moneda';
		if (empty($notaCredito->monto_bruto)) $missing[] = 'monto_bruto';
        if (empty($notaCredito->neto)) $missing[] = 'neto';
		if (empty($notaCredito->neto_usd)) $missing[] = 'neto_usd';
		if (empty($notaCredito->base_gravada)) $missing[] = 'base_gravada';
		if (empty($notaCredito->base_gravada_usd)) $missing[] = 'base_gravada_usd';
		if (empty($notaCredito->exento)) $missing[] = 'exento';
		if (empty($notaCredito->exento_usd)) $missing[] = 'exento_usd';
		if (empty($notaCredito->iva_gravado)) $missing[] = 'iva_gravado';
		if (empty($notaCredito->iva_gravado_usd)) $missing[] = 'iva_gravado_usd';
        if (empty($notaCredito->descuento_porcentual)) $missing[] = 'descuento_porcentual';
		if (empty($notaCredito->valor_cambiario_dolar)) $missing[] = 'valor_cambiario_dolar';
		if (empty($notaCredito->valor_cambiario_peso)) $missing[] = 'valor_cambiario_peso';
        if (empty($notaCredito->cliente)) $missing[] = 'cliente';
        if (empty($notaCredito->vendedor)) $missing[] = 'vendedor';
    }
}
?>