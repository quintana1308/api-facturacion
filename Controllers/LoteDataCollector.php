<?php
// ARCHIVO: Controllers/LoteDataCollector.php

class LoteDataCollector {
    
    public $seriesFiscales = [];
    public $clientes = [];
    public $vendedores = [];
    public $vehiculos = [];
    public $pacientes = [];
    public $marcas = [];
    public $departamentos = [];
    public $grupos = [];
    public $categorias = [];
    public $versiones = [];
    public $productos = [];
    public $almacenes = [];
    public $unidades = [];
    public $unidadesAgru = [];
    public $bancos = [];
    public $sucursales = [];

    public function addSerieFiscal($data) { if ($data && !isset($this->seriesFiscales[$data])) $this->seriesFiscales[$data] = $data; }
    public function addCliente($data) { if ($data && !isset($this->clientes[$data->codigo])) $this->clientes[$data->codigo] = $data; }
    public function addVendedor($data) { if ($data && !isset($this->vendedores[$data->codigo])) $this->vendedores[$data->codigo] = $data; }

    public function addVehiculo($data) { if ($data && !isset($this->vehiculos[$data->documento])) $this->vehiculos[$data->documento] = $data; }
    public function addPaciente($data) { if ($data && !isset($this->pacientes[$data->codeCliente])) $this->pacientes[$data->codeCliente] = $data; }

    public function addMarca($data) { if ($data && !isset($this->marcas[$data->codigo])) $this->marcas[$data->codigo] = $data; }
    public function addDepartamento($data) { if ($data && !isset($this->departamentos[$data->codigo])) $this->departamentos[$data->codigo] = $data; }
    public function addGrupo($data) { if ($data && !isset($this->grupos[$data->codigo])) $this->grupos[$data->codigo] = $data; }
    public function addCategoria($data) { if ($data && !isset($this->categorias[$data->codigo])) $this->categorias[$data->codigo] = $data; }
    public function addVersion($data) { if ($data && !isset($this->versiones[$data->codigo])) $this->versiones[$data->codigo] = $data; }
    public function addAlmacen($data) { if ($data && !isset($this->almacenes[$data->codigo])) $this->almacenes[$data->codigo] = $data; }
    public function addUnidad($data) { if ($data && !isset($this->unidades[$data->codigo])) $this->unidades[$data->codigo] = $data; }
    public function addUnidadAgru($data) { if ($data && isset($data->codigo) && !isset($this->unidadesAgru[$data->producto->codigo])) $this->unidadesAgru[$data->producto->codigo] = $data; }

    public function addBanco($data, $sucursal = null) { 
        if ($data && !isset($this->bancos[$data->codigo])) {
            $this->bancos[$data->codigo] = $data; 
        }
    }
    
    public function addCentroCosto($data) { if ($data && !isset($this->sucursales[$data->codigo])) $this->sucursales[$data->codigo] = $data; }
    
    public function addProducto($data) {
        if ($data && !isset($this->productos[$data->codigo])) {
            $this->productos[$data->codigo] = $data;
            // Recolecta las entidades anidadas del producto
            $this->addMarca($data->marca ?? null);
            $this->addDepartamento($data->departamento ?? null);
            $this->addGrupo($data->grupo ?? null);
            $this->addCategoria($data->categoria ?? null);
            $this->addVersion($data->version ?? null);
        }
    }
}
?>