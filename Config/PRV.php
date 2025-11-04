<?php 
	if (!defined('BASE_URL')) define('BASE_URL', 'https://backups25.apps-adn.com');

	//Zona horaria
	date_default_timezone_set('America/Caracas');
	setlocale(LC_ALL, 'es_ES');

	// Datos Dinámicos (pueden ser actualizados)
	define('DB_HOST', 'nube6adn.ddns.me:3369');
	define('DB_NAME', 'adn002');
	define('DB_USER', 'sistemas');
	define('DB_PASSWORD', 'adn');
	define('DB_CHARSET', "utf8mb4");

?>