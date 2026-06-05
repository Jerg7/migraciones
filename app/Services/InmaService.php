<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RicorocksDigitalAgency\Soap\Facades\Soap;

class InmaService
{
    private string $url;
    private string $user;
    private string $password;
    private string $user_web;
    private string $password_web;

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
     * Obtiene las versiones preparadas con su marca y modelo asociados
     *
     * @throws \Exception
     * @return array
     */
    public function getPreparedVersiones(): array
    {
        $marcas = $this->getMarcas();
        $modelos = $this->getModelos();
        $versiones = $this->getVersiones();

        $marcas_obj = json_decode(json_encode($marcas));
        $modelos_obj = json_decode(json_encode($modelos));
        $versiones_obj = json_decode(json_encode($versiones));

        foreach ($versiones_obj as $version) {
            $marca = Arr::first($marcas_obj, function ($marca) use ($version) {
                return $marca->marca_codigo == $version->marca_codigo;
            });

            $modelo = Arr::first($modelos_obj, function ($modelo) use ($version) {
                return $modelo->marca_codigo == $version->marca_codigo && $modelo->modelo_codigo == $version->modelo_codigo;
            });

            $version->marca_descripcion = $marca ? $marca->marca_descripcion : '';
            $version->modelo_descripcion = $modelo ? $modelo->modelo_descripcion : '';
        }

        return $versiones_obj;
    }

    /**
     * Compara los datos del API con la Base de Datos para encontrar diferencias
     *
     * @param array $versiones
     * @return array
     */
    public function compareInmaData(array $versiones): array
    {
        $existing_marcas = DB::connection('mysql_automovil')->table('marcas')
            ->select('cod_marca', 'descripcion')
            ->get()
            ->groupBy('cod_marca');

        $existing_modelos = DB::connection('mysql_automovil')->table('modelos')
            ->select('cod_marca', 'cod_modelo', 'descripcion')
            ->get()
            ->groupBy(fn($item) => "{$item->cod_marca}_{$item->cod_modelo}");

        $existing_versiones = DB::connection('mysql_automovil')->table('versiones')
            ->selectRaw("CONCAT(cod_marca, '_', cod_modelo, '_', civi, '_', anio_vehiculo) as version_key")
            ->pluck('version_key')
            ->flip()
            ->toArray();

        $marcas_nuevas = [];
        $modelos_nuevos = [];
        $versiones_nuevas = [];

        $marcas_vistas = [];
        $modelos_vistos = [];
        $versiones_vistas = [];

        foreach ($versiones as $version) {
            $marca_codigo = $version->marca_codigo;
            $marca_descripcion = $version->marca_descripcion;
            $modelo_codigo = $version->modelo_codigo;
            $modelo_descripcion = $version->modelo_descripcion;
            $civi = $version->civi;
            $anio_fabricacion = $version->anio_fabricacion;
            $version_descripcion = $version->version_descripcion;

            // 1. Comparar marca
            $marca_exists = isset($existing_marcas[$marca_codigo]) &&
                $existing_marcas[$marca_codigo]->contains(function ($item) use ($marca_descripcion) {
                    return stripos($item->descripcion, $marca_descripcion) !== false;
                });

            if (!$marca_exists) {
                $marca_key = $marca_codigo . '_' . $marca_descripcion;
                if (!isset($marcas_vistas[$marca_key])) {
                    $marcas_vistas[$marca_key] = true;
                    $marcas_nuevas[] = [
                        'cod_marca' => $marca_codigo,
                        'descripcion' => $marca_descripcion,
                    ];
                }
            }

            // 2. Comparar modelo
            $modelo_key_db = "{$marca_codigo}_{$modelo_codigo}";
            $modelo_exists = isset($existing_modelos[$modelo_key_db]) &&
                $existing_modelos[$modelo_key_db]->contains(function ($item) use ($modelo_descripcion) {
                    return stripos($item->descripcion, $modelo_descripcion) !== false;
                });

            if (!$modelo_exists) {
                $modelo_key = "{$marca_codigo}_{$modelo_codigo}_{$modelo_descripcion}";
                if (!isset($modelos_vistos[$modelo_key])) {
                    $modelos_vistos[$modelo_key] = true;
                    $modelos_nuevos[] = [
                        'cod_marca' => $marca_codigo,
                        'cod_modelo' => $modelo_codigo,
                        'descripcion' => $modelo_descripcion,
                    ];
                }
            }

            // 3. Comparar versión
            $version_key_db = "{$marca_codigo}_{$modelo_codigo}_{$civi}_{$anio_fabricacion}";
            $version_exists = isset($existing_versiones[$version_key_db]);

            if (!$version_exists) {
                $version_key = $version_key_db;
                if (!isset($versiones_vistas[$version_key])) {
                    $versiones_vistas[$version_key] = true;
                    $versiones_nuevas[] = [
                        'cod_marca' => $marca_codigo,
                        'cod_modelo' => $modelo_codigo,
                        'civi' => $civi,
                        'descripcion' => trim($version_descripcion),
                        'anio_vehiculo' => $anio_fabricacion,
                    ];
                }
            }
        }

        return [
            'marcas' => $marcas_nuevas,
            'modelos' => $modelos_nuevos,
            'versiones' => $versiones_nuevas,
        ];
    }
}
