<?php
/**
 * Aliado — el MODELO de la v1: la clase que representa una fila de la tabla
 * `aliado` como un objeto.
 *
 * Estilo clásico de P.O.O. (encapsulamiento):
 *   - las propiedades son PRIVADAS: nadie por fuera las toca directamente;
 *   - se LEEN con getters;
 *   - se CAMBIAN con setters;
 *   - `nit` NO tiene setter: es la llave primaria — se fija al
 *     crear el objeto y no cambia nunca.
 *
 * Lo que este modelo NO tiene, y es a propósito: la columna `activo`. La
 * usa el repositorio para el borrado lógico, pero no es un dato del
 * aliado — es cómo la base recuerda que ya no está. Si estuviera
 * aquí, alguien terminaría mandándola en un PUT.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class Aliado
{
    private int $nit;  // El NIT del aliado. Es la llave.
    private string $razon_social;
    private string $nombre_contacto;
    private string $correo;  // Se guarda como texto: el esquema dado no exige un formato de correo.
    private string $telefono;
    private string $ciudad;

    public function __construct(
        int $nit,
        string $razon_social,
        string $nombre_contacto,
        string $correo,
        string $telefono,
        string $ciudad,
    ) {
        $this->nit = $nit;
        $this->razon_social = $razon_social;
        $this->nombre_contacto = $nombre_contacto;
        $this->correo = $correo;
        $this->telefono = $telefono;
        $this->ciudad = $ciudad;
    }

    // ------------------------------------------------------------------
    // GETTERS — para LEER cada propiedad desde afuera
    // ------------------------------------------------------------------

    public function getNit(): int
    {
        return $this->nit;
    }

    public function getRazonSocial(): string
    {
        return $this->razon_social;
    }

    public function getNombreContacto(): string
    {
        return $this->nombre_contacto;
    }

    public function getCorreo(): string
    {
        return $this->correo;
    }

    public function getTelefono(): string
    {
        return $this->telefono;
    }

    public function getCiudad(): string
    {
        return $this->ciudad;
    }

    // ------------------------------------------------------------------
    // SETTERS — solo para lo que puede cambiar.
    // La llave no tiene: identificar y modificar son cosas distintas.
    // ------------------------------------------------------------------

    public function setRazonSocial(string $razon_social): void
    {
        $this->razon_social = $razon_social;
    }

    public function setNombreContacto(string $nombre_contacto): void
    {
        $this->nombre_contacto = $nombre_contacto;
    }

    public function setCorreo(string $correo): void
    {
        $this->correo = $correo;
    }

    public function setTelefono(string $telefono): void
    {
        $this->telefono = $telefono;
    }

    public function setCiudad(string $ciudad): void
    {
        $this->ciudad = $ciudad;
    }

    // ------------------------------------------------------------------
    // Conversión para la respuesta JSON
    // ------------------------------------------------------------------

    /**
     * Devuelve el aliado como array (columna => valor), listo
     * para que json_encode lo convierta en JSON. Hace falta porque las
     * propiedades son privadas: json_encode no las ve.
     */
    public function toArray(): array
    {
        return [
            'nit'                    => $this->nit,
            'razon_social'           => $this->razon_social,
            'nombre_contacto'        => $this->nombre_contacto,
            'correo'                 => $this->correo,
            'telefono'               => $this->telefono,
            'ciudad'                 => $this->ciudad,
        ];
    }
}
