<?php

namespace App\Http\Controllers;

use App\Models\Certificado;
use App\Models\CertificadosTercero;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CertificadoController extends Controller
{

    /**
     * Crea los certificados de los titulares
     * 
     * @param array $array_titulares
     * @param int $contrato_id
     * @return array<array|array{certificado_id: int, error: string, message: string, tercero_id: mixed|array{codigo_certificado: mixed, contrato_id: int, error: string}}>
     */
    public function storeTitulares(array $array_titulares, int $contrato_id)
    {
        $errores = [];
        DB::connection('mysql_personas')->transaction(function () use ($array_titulares, &$errores, $contrato_id) {
            foreach ( $array_titulares as $titular ) {
                try {
                    $verifica_certificado_existente = Certificado::where('contrato_id', $contrato_id)
                    ->where('codigo_certificado', $titular['documento'])
                    ->exists();

                    //* Si el certificado ya existe, saltamos el ciclo
                    if ( $verifica_certificado_existente ) {
                        $errores[] = [
                            'contrato_id' => $contrato_id,
                            'codigo_certificado' => $titular['documento'],
                            'error' => 'Error al crear certificado: El certificado ya existe.',
                        ];
                        Log::error("Error al crear certificado: El certificado ya existe." . [
                            'contrato_id' => $contrato_id,
                            'nombres' => $titular['nombres'],
                            'apellidos' => $titular['apellidos'],
                            'codigo_certificado' => $titular['documento'],
                            'error' => 'Error al crear certificado: El certificado ya existe.',
                            'message' => 'Error al crear certificado: El certificado ya existe.',
                        ]);
                        continue;
                    }

                    //* Si el certificado no existe, lo creamos
                    $certificado = Certificado::create([
                        'contrato_id' => $contrato_id,
                        'codigo_certificado' => $titular['documento'],
                    ]);
                } catch ( QueryException $th ) {
                    $errores[] = [
                        'contrato_id' => $contrato_id,
                        'nombres' => $titular['nombres'],
                        'apellidos' => $titular['apellidos'],
                        'codigo_certificado' => $titular['documento'],
                        'error' => $th->getMessage(),
                        'message' => 'Ha ocurrido un error al registrar el certificado.',
                    ];
                    Log::error("Error al crear certificado: " . [
                        'contrato_id' => $contrato_id,
                        'nombres' => $titular['nombres'],
                        'apellidos' => $titular['apellidos'],
                        'codigo_certificado' => $titular['documento'],
                        'error' => $th->getMessage(),
                        'message' => 'Ha ocurrido un error al registrar el certificado.',
                    ]);
                    continue;
                }

                try {
                    $verifica_certificado_tercero_existente = CertificadosTercero::where('certificado_id', $certificado->id)
                    ->where('tercero_id', $titular['tercero_id'])
                    ->exists();

                    //* Si el certificado tercero ya existe, saltamos el ciclo
                    if ( $verifica_certificado_tercero_existente ) {
                        $errores[] = [
                            'contrato_id' => $contrato_id,
                            'nombres' => $titular['nombres'],
                            'apellidos' => $titular['apellidos'],
                            'codigo_certificado' => $titular['documento'],
                            'error' => 'Ha ocurrido un error al registrar el certificado tercero',
                            'message' => 'Error al crear certificado tercero: El certificado tercero ya existe.',
                        ];
                        Log::error("Error al crear certificado tercero: El certificado tercero ya existe." . [
                            'contrato_id' => $contrato_id,
                            'nombres' => $titular['nombres'],
                            'apellidos' => $titular['apellidos'],
                            'codigo_certificado' => $titular['documento'],
                            'error' => 'Ha ocurrido un error al registrar el certificado tercero',
                            'message' => 'Error al crear certificado tercero: El certificado tercero ya existe.',
                        ]);
                        continue;
                    }

                    //* Si el certificado tercero no existe, lo creamos
                    CertificadosTercero::create([
                        'certificado_id' => $certificado->id,
                        'tercero_id' => $titular['tercero_id'],
                        'parentesco_id' => 5,
                        'fecha_ingreso' => now()->format('Y-m-d'),
                    ]);
                } catch ( QueryException $qe ) {
                    $errores[] = [
                        'contrato_id' => $contrato_id,
                        'nombres' => $titular['nombres'],
                        'apellidos' => $titular['apellidos'],
                        'codigo_certificado' => $titular['documento'],
                        'error' => $qe->getMessage(),
                        'message' => 'Ha ocurrido un error al registrar el certificado tercero.',
                    ];
                    Log::error("Error al crear certificado: " . [
                        'contrato_id' => $contrato_id,
                        'nombres' => $titular['nombres'],
                        'apellidos' => $titular['apellidos'],
                        'codigo_certificado' => $titular['documento'],
                        'error' => $qe->getMessage(),
                        'message' => 'Ha ocurrido un error al registrar el certificado tercero.',
                    ]);
                    continue;
                }
            }
        });

        return $errores;
    }

    /**
     * Crea los certificados de los familiares
     * 
     * @param array $array_familiares
     * @param int $contrato_id
     * @return array<array|array{apellidos: string, codigo_certificado: numeric, contrato_id: int, error: string, message: string, nombres: string}>
     */
    public function storeCargaFamiliar(array $array_familiares, int $contrato_id)
    {
        $errores = [];
        DB::connection('mysql_personas')->transaction(function () use ($array_familiares, &$errores, $contrato_id) {
            foreach ( $array_familiares as $familiar ) {
                $certificado_tercero = Certificado::where('contrato_id', $contrato_id)
                ->where('codigo_certificado', $familiar['documento_titular'])
                ->first();

                if ( !$certificado_tercero ) {
                    $errores[] = [
                        'contrato_id' => $contrato_id,
                        'nombres' => $familiar['nombres'],
                        'apellidos' => $familiar['apellidos'],
                        'codigo_certificado' => $familiar['documento_titular'],
                        'error' => 'Error al crear certificado: El certificado no existe.',
                        'message' => 'Error al crear certificado: El certificado no existe.',
                    ];
                    Log::error("Error al crear certificado: El certificado no existe." . [
                        'contrato_id' => $contrato_id,
                        'nombres' => $familiar['nombres'],
                        'apellidos' => $familiar['apellidos'],
                        'codigo_certificado' => $familiar['documento_titular'],
                        'error' => 'Error al crear certificado: El certificado no existe.',
                        'message' => 'Error al crear certificado: El certificado no existe.',
                    ]);
                    continue;
                }

                //* Obtenemos el id del parentesco
                $parentesco_controller = app(ParentescoController::class);
                $parentesco_id = $parentesco_controller->getParentesco($familiar['parentesco']);

                if ( !$parentesco_id ) {
                    $errores[] = [
                        'contrato_id' => $contrato_id,
                        'nombres' => $familiar['nombres'],
                        'apellidos' => $familiar['apellidos'],
                        'codigo_certificado' => $familiar['documento_titular'],
                        'error' => 'Error al crear certificado: El parentesco no existe.',
                        'message' => 'Error al crear certificado: El parentesco no existe.',
                    ];
                    Log::error("Error al crear certificado: El parentesco no existe." . [
                        'contrato_id' => $contrato_id,
                        'nombres' => $familiar['nombres'],
                        'apellidos' => $familiar['apellidos'],
                        'codigo_certificado' => $familiar['documento_titular'],
                        'error' => 'Error al crear certificado: El parentesco no existe.',
                        'message' => 'Error al crear certificado: El parentesco no existe.',
                    ]);
                    continue;
                }

                try {
                    $verifica_certificado_tercero_existente = CertificadosTercero::where('certificado_id', $certificado_tercero->id)
                    ->where('tercero_id', $familiar['tercero_id'])
                    ->exists();

                    //* Si el certificado tercero ya existe, saltamos el ciclo
                    if ( $verifica_certificado_tercero_existente ) {
                        $errores[] = [
                            'contrato_id' => $contrato_id,
                            'nombres' => $familiar['nombres'],
                            'apellidos' => $familiar['apellidos'],
                            'codigo_certificado' => $familiar['documento_titular'],
                            'error' => 'Ha ocurrido un error al registrar el certificado tercero',
                            'message' => 'Error al crear certificado tercero: El certificado tercero ya existe.',
                        ];
                        Log::error("Error al crear certificado tercero: El certificado tercero ya existe." . [
                            'contrato_id' => $contrato_id,
                            'nombres' => $familiar['nombres'],
                            'apellidos' => $familiar['apellidos'],
                            'codigo_certificado' => $familiar['documento_titular'],
                            'error' => 'Ha ocurrido un error al registrar el certificado tercero',
                            'message' => 'Error al crear certificado tercero: El certificado tercero ya existe.',
                        ]);
                        continue;
                    }

                    CertificadosTercero::create([
                        'certificado_id' => $certificado_tercero->id,
                        'tercero_id' => $familiar['tercero_id'],
                        'parentesco_id' => $parentesco_id,
                        'fecha_ingreso' => now()->format('Y-m-d'),
                    ]);
                } catch ( QueryException $qe ) {
                    $errores[] = [
                        'contrato_id' => $contrato_id,
                        'nombres' => $familiar['nombres'],
                        'apellidos' => $familiar['apellidos'],
                        'codigo_certificado' => $familiar['documento_titular'],
                        'error' => $qe->getMessage(),
                        'message' => 'Ha ocurrido un error al registrar el certificado tercero.',
                    ];
                    Log::error("Error al crear certificado: " . [
                        'contrato_id' => $contrato_id,
                        'nombres' => $familiar['nombres'],
                        'apellidos' => $familiar['apellidos'],
                        'codigo_certificado' => $familiar['documento_titular'],
                        'error' => 'Error al crear certificado: El certificado no existe.',
                        'message' => 'Error al crear certificado: El certificado no existe.',
                    ]);
                    continue;
                }
            }
        });

        return $errores;
    }
}
