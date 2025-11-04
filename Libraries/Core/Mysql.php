<?php 
	
	class Mysql extends Conexion
	{
		protected $conexion;
		private $strquery;
		private $arrValues;
		private $conectEnterprise;

		function __construct($conectEnterprise)
		{	

			$this->conectEnterprise = $conectEnterprise;

			$this->conexion = new Conexion($this->conectEnterprise);
			
			$this->conexion = $this->conexion->conect();
			$sql_mode = $this->conexion->prepare("SET sql_mode=''");
			$sql_mode->execute();
		}

		public function beginTransaction()
		{
			return $this->conexion->beginTransaction();
		}
		
		public function commit()
		{
			return $this->conexion->commit();
		}

		public function rollBack()
		{
			return $this->conexion->rollBack();
		}

		//Insertar un registro
		public function insert(string $query, array $arrValues)
		{
			$this->strquery = $query;
			$this->arrVAlues = $arrValues;
        	$insert = $this->conexion->prepare($this->strquery);
        	$resInsert = $insert->execute($this->arrVAlues);
        	if($resInsert)
	        {
	        	$lastInsert = 1;
	        }else{
	        	$lastInsert = 0;
	        }
	        return $lastInsert; 
		}
		
		//Insertar un registro
		public function insertMovRecibo(string $query, array $arrValues)
		{
			$this->strquery = $query;
			$this->arrVAlues = $arrValues;
        	$insert = $this->conexion->prepare($this->strquery);
        	$resInsert = $insert->execute($this->arrVAlues);
        	if($resInsert)
	        {
	        	return $this->conexion->lastInsertId();
	        }else{
	        	$lastInsert = 0;
	        }
	        return $lastInsert; 
		}
		//Insertar varios registors
		/*public function insert_massive(string $query){

			$this->strquery = $query;
			$insert = $this->conexion->prepare($this->strquery);
			$insert->execute();

			if($insert)
	        {
	        	$lastInsert = $insert->rowCount();
	        }else{
	        	$lastInsert = 0;	
	        }
	        return $lastInsert; 
		}*/
		public function insert_massive(string $query){

			// Ya no necesitas un if/else. Si hay un error, PDO lanzará una excepción
			// que será capturada por el try/catch principal en tu modelo.
			$this->strquery = $query;
			$insert = $this->conexion->prepare($this->strquery);
			$resp = $insert->execute();
			return $insert->rowCount(); // Devuelve el número de filas afectadas.
		}

		// Reemplazar un registro
		public function replace(string $query, array $arrValues)
		{
		    $this->strquery = $query;
		    $this->arrVAlues = $arrValues;
		    $replace = $this->conexion->prepare($this->strquery);
		    $resReplace = $replace->execute($this->arrVAlues);
		    if ($resReplace) {
		        $lastReplace = 1;
		    } else {
		        $lastReplace = 0;
		    }
		    return $lastReplace;
		}

		// Reemplazar varios registros
		public function replace_massive(string $query)
		{
		    $this->strquery = $query;
		    $replace = $this->conexion->prepare($this->strquery);
		    $replace->execute();
		    if ($replace) {
		        $lastReplace = 1;
		    } else {
		        $lastReplace = 0;
		    }
		    return $lastReplace;
		}

		//Busca un registro
		public function select(string $query)
		{
			$this->strquery = $query;
        	$result = $this->conexion->prepare($this->strquery);
			$result->execute();
        	$data = $result->fetch(PDO::FETCH_ASSOC);
        	return $data;
		}
		//Devuelve todos los registros
		public function select_all(string $query)
		{
			$this->strquery = $query;
        	$result = $this->conexion->prepare($this->strquery);
			$result->execute();
        	$data = $result->fetchall(PDO::FETCH_ASSOC);
        	return $data;
		}
		//Actualiza registros
		public function update(string $query, array $arrValues)
		{
			$this->strquery = $query;
			$this->arrVAlues = $arrValues;
			$update = $this->conexion->prepare($this->strquery);
			$resExecute = $update->execute($this->arrVAlues);
	        return $resExecute;
		}
		//actualizar varios registors
		public function update_massive(string $query){
			$this->strquery = $query;
			$update = $this->conexion->prepare($this->strquery);
			$update->execute();
	        return $update; 
		}
		//Eliminar un registros
		public function delete(string $query)
		{
			$this->strquery = $query;
        	$result = $this->conexion->prepare($this->strquery);
			$del = $result->execute();
        	return $del;
		}
	}


 ?>

