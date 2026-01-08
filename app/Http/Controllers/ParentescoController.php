<?php

namespace App\Http\Controllers;

use App\Models\Parentesco;
use Illuminate\Http\Request;

class ParentescoController extends Controller
{
    /**
     * Obtiene un parentesco por su descripción.
     * 
     * @param string $descripcion
     * @return int
     */
    public function getParentesco(string $descripcion)
    {
        $parentesco = Parentesco::where('desc_parentesco', 'LIKE', "%{$descripcion}")->first();
        return $parentesco->id;
    }
}
