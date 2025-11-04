<?php
class Conexion{
	private $conect;
	private $conectEnterprise;

	public function __construct($conectEnterprise){
		$this->conectEnterprise = $conectEnterprise;

		require_once("Config/".$this->conectEnterprise.".php");

        // --- CAMBIO IMPORTANTE AQUÍ ---
        // Asegúrate de que el charset esté definido EN LA CADENA DE CONEXIÓN.
		$connectionString = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;

		try{
			$options = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				// PDO::ATTR_PERSISTENT => true, // Las conexiones persistentes pueden causar problemas, prueba a desactivarlas temporalmente.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES ".DB_CHARSET // Comando adicional para asegurar la codificación
			];

			$this->conect = new PDO($connectionString, DB_USER, DB_PASSWORD, $options);
		    
		}catch(PDOException $e){
			$this->conect = 'Error de conexión';
			// Es mejor lanzar la excepción para que el controlador principal la capture
			throw new Exception("Error de Conexión a BD: " . $e->getMessage());
		}
	}

	public function conect(){
		return $this->conect;
	}
}

/*class Conexion{
	private $conect;
	private $conectEnterprise;

	public function __construct($conectEnterprise){

		$this->conectEnterprise = $conectEnterprise;

		require_once("Config/".$this->conectEnterprise.".php");

		$connectionString = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;

		try{
			$options = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_PERSISTENT => true,  // <-- Aquí la conexión persistente
			];

			$this->conect = new PDO($connectionString, DB_USER, DB_PASSWORD, $options);
			//$this->conect = new PDO($connectionString, DB_USER, DB_PASSWORD);
			//$this->conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		    //echo "conexión exitosa";
		}catch(PDOException $e){
			$this->conect = 'Error de conexión';
			echo "ERROR: " . $e->getMessage();
		}
	}

	public function conect(){
		return $this->conect;
	}
}
*/
?>