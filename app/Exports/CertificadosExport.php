<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CertificadosExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithMapping
{
    protected $data;

    public function __construct(array $errores)
    {
        $this->data = $errores;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'ID Contrato',
            'Nombre asegurado',
            'Apellido asegurado',
            'Código certificado',
            'Mensaje',
        ];
    }

    public function map($row): array
    {
        return [
            $row['contrato_id'],
            $row['nombres'],
            $row['apellidos'],
            $row['codigo_certificado'],
            $row['error'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,  // Contrato
            'B' => 50,  // Nombre asegurado
            'C' => 50,  // Apellido asegurado
            'D' => 20,  // Código certificado
            'E' => 100,  // Mensaje
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // Estilo para el encabezado
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
        ]);

        // Estilo para las filas de datos
        foreach (range(2, $lastRow) as $row) {
            $estado = $sheet->getCell('G' . $row)->getValue();
            
            if ($estado === 'ERROR') {
                $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFE6E6'],  // Rojo claro
                    ],
                ]);
            } elseif ($estado === 'ÉXITO') {
                $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E6FFE6'],  // Verde claro
                    ],
                ]);
            }
        }

        // Bordes y alineación para toda la tabla
        $sheet->getStyle('A1:E' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        return [];
    }
}
