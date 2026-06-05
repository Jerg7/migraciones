<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\InmaService;
use Illuminate\Http\JsonResponse;

class InmaController extends Controller
{
    public function __construct(private InmaService $_InmaService) {}

    /**
     * Obtener marcas de inma
     * 
     * @return JsonResponse
     */
    public function getMarcas(): JsonResponse
    {
        try {
            $marcas = $this->_InmaService->getMarcas();
            return response()->json($marcas);
        } catch (\Exception $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los modelos de INMA
     * 
     * @return JsonResponse
     */
    public function getModelos(): JsonResponse
    {
        try {
            $modelos = $this->_InmaService->getModelos();
            return response()->json($modelos);
        } catch (\Exception $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtiene las versiones de INMA
     * 
     * @return JsonResponse
     */
    public function getVersiones(): JsonResponse
    {
        try {
            $versiones = $this->_InmaService->getVersiones();
            return response()->json($versiones);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
