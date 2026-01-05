<?php

namespace App\Http\Controllers;

use App\Exports\CertificadosExport;
use App\Imports\certificadosImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Validator;

class CertificadosImportController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/certificados/import",
     *      operationId="import",
     *      tags={"Certificados"},
     *      summary="Importar certificados",
     *      description="Importar certificados desde archivo Excel",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"archivo","contrato_id"},
     *              @OA\Property(property="archivo", type="string", format="file", example="user@example.com"),
     *              @OA\Property(property="contrato_id", type="integer", example="secret")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Certificados importados correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="string", example="success"),
     *              @OA\Property(property="message", type="string", example="Certificados importados correctamente."),
     *          )
     *      ),
     *      @OA\Response(response=401, description="Credenciales inválidas"),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     *
     * @param Request $request
     */
    public function import(Request $request)
    {
        $validacion = Validator::make(
            $request->all(), [
                'archivo' => 'required|file|mimes:xlsx,xls',
                'contrato_id' => [
                    'required', 
                    'integer', 
                    \Illuminate\Validation\Rule::exists('mysql_personas.contrato', 'id_contrato')
                ]
            ],
            [
                'archivo.required' => 'El archivo es obligatorio.',
                'archivo.file' => 'El archivo debe ser un archivo.',
                'archivo.mimes' => 'El archivo debe ser un archivo .xlsx o .xls.',
                'contrato_id.required' => 'El contrato es obligatorio.',
                'contrato_id.integer' => 'El contrato debe ser un número entero.',
                'contrato_id.exists' => 'El contrato no existe.',
            ]
        );

        if ( $validacion->fails() ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al validar los datos.',
                'errors' => $validacion->errors()
            ], 422);
        }

        try {
            $file = $request->file('archivo');
            $contrato_id = $request->input('contrato_id');

            $import = new CertificadosImport($contrato_id);
            
            Excel::import($import, $file);

            if ( !empty($import->errores) ) {
                $numero_contrato = Contrato::find($contrato_id)->num_contrato;
                $fecha_actual = now()->format('Y-m-d_H-i-s');
                $filename = "errores_carga_{$numero_contrato}_{$fecha_actual}.xlsx";

                // Descarga directa de excel de errores
                // return Excel::download(new CertificadosExport($import->errores), $filename);

                $excel = Excel::raw(new CertificadosExport($import->errores), \Maatwebsite\Excel\Excel::XLSX);
                $base64 = base64_encode($excel);

                return response()->json([
                    'status' => 'warning',
                    'message' => 'El proceso ha finalizado con algunos errores.',
                    'errores' => $import->errores,
                    'archivo' => [
                        'nombre' => $filename,
                        'contenido' => $base64,
                        'tipo' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Certificados importados correctamente sin errores ni omisiones.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en la importación de certificados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error inesperado al procesar el archivo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
