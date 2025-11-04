<?php


class Conexion2{
	private $conect;

	public function __construct(){

		require_once(__DIR__ . "/../../Config/Config.php");

		$connectionString = "mysql:host=".DB_HOST_LOCAL.";dbname=".DB_NAME_LOCAL.";charset=".DB_CHARSET_LOCAL;
		try{
			$options = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_PERSISTENT => true,  // <-- Aquí la conexión persistente
			];

			$this->conect = new PDO($connectionString, DB_USER_LOCAL, DB_PASSWORD_LOCAL, $options);
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

?>