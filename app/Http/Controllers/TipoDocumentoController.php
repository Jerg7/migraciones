<?php

namespace App\Http\Controllers;

use App\Models\TipoDocumento;
use Illuminate\Http\Request;

class TipoDocumentoController extends Controller
{
    /**
     * Obtener tipo de documento
     * 
     * @param string $siglas
     * @return TipoDocumento
     */
    public function getTipoDocumento(string $siglas)
    {
        return TipoDocumento::where('siglas', trim($siglas))->first();
    }
}
