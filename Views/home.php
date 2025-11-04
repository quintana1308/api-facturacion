<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <title>Documentación Meta ADN</title>
    <meta name="description" content="">
    <meta name="author" content="ticlekiwi">

    <meta http-equiv="cleartype" content="on">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/hightlightjs-dark.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.8.0/highlight.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;1,300&family=Source+Code+Pro:wght@300&display=swap" rel="stylesheet"> 
    <link rel="stylesheet" href="<?= media(); ?>/css/style.css" media="all">
    <script>hljs.initHighlightingOnLoad();</script>
</head>

<body>
<div class="left-menu">
    <div class="content-logo">
        <div class="logo">
            <img alt="platform by Emily van den Heever from the Noun Project" title="platform by Emily van den Heever from the Noun Project" src="<?= media(); ?>/images/logo.png" height="32" />
            <span>Pryvit Documentación</span>
        </div>
        <button class="burger-menu-icon" id="button-menu-mobile">
            <svg width="34" height="34" viewBox="0 0 100 100"><path class="line line1" d="M 20,29.000046 H 80.000231 C 80.000231,29.000046 94.498839,28.817352 94.532987,66.711331 94.543142,77.980673 90.966081,81.670246 85.259173,81.668997 79.552261,81.667751 75.000211,74.999942 75.000211,74.999942 L 25.000021,25.000058"></path><path class="line line2" d="M 20,50 H 80"></path><path class="line line3" d="M 20,70.999954 H 80.000231 C 80.000231,70.999954 94.498839,71.182648 94.532987,33.288669 94.543142,22.019327 90.966081,18.329754 85.259173,18.331003 79.552261,18.332249 75.000211,25.000058 75.000211,25.000058 L 25.000021,74.999942"></path></svg>
        </button>
    </div>
    <div class="mobile-menu-closer"></div>
    <div class="content-menu">
        <div class="content-infos">
            <div class="info"><b>Version:</b> 1.1</div>
            <div class="info"><b>Última actualización:</b> 9 May, 2024</div>
        </div>
        <ul>
            <li class="scroll-to-link active" data-target="content-get-started">
                <a>Meta</a>
            </li>
            <li class="scroll-to-link" data-target="content-get-characters">
                <a>Respaldos</a>
            </li>
            <li class="scroll-to-link" data-target="content-errors">
                <a>PryvitInside</a>
            </li>
        </ul>
    </div>
</div>
<div class="content-page">
    <div class="content-code"></div>
    <div class="content">
        <div class="overflow-hidden content-section" id="content-get-started">
            <h1>Meta</h1>
            <pre>
    API Endpoint

        https://meta.adnpanel.com/Api/meta

    Aquí hay un ejemplo con curl
    
    curl 
    --location 'https://meta.adnpanel.com/Api/meta' \
	--form 'TOKEN="40422233344455"' \
	--form 'RIF="J-262879999"' \
	--form 'BD="adn"' \
	--form 'TYPE="CHAT"' \
	--form 'BODY="Aquí va el mensaje"' \
	--form 'SEND_TO="04126755217"'

                </pre>
            <p>
                Meta es el servicio que se encarga de envios de mensajes desde ADN desde supermarket y la tabla adn_pryvit<br>
                <code class="higlighted break-word">https://meta.adnpanel.com/Api/meta</code>
            </p>
            <br>

            <h4>Parametros POST requeridos para envío de mensajes de meta</h4>
            <table class="central-overflow-x">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Valor</th>
                    <th>Descripción</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>TOKEN</td>
                    <td>40422233344455</td>
                    <td>TOKEN de la empresa en ADN</td>
                </tr>
                <tr>
                    <td>RIF</td>
                    <td>J-12345678-9</td>
                    <td>RIF de la empresa igual que en ADN</td>
                </tr>
                <tr>
                    <td>BD</td>
                    <td>adn</td>
                    <td>
                        Nombre de la base de datos de la empresa
                    </td>
                </tr>
                <tr>
                    <td>TYPE</td>
                    <td>CHAT / CHAT2</td>
                    <td>
                        (CHAT) mensaje de saludo desde supermarket y docgeninv version free y lite</br>
                        (CHAT2) mensaje de saludo desde supermarket, docgeninv y adn_pryvit solo funcióna con la version full
                    </td>
                </tr>
                <tr>
                    <td>BODY</td>
                    <td>este es un mensaje</td>
                    <td>
                        Mensaje a enviar<br>
                    </td>
                </tr>
                <tr>
                    <td>SEND_TO</td>
                    <td>04126755217</td>
                    <td>Número de teléfono a enviar formatos requeridos(04126755217, +584126755217)</td>
                </tr>
                </tbody>
            </table>
        </div>


        <div class="overflow-hidden content-section" id="content-get-characters">
            <h2>Respaldos</h2>
            <pre><code class="bash">
    API Endpoint

        https://meta.adnpanel.com/Api/respaldos

    Aquí hay un ejemplo con curl
    
    curl 
    --location 'https://meta.adnpanel.com/Api/respaldos' \
	--form 'TOKEN="40422233344455"' \
	--form 'RIF="J-262879999"' \
	--form 'BD="adn"' \
	--form 'TYPE="CHAT"' \
	--form 'BODY="Mensaje con url del respaldo"' \
	--form 'SEND_TO="04126755217"'
                </code></pre>
            <p>
                RESPALDOS es la función de envio de los respaldo autoatico de la data de los clientes<br>
                <code class="higlighted break-word">https://meta.adnpanel.com/Api/respaldos</code>
            </p>
            <br>
            
            <h4>Parametros POST requeridos para envío de mensajes de respaldos</h4>
            <table class="central-overflow-x">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Valor</th>
                    <th>Descripción</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>TOKEN</td>
                    <td>40422233344455</td>
                    <td>TOKEN de la empresa en ADN</td>
                </tr>
                <tr>
                    <td>RIF</td>
                    <td>J-12345678-9</td>
                    <td>RIF de la empresa igual que en ADN</td>
                </tr>
                <tr>
                    <td>BD</td>
                    <td>adn</td>
                    <td>
                        Nombre de la base de datos de la empresa
                    </td>
                </tr>
                <tr>
                    <td>TYPE</td>
                    <td>CHAT</td>
                    <td>
                        CHAT es el tipo necesario para envio de mensajes para los respaldos</br>
                    </td>
                </tr>
                <tr>
                    <td>BODY</td>
                    <td>RESPALDO GENERADO AUTOMÁTICAMENTE</br>
EMPRESA: adn</br>
BASE DE DATOS: adn</br>
IMPORTANTE: El peso del archivo supera los 25MB y no puede enviarse al correo, el RESPALDO SÓLO ESTARÁ DISPONIBLE POR 15 DÍAS, en el siguiente link:</br>		
https://r1.enviarmasivo.com/sftp.php?file=J175027706_adn_25_respaldo.zip </td>
                    <td>
                        Mensaje a enviar<br>
                    </td>
                </tr>
                <tr>
                    <td>SEND_TO</td>
                    <td>04126755217</td>
                    <td>Número de teléfono a enviar formatos requeridos(04126755217, +584126755217)</td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="overflow-hidden content-section" id="content-errors">
            <h2>Pryvit Inside</h2>
             <pre><code class="bash">
    API Endpoint

        https://meta.adnpanel.com/Api/pryvitInside

    Aquí hay un ejemplo con curl
    
    curl 
    --location 'http://127.0.0.1/meta/Api/pryvitInside' \
	--form 'TOKEN="40422233344455"' \
	--form 'RIF="J-262879999"' \
	--form 'BD="adn"' \
	--form 'TYPE="DOCUMENT"' \
	--form 'BODY="base64 del pdf"' \
	--form 'SEND_TO="04126755217"' \
	--form 'FILE_NAME="test.pdf"' \
	--form 'CAPTION="test pryvit inside"'
                </code></pre>
            <p>
                Pryvit Inside es la función de envio de reportes a traves del dll de reportes desde el sistema ADN<br>
                <code class="higlighted break-word">https://meta.adnpanel.com/Api/pryvitInside</code>
            </p>
            <br>

            <h4>Parametros POST requeridos para envío de mensajes de pryvirInside</h4>
            <table class="central-overflow-x">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Valor</th>
                    <th>Descripción</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>TOKEN</td>
                    <td>40422233344455</td>
                    <td>TOKEN de la empresa en ADN</td>
                </tr>
                <tr>
                    <td>RIF</td>
                    <td>J-12345678-9</td>
                    <td>RIF de la empresa igual que en ADn</td>
                </tr>
                <tr>
                    <td>BD</td>
                    <td>adn</td>
                    <td>
                        Nombre de la base de datos de la empresa
                    </td>
                </tr>
                <tr>
                    <td>TYPE</td>
                    <td>DOCUMENT</td>
                    <td>
                        DOCUMENT es el tipo necesario para envio de archivos para pryvit Inside</br>
                    </td>
                </tr>
                <tr>
                    <td>BODY</td>
                    <td>{{BASE64 del pdf}}}
                    <td>
                         pdf en base64<br>
                    </td>
                </tr>
                <tr>
                    <td>SEND_TO</td>
                    <td>04126755217</td>
                    <td>Número de teléfono a enviar formatos requeridos(04126755217, +584126755217)</td>
                </tr>
                <tr>
                    <td>FILE_NAME</td>
                    <td>reporte-ventas.pdf</td>
                    <td>Nombre con le cual se va a enviar el reporte pdf</td>
                </tr>

                <tr>
                    <td>CAPTION</td>
                    <td>reporte-ventas generado el 01-01-2024 por el usuario @usuario</td>
                    <td>Caption del mensaje de texto</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="content-code"></div>
</div>

<script src="<?= media(); ?>/js/script.js"></script>
</body>
</html>