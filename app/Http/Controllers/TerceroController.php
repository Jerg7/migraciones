<?php

namespace App\Http\Controllers;

use App\Models\Tercero;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Validator;

class TerceroController extends Controller
{
    public $tipo_documento_controller;

    public function __construct(TipoDocumentoController $tipoDocumentoController)
    {
        $this->tipo_documento_controller = $tipoDocumentoController;
    }

    /**
     * Registrar tercero
     * 
     * @return int|string
     */
    public function storeTerceros(array $array_terceros)
    {
        //* Obtener tipo de documento
        $tipo_documento = $this->tipo_documento_controller->getTipoDocumento($array_terceros['codigo_documento']);

        //* Obtenemos el tercero
        $tercero = $this->getTerceros(
            $tipo_documento->cod_documento,
            $array_terceros['documento'], 
            $array_terceros['fecha_nacimiento'],
            $array_terceros['nombres']
        );

        //* Si no existe el tercero lo registramos
        if ( !$tercero ) {
            //* Ordenamos los datos a insertar del tercero
            $tercero_nuevo = [
                'cod_documento' => $tipo_documento->id,
                'nombre_razonsocial' => $array_terceros['nombres'],
                'apellido' => $array_terceros['apellidos'],
                'fecha_nac_consti' => $array_terceros['fecha_nacimiento'],
                'edad' => $array_terceros['edad'],
                'sexo' => $array_terceros['sexo'],
            ];

            //* Asignamos el rif o la cedula al arreglo del tercero
            in_array($array_terceros['codigo_documento'], ['V', 'E', 'P', 'M']) 
                ? $tercero_nuevo['cedula'] = $array_terceros['documento'] 
                : $tercero_nuevo['rif'] = $array_terceros['documento']; 

            //* Insertamos el tercero y capturamos el resultado
            $tercero = DB::connection('mysql')->transaction(function () use ($tercero_nuevo) {
                return Tercero::create($tercero_nuevo);
            });
        }

        //* Retornamos el id del tercero existente
        return $tercero->id_terceros;
    }

    /**
     * Obtener tercero por documento
     * 
     * @param int $codigo_documento
     * @param string $documento
     * @param string $fecha_nacimiento
     * @param string $nombres
     * @return Tercero|null
     */
    public function getTerceros(
        int $codigo_documento, 
        string $documento, 
        string $fecha_nacimiento, 
        string $nombres
    )
    {
        if ( $codigo_documento === 6 ) {
            $tercero_menor = Tercero::whereLike('cedula', substr($documento, 0, -1));
            $contar_tercero_menor = $tercero_menor->count();

            //* Si existe un tercero menor para esta cedula
            if ( $contar_tercero_menor >= 1 ) {
                //* Verifica si existen terceros con la misma fecha de nacimiento
                $candidatos = $tercero_menor->where('fecha_nac_consti', $fecha_nacimiento)->get();

                if ( $candidatos->isNotEmpty() ) {
                    //* Comprobamos similitud de nombres en PHP para mayor flexibilidad
                    foreach ( $candidatos as $candidato ) {
                        similar_text(mb_strtoupper($candidato->nombre_razonsocial), mb_strtoupper($nombres), $porcentaje);

                        //* Si la similitud es superior al 80%, consideramos que es la misma persona
                        if ( $porcentaje > 80 ) {
                            //* Retornamos el tercero existente
                            return $candidato;
                        }
                    }    
                }

                //* Si no coincide el nombre suficiente, seguimos aumentando el conteo de terceros asociados
                $documento = "{$documento}{$contar_tercero_menor}";
            }
        }

        //* Consultamos el tercero
        $tercero = Tercero::where('cod_documento', $codigo_documento)
        ->when(in_array($codigo_documento, [3, 4, 5, 6]), function ($query) use ($documento) {
            $query->where('cedula', $documento);
        }, function ($query) use ($documento) {
            $query->where('rif', $documento);
        })
        ->first();

        return $tercero;

    }
}
