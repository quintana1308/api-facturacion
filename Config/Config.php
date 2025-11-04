<?php 
	//if (!defined('BASE_URL')) define('BASE_URL', 'https://backups25.apps-adn.com');
	
	if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/V3-ApiFacturacion');
	//Zona horaria
	date_default_timezone_set('America/Caracas');
	setlocale(LC_ALL, 'es_ES');

	// Datos Dinámicos (pueden ser actualizados)
	define('DB_HOST_LOCAL', '198.251.71.61:3306');
	define('DB_NAME_LOCAL', 'backups25');
	define('DB_USER_LOCAL', 'backups25');
	define('DB_PASSWORD_LOCAL', '_vVBrT31*4xadmoa');
	define('DB_CHARSET_LOCAL', "utf8");

	// Configuración de Resend para notificaciones de errores
	define('RESEND_API_KEY', 're_9po1SoZ9_4KsBz6bm94a41H9knZQUL8P7');
	define('MAIL_FROM_EMAIL', 'envio@correos.apps-adn.com'); // Debe ser un dominio verificado en Resend
	define('MAIL_FROM_NAME', 'API Facturación - Notificaciones');

?>


