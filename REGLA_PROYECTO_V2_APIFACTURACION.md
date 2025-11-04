# REGLA ESPECÍFICA DEL PROYECTO V2-APIFACTURACION

## PROPÓSITO Y OBJETIVO

### Propósito Principal
API REST especializada en procesamiento de facturas electrónicas con gestión completa de documentos contables, recibos y movimientos IGTF para múltiples empresas.

### Objetivos Específicos
- Procesar facturas en lotes con validación exhaustiva
- Gestionar persistencia de datos maestros de forma optimizada
- Generar múltiples tipos de documentos contables (PED, FAV, FAV$, IGTFV)
- Manejar operaciones multi-moneda con conversión automática
- Proporcionar trazabilidad completa mediante logging

## ARQUITECTURA Y TECNOLOGÍAS

### Stack Tecnológico
- **Lenguaje**: PHP 7+ con PDO
- **Patrón de Diseño**: MVC personalizado con autoload
- **Base de Datos**: MySQL con soporte multi-empresa
- **Servidor Web**: Apache con XAMPP
- **Autenticación**: Bearer Token específico por empresa
- **CORS**: Habilitado para todas las origins

### Características Arquitectónicas
- Autoload personalizado para clases del core
- Routing dinámico (Controller/Method/Params)
- Conexiones de BD independientes por empresa
- Manejo transaccional con rollback automático

## ESTRUCTURA DEL PROYECTO

```
V2-ApiFacturacion/
├── Assets/                     # Recursos estáticos
│   ├── css/                   # Estilos CSS
│   ├── images/                # Imágenes
│   └── js/                    # JavaScript
├── Config/                     # Configuraciones por empresa
│   ├── ADNTEST.php           # Configuración empresa ADNTEST
│   ├── CRM.php               # Configuración empresa CRM
│   ├── MPC.php               # Configuración empresa MPC
│   └── Config.php            # Configuración base
├── Controllers/               # Controladores MVC
│   ├── Documento.php         # Controlador principal de facturas
│   ├── DocumentoDataCollector.php # Recolector de datos maestros
│   ├── Error.php             # Manejo de errores
│   └── Home.php              # Controlador home
├── Helpers/                   # Funciones utilitarias
│   └── Helpers.php           # Funciones auxiliares
├── Libraries/Core/            # Núcleo del framework
│   ├── Autoload.php          # Carga automática de clases
│   ├── Conexion.php          # Conexión principal BD
│   ├── Conexion2.php         # Conexión secundaria BD
│   ├── Controllers.php       # Clase base controladores
│   ├── DocumentoValidators.php # Validadores especializados
│   ├── Load.php              # Cargador de controladores
│   ├── Mysql.php             # Clase BD principal
│   └── Mysql2.php            # Clase BD secundaria
├── Models/                    # Modelos de datos
│   ├── DocumentoModel.php    # Modelo principal de documentos
│   ├── LogJsonModel.php      # Modelo de logging
│   └── [otros modelos]
├── Views/                     # Vistas
│   ├── api.php               # Vista API
│   └── home.php              # Vista home
├── index.php                  # Punto de entrada principal
└── .htaccess                  # Configuración URL rewriting
```

## FLUJO DE PROCESAMIENTO PRINCIPAL

### 1. Inicialización de Petición
- Validación de JSON de entrada
- Verificación de token Bearer
- Validación de existencia de empresa
- Creación de log inicial de petición

### 2. Validación Estructural Completa
- Validación de cabecera de factura (DocumentoValidators)
- Validación de cliente y vendedor
- Validación de movimientos y productos
- Validación de recibo (si existe)
- Validación de vehículo (si existe)

### 3. Recolección de Datos Maestros
- DocumentoDataCollector agrupa datos únicos
- Recolección de marcas, departamentos, grupos, categorías
- Recolección de productos, almacenes, unidades
- Recolección de clientes, vendedores, vehículos
- Recolección de bancos y cuentas

### 4. Persistencia Masiva de Maestros
- Inserción masiva optimizada con ON DUPLICATE KEY UPDATE
- Procesamiento de entidades simples (marcas, departamentos, etc.)
- Procesamiento de entidades complejas (productos, clientes)
- Manejo de relaciones (unidades de producto, cuentas bancarias)

### 5. Procesamiento Individual de Factura
- Validación de duplicados de documentos
- Validación de duplicados de movimientos
- Validación de duplicados de recibos
- Preparación de datos transaccionales
- Inserción transaccional con rollback automático

### 6. Generación de Documentos
- **PED** (Pedido): Documento base tipo D
- **FAV** (Factura): Documento principal tipo D
- **FAV$** (Factura USD): Solo si moneda_base = BS, tipo D
- **NEN** (Nota de Entrega): Documento principal tipo D (misma lógica que FAV)
- **NEN$** (Nota de Entrega USD): Solo si moneda_base = BS, tipo D
- **IGTFV** (IGTF): Documentos D (devengo) y P (pago) para operaciones en divisas
- **Documentos de Pago**: FAV/NEN o FAV$/NEN$ tipo P cuando hay recibo

### 7. Logging y Respuesta
- Actualización de log con respuesta JSON
- Generación de respuesta final estructurada
- Manejo de errores con códigos HTTP apropiados

## ENTIDADES Y MODELOS DE DATOS

### Factura Principal
**Campos Obligatorios:**
- `numero`: Número único de factura
- `tipo_documento`: Tipo de documento (FAV, PED, etc.)
- `ip`: Dirección IP de origen
- `serie_fiscal`: Serie fiscal del documento
- `fecha`: Fecha de emisión
- `hora`: Hora de emisión
- `moneda`: Moneda del documento (BS/USD)
- `monto_bruto`: Monto bruto total
- `neto`: Monto neto
- `base_gravada`: Base gravada para IVA
- `exento`: Monto exento
- `iva_gravado`: IVA calculado
- `valor_cambiario_dolar`: Tasa de cambio USD
- `valor_cambiario_peso`: Tasa de cambio otras monedas

### Datos Maestros

#### Cliente
- `codigo`: Código único del cliente
- `nombre`: Nombre o razón social
- `rif`: RIF del cliente
- `paciente` (opcional): Datos del paciente asociado

#### Vendedor
- `codigo`: Código único del vendedor
- `nombre`: Nombre del vendedor
- `apellido`: Apellido del vendedor

#### Producto
- `codigo`: Código único del producto
- `descripcion`: Descripción del producto
- `estado`: Estado del producto
- `tipo_costo`: Tipo de costo
- `marca`: Marca del producto
- `departamento`: Departamento al que pertenece
- `grupo`: Grupo del producto
- `categoria`: Categoría del producto
- `version`: Versión del producto

#### Almacén
- `codigo`: Código único del almacén
- `nombre`: Nombre del almacén
- `activo`: Estado activo/inactivo
- `lpt`: Lista de precios por defecto
- `tipo`: Tipo de almacén

#### Vehículo (Opcional)
- Datos completos de registro vehicular
- `cuadro_poliza`: Número de póliza
- `placa`: Placa del vehículo
- `marca`: Marca del vehículo
- `modelo`: Modelo del vehículo
- Datos técnicos completos

### Movimientos y Transacciones
- **Movimientos de Factura**: Detalle de productos facturados
- **Movimientos de Recibo**: Formas de pago utilizadas
- **Movimientos IGTF**: Cálculos automáticos para operaciones en divisas

## VALIDACIONES CRÍTICAS

### Validaciones de Duplicación
1. **Documentos**: Por (numero, tipo_documento, tipo_transaccion)
2. **Movimientos**: Por (documento, producto, cantidad, precio, lote, fecha_lote)
3. **Recibos**: Por número de recibo
4. **Movimientos de Recibo**: Por (numero_movimiento, numero_recibo)
5. **Documentos IGTF**: Por (numero, tipo_transaccion)

### Validaciones de Estructura
- Validación completa de JSON de entrada
- Verificación de campos obligatorios en todos los niveles
- Validación de tipos de datos y formatos
- Verificación de relaciones entre entidades

### Validaciones de Negocio
- Autenticación por token Bearer específico por empresa
- Verificación de existencia de empresa
- Validación de monedas y tasas de cambio
- Cálculos automáticos de IGTF para operaciones en divisas

## CONFIGURACIÓN MULTI-EMPRESA

### Estructura de Configuración
- Archivos separados por empresa en `Config/`
- Cada empresa tiene su propio archivo de configuración
- Conexiones de BD independientes por empresa
- Tokens de autenticación únicos por empresa

### Empresas Configuradas
- **ADNTEST**: Empresa de pruebas
- **CRM**: Sistema CRM
- **MPC**: Empresa MPC
- Otras empresas según archivos en Config/

### Parámetros por Empresa
```php
define('DB_HOST', 'host_de_la_empresa');
define('DB_NAME', 'nombre_bd_empresa');
define('DB_USER', 'usuario_bd_empresa');
define('DB_PASSWORD', 'password_bd_empresa');
define('DB_CHARSET', 'utf8');
```

## MANEJO DE ERRORES Y LOGGING

### Sistema de Logging
- **Tabla**: `api_logjson`
- **Log de Petición**: JSON completo de entrada
- **Log de Respuesta**: JSON completo de salida
- **Identificación**: Por número de factura y empresa
- **Trazabilidad**: Completa del proceso

### Manejo de Excepciones
- Captura en múltiples niveles (validación, persistencia, negocio)
- Códigos HTTP apropiados:
  - `400`: Bad Request (JSON inválido, campos faltantes)
  - `401`: Unauthorized (token faltante)
  - `403`: Forbidden (token inválido)
  - `500`: Internal Server Error (errores de BD, lógica)

### Rollback Automático
- Transacciones para integridad de datos
- Rollback automático en caso de errores
- Preservación del estado anterior en caso de fallo

## CARACTERÍSTICAS ESPECIALES

### Soporte Multi-Moneda
- Conversión automática BS ↔ USD
- Generación de documentos en múltiples monedas
- Cálculo automático de equivalencias

### Gestión IGTF
- Detección automática de operaciones en divisas
- Generación de documentos IGTFV (devengo y pago)
- Cálculo automático de base e impuesto IGTF

### Optimizaciones de Rendimiento
- Inserción masiva con `ON DUPLICATE KEY UPDATE`
- Recolección de datos únicos para evitar duplicados
- Validaciones en lote para reducir consultas

### Funcionalidades Adicionales
- Formateo automático de números telefónicos venezolanos
- Validación de horarios para ciertas operaciones
- Soporte para múltiples formas de pago en recibos

## DEPENDENCIAS Y CONEXIONES

### Dependencias PHP
- PHP 7.0+
- Extensión PDO
- Extensión MySQL
- Soporte para JSON

### Configuración de BD
- MySQL 5.7+
- Charset UTF-8
- Conexiones persistentes opcionales
- Modo SQL flexible (`SET sql_mode=''`)

### Configuración Apache
- Módulo `mod_rewrite` habilitado
- Headers CORS configurados
- Soporte para `Authorization` header

## PUNTOS DE ENTRADA Y ROUTING

### Punto de Entrada Principal
- **Archivo**: `index.php`
- **Ruta por defecto**: `Documento/documento`
- **Parámetros**: Extraídos de URL mediante `$_GET['url']`

### Estructura de URLs
```
/Controller/Method/Param1/Param2/...
```

### Métodos HTTP Soportados
- `GET`: Consultas
- `POST`: Creación de documentos
- `PUT`: Actualizaciones
- `DELETE`: Eliminaciones

### Headers Requeridos
```
Authorization: Bearer {token_empresa}
Content-Type: application/json
```

## CONSIDERACIONES DE SEGURIDAD

### Autenticación
- Token Bearer obligatorio por empresa
- Validación de token contra BD
- Verificación de existencia de empresa

### Sanitización
- Uso de `PDO::quote()` para prevenir SQL injection
- Validación de tipos de datos de entrada
- Escape de caracteres especiales

### Logging de Seguridad
- Registro de todos los intentos de acceso
- Log de tokens inválidos
- Trazabilidad completa de operaciones

---

**Nota**: Esta regla debe ser consultada antes de realizar cualquier modificación al proyecto para asegurar el cumplimiento de la arquitectura, procesos y validaciones establecidas.
