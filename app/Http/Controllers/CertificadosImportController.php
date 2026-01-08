<?php

namespace App\Http\Controllers;

use App\Exports\CertificadosExport;
use App\Imports\certificadosImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CertificadosImportController extends Controller
{
    /**
     * @param Request $request
     */
    #[OA\Post(
        path: "/api/importar-certificados",
        operationId: "import",
        tags: ["Certificados"],
        summary: "Importar certificados",
        description: "Importar certificados desde archivo Excel",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        description: "Datos del formulario de importación",
        content: new OA\MediaType(
            mediaType: "multipart/form-data",
            schema: new OA\Schema(
                required: ["archivo", "contrato_id"],
                properties: [
                    new OA\Property(
                        property: "archivo",
                        description: "Archivo Excel (.xlsx, .xls)",
                        type: "string",
                        format: "binary"
                    ),
                    new OA\Property(
                        property: "contrato_id",
                        description: "ID del contrato asociado",
                        type: "integer",
                        example: 1
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Certificados importados correctamente",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Certificados importados correctamente.")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Credenciales inválidas")]
    #[OA\Response(response: 422, description: "Error de validación")]
    public function import(Request $request)
    {
        $validacion = Validator::make(
            $request->all(), [
                'archivo' => 'required|file|mimes:xlsx,xls',
                'contrato_id' => [
                    'required', 
                    'integer', 
                    Rule::exists('mysql_personas.contrato', 'id_contrato')
                ]
            ],
            [
                'required' => 'El :attribute es obligatorio.',
                'file' => 'El :attribute debe ser un archivo Excel.',
                'mimes' => 'El :attribute debe ser un archivo de tipo :values.',
                'integer' => 'El :attribute debe ser un número entero.',
                'exists' => 'El :attribute no existe.',
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
            $contrato_id = $request->contrato_id;
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
