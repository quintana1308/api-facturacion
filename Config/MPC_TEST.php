<?php 
	if (!defined('BASE_URL')) define('BASE_URL', 'https://backups25.apps-adn.com/V2');

	//Zona horaria
	date_default_timezone_set('America/Caracas');
	setlocale(LC_ALL, 'es_ES');

	// Datos Dinámicos (pueden ser actualizados)
	define('DB_HOST', 'nube2adn.ddns.me:3420');
	define('DB_NAME', 'test');
	define('DB_USER', 'sistemas');
	define('DB_PASSWORD', 'adn');
	define('DB_CHARSET', "utf8");

?>
