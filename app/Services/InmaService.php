<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RicorocksDigitalAgency\Soap\Facades\Soap;

class InmaService
{
    private string $url;
    private string $user;
    private string $password;
    private string $user_web;
    private string $password_web;
    private array $marcas_catalogo = [];
    private array $modelos_catalogo = [];

    public function __construct()
    {
        $this->url = (string) config('app.api.inma.url');
        $this->user = (string) config('app.api.inma.user');
        $this->password = (string) config('app.api.inma.password');
        $this->user_web = (string) config('app.api.inma.user_web');
        $this->password_web = (string) config('app.api.inma.password_web');
    }

    /**
     * Realiza una petición a la API de Catálogos de INMA
     *
     * @param string $metodo
     * @throws \Exception
     * @return mixed
     */
    public function peticionInma(string $metodo): mixed
    {
        $response = Soap::to($this->url)
            ->withBasicAuth($this->user_web, $this->password_web)
            ->withOptions([
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ])
            ])
            ->call($metodo, [
                'vuser' => $this->user,
                'vpass' => $this->password,
            ]);

        if (($response->response->vError ?? '') !== 'OK') {
            throw new \Exception($response->response->vError ?? 'Error desconocido en INMA API');
        }

        return $response->response;
    }

    /**
     * Obtener marcas procesadas de INMA
     *
     * @throws \Exception
     * @return array
     */
    public function getMarcas(): array
    {
        $marcas_result = $this->peticionInma('Marcas');

        if (empty($marcas_result->MarcasResult->string)) {
            throw new \Exception('No se encontraron marcas');
        }

        $arreglo_marcas = [];
        foreach ($marcas_result->MarcasResult->string as $marca) {
            $marca_codigo = substr($marca, 0, 3);
            $marca_descripcion = substr($marca, 3);

            $arreglo_marcas[] = [
                'marca_codigo' => $marca_codigo,
                'marca_descripcion' => trim($marca_descripcion)
            ];
        }

        return $arreglo_marcas;
    }

    /**
     * Obtener modelos procesados de INMA
     *
     * @throws \Exception
     * @return array
     */
    public function getModelos(): array
    {
        $modelos_result = $this->peticionInma('Modelos');

        if (empty($modelos_result->ModelosResult->string)) {
            throw new \Exception('No se encontraron modelos');
        }

        $arreglo_modelos = [];
        foreach ($modelos_result->ModelosResult->string as $modelo) {
            $marca_codigo = substr($modelo, 0, 3);
            $modelo_codigo = substr($modelo, 3, 3);
            $modelo_descripcion = substr($modelo, 6);

            $arreglo_modelos[] = [
                'marca_codigo' => $marca_codigo,
                'modelo_codigo' => $modelo_codigo,
                'modelo_descripcion' => trim($modelo_descripcion)
            ];
        }

        return $arreglo_modelos;
    }

    /**
     * Obtener versiones procesadas de INMA
     *
     * @throws \Exception
     * @return array
     */
    public function getVersiones(): array
    {
        $versiones_result = $this->peticionInma('Version');

        if (empty($versiones_result->VersionResult->string)) {
            throw new \Exception('No se encontraron versiones');
        }

        $arreglo_versiones = [];
        foreach ($versiones_result->VersionResult->string as $version) {
            $marca_codigo = substr($version, 0, 3);
            $modelo_codigo = substr($version, 3, 3);
            $civi = substr($version, 0, 8);
            $version_descripcion = trim(substr($version, 8, strlen($version) - 15));
            $anio_fabricacion = (int) substr($version, strlen($version) - 4, 4);
            $anio_anterior_actual = date('Y') - 20;

            if ($anio_fabricacion >= $anio_anterior_actual) {
                $arreglo_versiones[] = [
                    'marca_codigo' => $marca_codigo,
                    'modelo_codigo' => $modelo_codigo,
                    'civi' => $civi,
                    'version_descripcion' => trim($version_descripcion),
                    'anio_fabricacion' => $anio_fabricacion
                ];
            }
        }

        return $arreglo_versiones;
    }

    /**
     * Normaliza un texto para comparar (espacios y mayúsculas/minúsculas)
     */
    private function normalizar(mixed $texto): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $texto)));
    }

    /**
     * Obtiene las versiones preparadas con su marca y modelo asociados
     *
     * @throws \Exception
     * @return array
     */
    public function getPreparedVersiones(): array
    {
        $this->marcas_catalogo = $this->getMarcas();
        $this->modelos_catalogo = $this->getModelos();
        $versiones = $this->getVersiones();

        // Índices por código: evita el O(n*m) de buscar con Arr::first por cada versión
        $marcas_idx = [];
        foreach ($this->marcas_catalogo as $marca) {
            $marcas_idx[$marca['marca_codigo']] ??= $marca['marca_descripcion'];
        }

        $modelos_idx = [];
        foreach ($this->modelos_catalogo as $modelo) {
            $modelos_idx[$modelo['marca_codigo'] . $modelo['modelo_codigo']] ??= $modelo['modelo_descripcion'];
        }

        $resultado = [];
        foreach ($versiones as $version) {
            $version['marca_descripcion'] = $marcas_idx[$version['marca_codigo']] ?? '';
            $version['modelo_descripcion'] = $modelos_idx[$version['marca_codigo'] . $version['modelo_codigo']] ?? '';
            $resultado[] = (object) $version;
        }

        return $resultado;
    }

    /**
     * Compara los datos del API con la Base de Datos para encontrar diferencias
     *
     * @param array $versiones
     * @return array
     */
    public function compareInmaData(array $versiones): array
    {
        $db = DB::connection('mysql_automovil');

        // Existentes indexados por llave normalizada (búsqueda O(1)); cursor() evita cargar todo en memoria
        $existing_marcas = [];
        foreach ($db->table('marcas')->select('cod_marca', 'descripcion')->cursor() as $row) {
            $existing_marcas[$this->normalizar($row->cod_marca) . '|' . $this->normalizar($row->descripcion)] = true;
        }

        $existing_modelos = [];
        foreach ($db->table('modelos')->select('cod_marca', 'cod_modelo', 'descripcion')->cursor() as $row) {
            $existing_modelos[$this->normalizar($row->cod_marca) . '|' . $this->normalizar($row->cod_modelo) . '|' . $this->normalizar($row->descripcion)] = true;
        }

        // Llave construida en PHP: CONCAT en SQL devuelve NULL si algún campo es NULL
        $existing_versiones = [];
        foreach ($db->table('versiones')->select('cod_marca', 'cod_modelo', 'civi', 'anio_vehiculo')->cursor() as $row) {
            $existing_versiones[$this->normalizar($row->cod_marca) . '|' . $this->normalizar($row->cod_modelo) . '|' . $this->normalizar($row->civi) . '|' . (int) $row->anio_vehiculo] = true;
        }

        $marcas_nuevas = [];
        $modelos_nuevos = [];
        $versiones_nuevas = [];

        $evaluar_marca = function (string $codigo, string $descripcion) use ($existing_marcas, &$marcas_nuevas) {
            if ($descripcion === '') {
                return;
            }
            $key = $this->normalizar($codigo) . '|' . $this->normalizar($descripcion);
            if (!isset($existing_marcas[$key])) {
                $marcas_nuevas[$key] = ['cod_marca' => $codigo, 'descripcion' => $descripcion];
            }
        };

        $evaluar_modelo = function (string $marca, string $codigo, string $descripcion) use ($existing_modelos, &$modelos_nuevos) {
            if ($descripcion === '') {
                return;
            }
            $key = $this->normalizar($marca) . '|' . $this->normalizar($codigo) . '|' . $this->normalizar($descripcion);
            if (!isset($existing_modelos[$key])) {
                $modelos_nuevos[$key] = ['cod_marca' => $marca, 'cod_modelo' => $codigo, 'descripcion' => $descripcion];
            }
        };

        // Catálogo completo de marcas y modelos (aunque no tengan versiones dentro del rango de años)
        foreach ($this->marcas_catalogo as $m) {
            $evaluar_marca($m['marca_codigo'], $m['marca_descripcion']);
        }
        foreach ($this->modelos_catalogo as $m) {
            $evaluar_modelo($m['marca_codigo'], $m['modelo_codigo'], $m['modelo_descripcion']);
        }

        foreach ($versiones as $version) {
            $evaluar_marca($version->marca_codigo, $version->marca_descripcion);
            $evaluar_modelo($version->marca_codigo, $version->modelo_codigo, $version->modelo_descripcion);

            $version_key = $this->normalizar($version->marca_codigo) . '|' . $this->normalizar($version->modelo_codigo) . '|'
                . $this->normalizar($version->civi) . '|' . (int) $version->anio_fabricacion;

            if (!isset($existing_versiones[$version_key]) && !isset($versiones_nuevas[$version_key])) {
                $versiones_nuevas[$version_key] = [
                    'cod_marca' => $version->marca_codigo,
                    'cod_modelo' => $version->modelo_codigo,
                    'civi' => $version->civi,
                    'descripcion' => trim($version->version_descripcion),
                    'anio_vehiculo' => $version->anio_fabricacion,
                ];
            }
        }

        return [
            'marcas' => array_values($marcas_nuevas),
            'modelos' => array_values($modelos_nuevos),
            'versiones' => array_values($versiones_nuevas),
        ];
    }
}
