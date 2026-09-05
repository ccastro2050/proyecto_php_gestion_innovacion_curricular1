<?php
/**
 * index.php — el FRONT CONTROLLER de la API de Innovación curricular v1.
 *
 * TODAS las peticiones entran por aquí (el servidor se arranca con
 * `php -S 0.0.0.0:8115 index.php`). Este archivo hace UNA cosa: mirar el
 * método y la ruta, y entregar la petición al método del controlador que
 * corresponde. Nada de SQL, nada de negocio.
 *
 * Rutas de la v1 (contratos exactos en docs/spec_kit/versiones/v1_aliado/6_contracts.md):
 *   GET    /                            → diagnóstico
 *   GET    /api/aliado[?limite=N]   → listar
 *   POST   /api/aliado              → crear
 *   GET    /api/aliado/{nit}         → obtener uno
 *   PUT    /api/aliado/{nit}         → reemplazo completo
 *   PATCH  /api/aliado/{nit}         → actualización parcial
 *   DELETE /api/aliado/{nit}         → borrado LÓGICO
 */

// "Modo estricto de tipos": si una función espera int y llega el string "5",
// PHP lanza error en vez de convertirlo en silencio. DEBE ser la primera
// instrucción del archivo. Todos los archivos del proyecto lo llevan.
declare(strict_types=1);

require_once __DIR__ . '/servicios/ensamblador.php';
require_once __DIR__ . '/controladores/ControladorAliado.php';

// Toda respuesta de esta API es JSON — se avisa en el encabezado HTTP:
header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------------
// 1. CAPTURAR la petición: método, ruta y cuerpo
// ----------------------------------------------------------------------
$metodo = $_SERVER['REQUEST_METHOD'];
$ruta   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// El cuerpo (el JSON de POST, PUT y PATCH) se lee del canal php://input, que
// solo se puede leer UNA vez. Si no vino nada, o el JSON está malo, queda [].
$cuerpo = json_decode(file_get_contents('php://input'), true) ?? [];

// Se arma el controlador. Su dependencia la crea el ensamblador — el único
// archivo del sistema que conoce clases concretas:
$controlador = new ControladorAliado(crearServicioAliado());

// ----------------------------------------------------------------------
// 2. ENRUTAR
// ----------------------------------------------------------------------

// GET / — diagnóstico (sirve para saber si la API está viva)
if ($ruta === '/' && $metodo === 'GET') {
    echo json_encode([
        'mensaje'   => 'API de Innovación curricular funcionando',
        'version'   => 'v1',
        'tabla'     => 'aliado',
        'contratos' => 'docs/spec_kit/versiones/v1_aliado/6_contracts.md',
    ], JSON_UNESCAPED_UNICODE);
    return;
}

// /api/aliado — la COLECCIÓN: listar y crear
if ($ruta === '/api/aliado') {
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($cuerpo);
    } else {
        responderNoPermitido();   // PUT, DELETE… aquí no existen → 405
    }
    return;
}

// /api/aliado/{nit} — UNA fila concreta.
if (str_starts_with($ruta, '/api/aliado/')) {
    $clave = urldecode(substr($ruta, strlen('/api/aliado/')));

    // La llave de esta tabla es un ENTERO y lo que llega de la URL es texto:
    // se convierte aquí, en la frontera. `(int) "abc"` da 0, y el servicio
    // rechaza el 0 con un 400 — que es lo correcto: no es que no exista, es
    // que eso no es una llave.
    $clave = (int) $clave;

    if ($metodo === 'GET') {
        $controlador->obtener($clave);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($clave, $cuerpo);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($clave, $cuerpo);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($clave);
    } else {
        responderNoPermitido();
    }
    return;
}

// Ninguna ruta coincidió: 404 de RUTA — distinto del 404 de «no existe esa
// fila», que decide el servicio.
http_response_code(404);
echo json_encode([
    'estado' => 404, 'mensaje' => 'Ruta no encontrada.', 'detalle' => "$metodo $ruta",
], JSON_UNESCAPED_UNICODE);

// ----------------------------------------------------------------------
function responderNoPermitido(): void
{
    // 405 = "la ruta existe, pero no con ese método"
    http_response_code(405);
    echo json_encode([
        'estado' => 405, 'mensaje' => 'Método no permitido para esta ruta.',
    ], JSON_UNESCAPED_UNICODE);
}
