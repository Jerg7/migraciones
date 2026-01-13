<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DerivacionErroresExport implements FromArray, WithHeadings
{
    protected $errores;

    public function __construct(array $errores)
    {
        $this->errores = $errores;
    }

    public function array(): array
    {
        return $this->errores;
    }

    public function headings(): array
    {
        return [
            'Código Certificado',
            'Nombre',
            'Apellido',
            'Parentesco',
            'Error',
        ];
    }
}
