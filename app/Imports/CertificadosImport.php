<?php

namespace App\Imports;

use App\Exports\CertificadosExport;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\TerceroController;
use App\Models\Contrato;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Excel;

class CertificadosImport implements ToCollection, WithChunkReading, WithHeadingRow, WithBatchInserts
{
    public $errores;
    public $contrato_id;

    public function __construct(int $contrato)
    {
        $this->contrato_id = $contrato;
        $this->errores = [];
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    /**
    * @param Collection $collection
    */
    public function collection( Collection $collection )
    {
        $array_terceros = [];
        $array_titulares = [];
        $array_familiares = [];

        foreach ( $collection as $row ) {
            dump($row);die;
            //* Convertimos la fecha de excel a formato Y/m/d
            $fecha_excel = $this->convertirFechaExcel(trim($row['fnac']));

            //* Convertimos la fecha de excel a formato base de datos
            $fecha_nacimiento = $this->convertirFecha($fecha_excel);

            //* Calculamos la edad
            $edad = Carbon::parse($fecha_nacimiento)->age;

            //* Agregamos el arreglo para cargar inicialmente los terceros
            $array_terceros[] = [
                'documento_titular' => trim($row['cedula_titular']),
                'codigo_documento' => trim($row['nacionalidad_beneficiario']),
                'documento' => trim($row['cedula_beneficiario']),
                'parentesco' => trim($row['parentesco']),
                'nombres' => trim($row['nombres']),
                'apellidos' => trim($row['apellidos']),
                'fecha_nacimiento' => trim($fecha_nacimiento),
                'edad' => trim($edad),
                'sexo' => trim($row['sexo']),
            ];
        }

        //* Instanciamos los controladores
        $tercero_controller = app(TerceroController::class);
        $certificado_controller = app(CertificadoController::class);

        //* Insertamos los terceros
        for ( $i = 0; $i < count($array_terceros); $i++ ) {
            $tercero = $tercero_controller->storeTerceros($array_terceros[$i]);

            //* Agregamos el id del tercero al arreglo en su respectiva posicion
            $array_terceros[$i]['tercero_id'] = $tercero;

            //* Agregamos el arreglo para cargar inicialmente los titulares y la carga familiar
            if ( strtolower(trim($array_terceros[$i]['parentesco'])) === "titular" ) {
                $array_titulares[] = $array_terceros[$i];
            } else {
                $array_familiares[] = $array_terceros[$i];
            }
        }

        //* Insertamos los titulares de la póliza
        $titulares = $certificado_controller->storeTitulares($array_titulares, $this->contrato_id);

        //* Insertamos la carga familiar de la póliza
        $familiares = $certificado_controller->storeCargaFamiliar($array_familiares, $this->contrato_id);

        //* Agrupamos los errores
        $this->errores = array_merge($this->errores, $titulares, $familiares);
        
        return [
            'status' => 200,
            'message' => 'Certificados cargados correctamente, sin errores ni omisiones.',
            'errores' => $this->errores
        ];
    }

    /**
     * Convierte el valor de la fecha del archivo excel a formato Y/m/d
     * 
     * @param string $valor
     * @return string
     */
    private function convertirFechaExcel( string $valor )
    {
        // Verificamos si el valor es un número
        if ( is_numeric($valor) ) {
            // Excel usa el 1 de enero de 1900 como base
            $fechaBase = Carbon::createFromDate(1900, 1, 1);

            // Restamos 1 día porque Excel cuenta mal desde el día 1 (se cuenta 1900-01-01 como 1)
            return $fechaBase->addDays($valor - 2)->format('Y-m-d');
        }

        // Si no es un número, devolvemos el valor tal cual (podría ser ya una fecha)
        return $valor;
    }

    /**
     * Convertir fecha en formato Y-m-d
     * 
     * @param string $fecha
     * @return string
     */
    private function convertirFecha( string $fecha ) 
    {
        // Verificamos si el formato es d/m/Y
        $fechaObj = DateTime::createFromFormat('d/m/Y', $fecha);
        // Comprobamos si la fecha fue creada correctamente y si el formato coincide
        if ($fechaObj && $fechaObj->format('d/m/Y') === $fecha) {
            // Convertimos a Y-m-d
            return $fechaObj->format('Y-m-d');
        }
        
        // Si no es el formato esperado, devolvemos el string original
        return $fecha;
    }

    /**
     * Separa nombres de compuestos
     * 
     * @param string $nombre_complero
     * @return array
     */
    private function separarNombresApellidos( string $nombre_complero )
    {
        // Lista de partículas comunes en nombres y apellidos compuestos
        $particles = ['de', 'del', 'la', 'los', 'las', 'da', 'dos', 'das'];
    
        // Separamos el nombre completo por espacios
        $partes = explode(' ', trim($nombre_complero));
    
        // Variables para almacenar las partes unidas y el resultado final
        $combinados = [];
        $temp = [];
    
        // Unimos las partículas con las siguientes palabras
        foreach ($partes as $i => $palabra) {
            if (in_array(strtolower($palabra), $particles)) {
                $temp[] = $palabra;
            } else {
                if (!empty($temp)) {
                    // Si tenemos palabras en $temp, las unimos con la siguiente palabra
                    $temp[] = $palabra;
                    $combinados[] = implode('_', $temp);  // Unir con '_'
                    $temp = [];  // Resetear temporal
                } else {
                    // Si no hay partículas, simplemente agregamos la palabra
                    $combinados[] = $palabra;
                }
            }
        }
    
        // Si por alguna razón quedó algo en $temp (por ejemplo, al final del string)
        if (!empty($temp)) {
            $combinados[] = implode('_', $temp);
        }
    
        // Ahora tenemos las partes unidas; definimos un punto de separación lógico
        $mitad = floor(count($combinados) / 2);
    
        // Suponemos que los apellidos pueden estar en la primera o segunda mitad
        $posibles_apellidos = array_slice($combinados, 0, $mitad);
        $posibles_nombres = array_slice($combinados, $mitad);
    
        // Convertimos los arrays a strings
        $nombres = implode(' ', $posibles_nombres);
        $apellidos = implode(' ', $posibles_apellidos);
    
        // Retornamos las dos variables; nombres y apellidos
        return [$nombres, $apellidos];
    }
}
