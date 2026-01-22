<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RicorocksDigitalAgency\Soap\Facades\Soap;

class InmaController extends Controller
{
    
    private $url;
    private $user;
    private $password;
    private $user_web;
    private $password_web;

    public function __construct()
    {
        $this->url = config('app.api.inma.url');
        $this->user = config('app.api.inma.user');
        $this->password = config('app.api.inma.password');
        $this->user_web = config('app.api.inma.user_web');
        $this->password_web = config('app.api.inma.password_web');
    }

    /**
     * Realiza una peticion a la API de Catalogos de INMA
     * 
     * @param string $metodo
     * @throws \Exception
     * @return \Illuminate\Http\JsonResponse
     */
    public function peticionInma(string $metodo)
    {
        try{
            $response = Soap::to(config('app.api.inma.url'))
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

            if ( $response->response->vError !== 'OK' ) {
                throw new \Exception($response->response->vError);
            }

            return response()->json($response->response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener marcas de inma
     * 
     * @throws \Exception
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMarcas()
    {
        try {
            $marcas = $this->peticionInma('Marcas')->getData();

            if ( empty($marcas->MarcasResult->string) ) {
                throw new \Exception('No se encontraron marcas');
            }

            $arreglo_marcas = [];
            foreach ($marcas->MarcasResult->string as $marca) {
                $marca_codigo = substr($marca, 0, 3);
                $marca_descripcion = substr($marca, 3);

                $arreglo_marcas[] = [
                    'marca_codigo' => $marca_codigo,
                    'marca_descripcion' => trim($marca_descripcion)
                ];
            }

            return response()->json($arreglo_marcas);
        } catch (\Exception $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los modelos de INMA
     * 
     * @throws \Exception
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModelos()
    {
        try {
            $modelos = $this->peticionInma('Modelos')->getData();

            if ( empty($modelos->ModelosResult->string) ) {
                throw new \Exception('No se encontraron modelos');
            }

            $arreglo_modelos = [];
            foreach ($modelos->ModelosResult->string as $modelo) {

            //* Tomar los 6 primeros caracteres del strim y separarlos del resto, obtener dos partes
            $marca_codigo = substr($modelo, 0, 3);
            $modelo_codigo = substr($modelo, 3, 3);
            $modelo_descripcion = substr($modelo, 6);

            $arreglo_modelos[] = [
                'marca_codigo' => $marca_codigo,
                'modelo_codigo' => $modelo_codigo,
                'modelo_descripcion' => trim($modelo_descripcion)
            ];
            }

            return response()->json($arreglo_modelos);
        } catch (\Exception $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtiene las versiones de INMA
     * 
     * @throws \Exception
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVersiones()
    {
        try {
            $versiones = $this->peticionInma('Version')->getData();

            if ( empty($versiones->VersionResult->string) ) {
                throw new \Exception('No se encontraron versiones');
            }

            $arreglo_versiones = [];
            foreach ($versiones->VersionResult->string as $version) {
                $marca_codigo = substr($version, 0, 3);
                $modelo_codigo = substr($version, 3, 3);
                $civi = substr($version, 0, 8);
                //* Obtener el texto que está entre el noveno caracter y el ultimo simbolo | (pipe)
                $version_descripcion = trim(substr($version, 8, strlen($version) - 15));
                //* Obtener ultimo 4 caracteres
                $anio_fabricacion = (int) substr($version, strlen($version) - 4, 4);
                //* Obtenemos el año anterior al actual
                $anio_anterior_actual = date('Y') - 1;

                //* Obtenemos las versiones más recientes
                if ( $anio_fabricacion >= $anio_anterior_actual ) {     
                    $arreglo_versiones[] = [
                        'marca_codigo' => $marca_codigo,
                        'modelo_codigo' => $modelo_codigo,
                        'civi' => $civi,
                        'version_descripcion' => trim($version_descripcion),
                        'anio_fabricacion' => $anio_fabricacion
                    ];
                }

            }

            return response()->json($arreglo_versiones);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
