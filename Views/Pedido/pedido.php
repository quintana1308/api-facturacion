

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $data['page_title'] ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        .content {
            padding: 30px;
        }
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.1em;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }
        .info-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.2em;
            font-weight: 600;
        }
        .info-card p {
            margin: 8px 0;
            color: #666;
        }
        .info-card strong {
            color: #333;
            font-weight: 600;
        }
        .json-container {
            margin-top: 30px;
        }
        .json-section {
            margin-bottom: 30px;
        }
        .json-section h3 {
            background: #667eea;
            color: white;
            padding: 15px 20px;
            margin: 0 0 15px 0;
            border-radius: 8px 8px 0 0;
            font-size: 1.1em;
            font-weight: 600;
        }
        .json-content {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .search-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }
        .search-form h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .form-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .form-group input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        .form-group button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .form-group button:hover {
            background: #5a6fd8;
        }
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .form-group {
                flex-direction: column;
            }
            .form-group input,
            .form-group button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= $data['page_title'] ?></h1>
            <?php if (!empty($data['numero_pedido'])): ?>
                <p>Pedido: <?= htmlspecialchars($data['numero_pedido']) ?></p>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <!-- Formulario de búsqueda -->
            <div class="search-form">
                <h3>🔍 Buscar Pedido</h3>
                <form method="GET" action="">
                    <div class="form-group">
                        <input type="text" 
                               name="pedido" 
                               placeholder="Ingrese el número de pedido..." 
                               value="<?= htmlspecialchars($data['numero_pedido'] ?? '') ?>"
                               required>
                        <button type="submit">Buscar</button>
                    </div>
                </form>
            </div>

            <?php if (!empty($data['error'])): ?>
                <div class="error">
                    ⚠️ <?= $data['error'] ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($data['pedido'])): ?>
                <!-- Información básica del pedido -->
                <div class="info-grid">
                    <div class="info-card">
                        <h3>📋 Información General</h3>
                        <p><strong>ID:</strong> <?= htmlspecialchars($data['pedido']['JSON_ID']) ?></p>
                        <p><strong>Número de Pedido:</strong> <?= htmlspecialchars($data['pedido']['JSON_DCL_NUMERO']) ?></p>
                        <p><strong>Empresa:</strong> 
                            <span class="badge badge-info"><?= htmlspecialchars($data['pedido']['JSON_ENTERPRISE']) ?></span>
                        </p>
                        <p><strong>Tipo:</strong> 
                            <span class="badge badge-warning"><?= htmlspecialchars($data['pedido']['JSON_TYPE']) ?></span>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <h3>⏰ Información Temporal</h3>
                        <p><strong>Fecha y Hora:</strong> <?= htmlspecialchars($data['pedido']['JSON_FECHAHORA']) ?></p>
                        <p><strong>Estado:</strong> 
                            <?php if (!empty($data['pedido']['JSON_RESPONSE'])): ?>
                                <span class="badge badge-success">✅ Procesado</span>
                            <?php else: ?>
                                <span class="badge badge-warning">⏳ Pendiente</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- JSON de la petición -->
                <?php if (!empty($data['pedido']['JSON_VALUE'])): ?>
                    <div class="json-container">
                        <div class="json-section">
                            <h3>📤 JSON de la Petición</h3>
                            <div class="json-content"><?= htmlspecialchars($data['json_formatted'] ?? $data['pedido']['JSON_VALUE']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- JSON de la respuesta -->
                <?php if (!empty($data['pedido']['JSON_RESPONSE'])): ?>
                    <div class="json-container">
                        <div class="json-section">
                            <h3>📥 JSON de la Respuesta</h3>
                            <div class="json-content"><?= htmlspecialchars($data['response_formatted'] ?? $data['pedido']['JSON_RESPONSE']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
