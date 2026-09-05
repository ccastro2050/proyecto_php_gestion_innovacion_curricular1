<?php
/**
 * ControladorAliado — la capa HTTP de la v1.
 *
 * Su único trabajo: leer la petición, VALIDAR la forma del cuerpo (→ 422),
 * delegar al servicio, y responder JSON con el código correcto.
 * Aquí NO hay SQL ni reglas de negocio.
 *
 * La traducción, siempre la misma (contrato de 6_contracts.md §0):
 *   Cuerpo con errores de forma    → 422 (con la lista de errores)
 *   InvalidArgumentException       → 400 (regla de negocio, la lanza el servicio)
 *   NoEncontradoExcepcion          → 404 (no existe, la lanza el servicio)
 *   PDOException y cualquier otra  → 500 (mensaje del motor en `detalle`)
 *
 * ======================================================================
 * LO QUE ESTE ARCHIVO HACE A MANO Y EL GEMELO EN PYTHON NO
 * ======================================================================
 *
 * El mismo módulo está construido en FastAPI en `proyecto_paradigmas_innovacion_curricular1`.
 * Allá la validación NO se programa: se DECLARA en un modelo de Pydantic y
 * el framework responde 422 antes de que el controlador se entere. Aquí,
 * cada regla es un `if` escrito abajo.
 *
 * Las dos formas tienen un precio, y conviene verlo con los dos proyectos
 * abiertos al tiempo:
 *
 *   · Declararla es más corto y no se desactualiza respecto al modelo…
 *     pero el sobre del error lo decide el framework. FastAPI envuelve
 *     TODO en `{"detail": …}` y sus 422 salen en inglés, con el nombre de
 *     la columna. El contrato de ese proyecto documenta un sobre plano que
 *     su propia API no entrega.
 *   · Escribirla a mano es más largo y hay que acordarse de mantenerla…
 *     pero **el sobre sale exactamente como está documentado**, en español
 *     y con el nombre del campo. Nadie envuelve nada.
 *
 * No hay una que gane siempre. Lo que sí hay es una decisión, y está tomada
 * a la vista.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioAliado.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ControladorAliado
{
    public function __construct(
        // Ojo al TIPO: es la INTERFAZ — el controlador no sabe ni le importa
        // qué servicio concreto hay detrás.
        private readonly IServicioAliado $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/aliado[?limite=N]  →  listar
    // ------------------------------------------------------------------
    public function listar(): void
    {
        // $_GET trae el query string SIEMPRE como texto: "(int)" lo convierte
        // aquí, en la frontera. Si no vino, queda el valor por defecto.
        $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 1000;

        try {
            $filas = $this->servicio->listar($limite);

            if ($filas === []) {
                // 204 = éxito SIN contenido. Vacío no es error.
                http_response_code(204);
                return;
            }
            $datos = [];
            foreach ($filas as $fila) {
                $datos[] = $fila->toArray();
            }
            $this->responder(200, [
                'tabla'  => 'aliado',
                'limite' => $limite,
                'total'  => count($datos),
                'datos'  => $datos,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400,
                'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500,
                'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // GET /api/aliado/{nit}  →  obtener uno
    // ------------------------------------------------------------------
    public function obtener(int $clave): void
    {
        try {
            $this->responder(200, $this->servicio->obtener($clave)->toArray());
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400,
                'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404,
                'mensaje' => 'Aliado no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500,
                'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/aliado  →  crear (cuerpo completo, con la llave)
    // ------------------------------------------------------------------
    public function crear(array $cuerpo): void
    {
        // VALIDAR PRIMERO. POST exige TODO: la llave y los campos.
        $errores = array_merge(
            $this->validarClave($cuerpo),
            $this->validarCampos($cuerpo, true),   // true = todos obligatorios
        );
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422,
                'mensaje' => 'Datos inválidos.', 'errores' => $errores]);
            return;   // con errores de forma no se sigue: nada llegó a la BD
        }

        try {
            $datos = $this->filtrarColumnas($cuerpo);
            $datos['nit'] = $cuerpo['nit'];

            $this->servicio->crear($datos);
            $this->responder(200, ['estado' => 200,
                'mensaje' => 'Aliado creado exitosamente.']);
        } catch (Throwable $e) {
            // Ej.: llave duplicada — la BD rechaza por clave primaria.
            $this->responder(500, ['estado' => 500,
                'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/aliado/{nit}  →  reemplazo COMPLETO
    // ------------------------------------------------------------------
    public function reemplazar(int $clave, array $cuerpo): void
    {
        // PUT exige TODOS los campos (la llave va en la ruta): un PUT con
        // cuerpo parcial muere aquí con 422 — esa es la semántica de PUT.
        $errores = $this->validarCampos($cuerpo, true);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422,
                'mensaje' => 'Datos inválidos.', 'errores' => $errores]);
            return;
        }
        $this->escribir($clave, $cuerpo, 'Aliado reemplazado.');
    }

    // ------------------------------------------------------------------
    // PATCH /api/aliado/{nit}  →  actualización PARCIAL
    // ------------------------------------------------------------------
    public function actualizar(int $clave, array $cuerpo): void
    {
        // PATCH valida SOLO lo que llegó (false = nada es obligatorio).
        // El MISMO cuerpo que en PUT da 422, aquí pasa — la diferencia entre
        // reemplazar y actualizar queda escrita en código, no en un `if`.
        $errores = $this->validarCampos($cuerpo, false);
        if ($errores !== []) {
            $this->responder(422, ['estado' => 422,
                'mensaje' => 'Datos inválidos.', 'errores' => $errores]);
            return;
        }
        $this->escribir($clave, $cuerpo, 'Aliado actualizado.');
    }

    /** Lo que PUT y PATCH hacen igual una vez validado el cuerpo. */
    private function escribir(int $clave, array $cuerpo, string $mensaje): void
    {
        try {
            $filas = $this->servicio->actualizar($clave, $this->filtrarColumnas($cuerpo));
            $this->responder(200, ['estado' => 200, 'mensaje' => $mensaje,
                'filasAfectadas' => $filas]);
        } catch (InvalidArgumentException $e) {
            // El cuerpo vacío NO es 422: es una regla de negocio (400) que
            // decide el servicio — forma y negocio, cada cosa en su capa.
            $this->responder(400, ['estado' => 400,
                'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404,
                'mensaje' => 'Aliado no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500,
                'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // DELETE /api/aliado/{nit}  →  borrado LÓGICO
    // ------------------------------------------------------------------
    public function eliminar(int $clave): void
    {
        try {
            $filas = $this->servicio->eliminar($clave);
            $this->responder(200, ['estado' => 200,
                'mensaje' => 'Aliado eliminado.', 'filasAfectadas' => $filas]);
        } catch (InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400,
                'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage()]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, ['estado' => 404,
                'mensaje' => 'Aliado no encontrado.', 'detalle' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['estado' => 500,
                'mensaje' => 'Error interno.', 'detalle' => $e->getMessage()]);
        }
    }

    // ==================================================================
    // LA VALIDACIÓN DEL CUERPO (los ifs de la frontera HTTP → 422).
    // Devuelven LISTA de errores (vacía = todo bien) para reportarle al
    // cliente todos los problemas de una vez, no el primero.
    // ==================================================================

    /** La llave: obligatoria y bien formada. Solo la exige el POST. */
    private function validarClave(array $datos): array
    {
        $v = $datos['nit'] ?? null;
        if (!is_int($v) || $v < 1) {
            return ['El campo nit es obligatorio: un entero mayor o igual a 1.'];
        }
        return [];
    }

    /**
     * Con $obligatorios = true (POST y PUT) todos deben venir;
     * con false (PATCH) solo se valida lo que llegue.
     */
    private function validarCampos(array $datos, bool $obligatorios): array
    {
        $errores = [];

        if (array_key_exists('razon_social', $datos)) {
            $v = $datos['razon_social'];
            if (!is_string($v) || trim($v) === '' || mb_strlen($v) > 60) {
                $errores[] = 'El campo razon_social debe ser un texto de 1 a 60 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo razon_social es obligatorio.';
        }
        if (array_key_exists('nombre_contacto', $datos)) {
            $v = $datos['nombre_contacto'];
            if (!is_string($v) || trim($v) === '' || mb_strlen($v) > 60) {
                $errores[] = 'El campo nombre_contacto debe ser un texto de 1 a 60 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo nombre_contacto es obligatorio.';
        }
        if (array_key_exists('correo', $datos)) {
            $v = $datos['correo'];
            if (!is_string($v) || trim($v) === '' || mb_strlen($v) > 70) {
                $errores[] = 'El campo correo debe ser un texto de 1 a 70 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo correo es obligatorio.';
        }
        if (array_key_exists('telefono', $datos)) {
            $v = $datos['telefono'];
            if (!is_string($v) || trim($v) === '' || mb_strlen($v) > 45) {
                $errores[] = 'El campo telefono debe ser un texto de 1 a 45 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo telefono es obligatorio.';
        }
        if (array_key_exists('ciudad', $datos)) {
            $v = $datos['ciudad'];
            if (!is_string($v) || trim($v) === '' || mb_strlen($v) > 45) {
                $errores[] = 'El campo ciudad debe ser un texto de 1 a 45 caracteres.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo ciudad es obligatorio.';
        }

        return $errores;
    }

    /**
     * Deja pasar SOLO las columnas conocidas (lista blanca): cualquier campo
     * extraño que mande el cliente se ignora y jamás llega a un SQL. Y
     * `activo` no está en la lista: el borrado lógico no se maneja por el
     * cuerpo de un PUT.
     */
    private function filtrarColumnas(array $cuerpo): array
    {
        $datos = [];
        if (array_key_exists('razon_social', $cuerpo)) {
            $datos['razon_social'] = $cuerpo['razon_social'];
        }
        if (array_key_exists('nombre_contacto', $cuerpo)) {
            $datos['nombre_contacto'] = $cuerpo['nombre_contacto'];
        }
        if (array_key_exists('correo', $cuerpo)) {
            $datos['correo'] = $cuerpo['correo'];
        }
        if (array_key_exists('telefono', $cuerpo)) {
            $datos['telefono'] = $cuerpo['telefono'];
        }
        if (array_key_exists('ciudad', $cuerpo)) {
            $datos['ciudad'] = $cuerpo['ciudad'];
        }
        return $datos;
    }

    // ------------------------------------------------------------------
    // Respuesta: SIEMPRE se sale por aquí
    // ------------------------------------------------------------------

    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
