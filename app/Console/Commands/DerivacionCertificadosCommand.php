<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DerivacionErroresExport;

class DerivacionCertificadosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:derivacion-certificados-command {numero? : El número a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para derivación de certificados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $poliza_antigua = $this->argument('numero');

        // Si no se pasó el argumento, solicitarlo de manera interactiva
        if ( !$poliza_antigua ) {
            $this->info('Bienvenido al comando de derivación de certificados.');
            $poliza_antigua = $this->ask('Por favor, ingrese el número de póliza a derivar (Póliza antigua)');
        }

        // Validar que sea numérico
        if ( !is_numeric($poliza_antigua) ) {
            $this->error('El valor ingresado debe ser un número.');
            return 1;
        }

        $this->info("Número de póliza antigua: {$poliza_antigua}");

        $opcion = $this->choice(
            '¿Qué acción deseas realizar con esta póliza?', 
            [
                'Carga completa', 
                'Carga de titulares', 
                'Carga de beneficiarios', 
                'Carga de beneficiarios sin menores', 
                'Carga de menores', 
                'Cancelar'
            ],
        );

        $this->info("Has seleccionado: $opcion");

        if ( $opcion === 'Cancelar' ) {
            $this->info('Operación cancelada.');
            return;
        }

        $this->info("Buscando certificados de la póliza...");

        //* Obtenemos la data de los certificados activos de la póliza segun la opcion seleccionada
        $data_activa_poliza = DB::connection('mysql_personas')->table('contrato')
        ->join('certificados', 'contrato.id_contrato', 'certificados.contrato_id')
        ->join('certificados_terceros', 'certificados.id', 'certificados_terceros.certificado_id')
        ->join('ridosm_general.parentesco', 'certificados_terceros.parentesco_id', 'parentesco.id')
        ->when($opcion === 'Carga de titulares', function ($query) {
            return $query->where('parentesco.desc_parentesco', 'LIKE', '%TITULAR%');
        })
        ->when($opcion === 'Carga de beneficiarios', function ($query) {
            return $query->where('parentesco.desc_parentesco', 'NOT LIKE', '%TITULAR%');
        })
        ->when($opcion === 'Carga de beneficiarios sin menores', function ($query) {
            return $query->join('ridosm_general.terceros', 'certificados_terceros.tercero_id', 'terceros.id_terceros')
            ->join('ridosm_general.tipo_documento', 'terceros.cod_documento', 'tipo_documento.cod_documento')
            ->where('tipo_documento.siglas', '!=', 'M')
            ->where('parentesco.desc_parentesco', 'NOT LIKE', '%HIJO%')
            ->where('parentesco.desc_parentesco', 'NOT LIKE', '%TITULAR%');
        })
        ->when($opcion === 'Carga de menores', function ($query) {
            return $query->join('ridosm_general.terceros', 'certificados_terceros.tercero_id', 'terceros.id_terceros')
            ->join('ridosm_general.tipo_documento', 'terceros.cod_documento', 'tipo_documento.cod_documento')
            ->where('tipo_documento.siglas', 'M')
            ->where('parentesco.desc_parentesco', 'LIKE', '%HIJO%');
        })
        ->where('num_contrato', $poliza_antigua)
        ->where('certificados_terceros.status', 'ACTIVO')
        ->select([
            'certificados_terceros.*',
            'certificados.codigo_certificado'
        ])
        ->get();
        
        $this->info("Certificados encontrados: {$data_activa_poliza->count()}");

        $poliza_nueva = $this->ask('Por favor, ingrese el nuevo número de póliza renovada:');

        $fecha_ingreso = $this->ask('Por favor, ingrese la fecha de ingreso:');

        //* Obtenemos el id_contrato de la poliza renovada
        $contrato_id_nuevo = DB::connection('mysql_personas')->table('contrato')
        ->where('num_contrato', $poliza_nueva)
        ->value('id_contrato');

        $this->info("Cargando certificados de eleccion: {$opcion}");

        $eliminar_menores_flag = false;

        if ( $opcion === 'Carga de menores' ) {
            $eliminar_menores = $this->choice("Desea eliminar a los menores que no posean siniestros previamente cargados en esta póliza?", [
                'Sí',
                'No',
            ]);

            if ( $eliminar_menores === 'Sí' ) {
                $contar_menores_sin_siniestro = DB::connection('mysql_personas')->table('certificados_terceros')
                    ->join('certificados', 'certificados.id', 'certificados_terceros.certificado_id')
                    ->join('ridosm_general.terceros', 'terceros.id_terceros', 'certificados_terceros.tercero_id')
                    ->leftJoin('siniestros', 'siniestros.certificados_terceros_id', 'certificados_terceros.id')
                    ->where('certificados.contrato_id', $contrato_id_nuevo)
                    ->where('terceros.cod_documento', 6)
                    ->where('certificados_terceros.parentesco_id', 3)
                    ->whereNull('siniestros.id_siniestro')
                    ->count();

                $this->info("Se eliminarán {$contar_menores_sin_siniestro} menores que no poseen siniestros previamente cargados en la póliza nueva...");

                $confirmar_eliminacion = $this->confirm("Desea continuar con la eliminación de los menores que no poseen siniestros previamente cargados en la póliza nueva?");

                if ( !$confirmar_eliminacion ) {
                    $this->info("Ha sido cancelado el proceso de renovación.");
                    return;
                }

                $eliminar_menores_flag = true;
            }
        }

        //* Inicia transacción de derivación de certificados
        $bar = $this->output->createProgressBar($data_activa_poliza->count());
        $bar->start();

        $errores = [];

        //* Inicia transacción de derivación de certificados
        DB::transaction(function () use ($data_activa_poliza, $contrato_id_nuevo, $opcion, $bar, $fecha_ingreso, $poliza_nueva, $eliminar_menores_flag, &$errores, $poliza_antigua) {
            switch ( $opcion ) {
                case 'Carga de titulares':
                    foreach ($data_activa_poliza as $certificado) {
                        $this->createCertificadosTitulares($certificado, $contrato_id_nuevo, $fecha_ingreso);
                        $bar->advance();
                    }
                    break;
                case 'Carga completa':
                    foreach ($data_activa_poliza as $certificado) {
                        if ( $certificado->parentesco_id == 5 ) {
                            $this->createCertificadosTitulares($certificado, $contrato_id_nuevo, $fecha_ingreso);
                        } else {
                            $certificado_id = $this->obtenerCertificadoId($certificado->codigo_certificado, $contrato_id_nuevo);
                            if ( !$certificado_id ) {
                                $errores[] = [
                                    'codigo_certificado' => $certificado->codigo_certificado,
                                    'tercero_id' => $certificado->tercero_id,
                                    'parentesco_id' => $certificado->parentesco_id,
                                    'error' => 'Certificado/Titular no encontrado'
                                ];
                                $bar->advance();
                                continue;
                            }

                            $this->crearCertificadoTercero($certificado, $certificado_id, $fecha_ingreso);
                        }
                        $bar->advance();
                    }
                    break;
                default:
                    if ( $opcion === 'Carga de menores' && $eliminar_menores_flag ) {
                        DB::connection('mysql_personas')->table('certificados_terceros')
                            ->join('certificados', 'certificados.id', 'certificados_terceros.certificado_id')
                            ->join('ridosm_general.terceros', 'terceros.id_terceros', 'certificados_terceros.tercero_id')
                            ->leftJoin('siniestros', 'siniestros.certificados_terceros_id', 'certificados_terceros.id')
                            ->where('certificados.contrato_id', $contrato_id_nuevo)
                            ->where('terceros.cod_documento', 6)
                            ->where('certificados_terceros.parentesco_id', 3)
                            ->whereNull('siniestros.id_siniestro')
                            ->delete();
                    }

                    foreach ($data_activa_poliza as $certificado) {
                        $certificado_id = $this->obtenerCertificadoId($certificado->codigo_certificado, $contrato_id_nuevo);
                        if ( !$certificado_id ) {
                            $errores[] = [
                                'codigo_certificado' => $certificado->codigo_certificado,
                                'tercero_id' => $certificado->tercero_id,
                                'parentesco_id' => $certificado->parentesco_id,
                                'error' => 'Certificado/Titular no encontrado'
                            ];
                            $bar->advance();
                            continue;
                        }

                        $this->crearCertificadoTercero($certificado, $certificado_id, $fecha_ingreso);
                        $bar->advance();
                    }
                    break;
            }
        });

        $bar->finish();
        $this->newLine();
        
        $this->info("Certificados seleccionados por la opción {$opcion} derivados correctamente de la poliza {$poliza_antigua} a la poliza {$poliza_nueva}.");

        if ( !empty($errores) ) {
            $fileName = "errores_derivacion_{$poliza_antigua}_{$poliza_nueva}_" . now()->format('Ymd_His') . ".xlsx";
            Excel::store(new DerivacionErroresExport($errores), $fileName);
            $this->error("Se encontraron " . count($errores) . " errores.");
            $this->info("Archivo de errores generado en: " . storage_path("app/{$fileName}"));
        }

    }

    /**
     * Crea certificados de titulares
     * 
     * @param object $certificado
     * @param int $contrato_id_nuevo
     * @param string $fecha_ingreso
     */
    public function createCertificadosTitulares(object $certificado, int $contrato_id_nuevo, string $fecha_ingreso)
    {
        $certificado_id = DB::connection('mysql_personas')->table('certificados_terceros')
        ->insertGetId([
            'certificado_id' => $certificado->id,
            'contrato_id' => $contrato_id_nuevo,
            'tercero_id' => $certificado->tercero_id,
            'status' => 'ACTIVO',
        ]);

        $this->crearCertificadoTercero($certificado, $certificado_id, $fecha_ingreso);
    }

    /**
     * Crea certificados de beneficiarios
     * 
     * @param object $certificado
     * @param int $certificado_id
     * @param string $fecha_ingreso
     */
    public function crearCertificadoTercero(object $certificado, int $certificado_id, string $fecha_ingreso)
    {
        $verificar_existencia = DB::connection('mysql_personas')->table('certificados_terceros')
        ->where('certificado_id', $certificado_id)
        ->where('tercero_id', $certificado->tercero_id);

        if ( $verificar_existencia->exists() ) {
            $verificar_existencia->update([
                'parentesco_id' => $certificado->parentesco_id,
                'fecha_ingreso' => $fecha_ingreso,
                'estatus_ingreso' => 'RENOVADO',
                'status' => 'ACTIVO'
            ]);
        } else {
            DB::connection('mysql_personas')->table('certificados_terceros')
            ->insert([
                'certificado_id' => $certificado_id,
                'tercero_id' => $certificado->tercero_id,
                'parentesco_id' => $certificado->parentesco_id,
                'fecha_ingreso' => $fecha_ingreso,
                'status' => 'ACTIVO',
                'estatus_ingreso' => 'RENOVADO'
            ]);
        }

    }

    /**
     * Obtiene el id del certificado
     * 
     * @param string $codigo_certificado
     * @param int $contrato_id
     * @return int|null
     */
    public function obtenerCertificadoId(string $codigo_certificado, int $contrato_id): ?int
    {
        return DB::connection('mysql_personas')->table('certificados')
        ->where('codigo_certificado', $codigo_certificado)
        ->where('contrato_id', $contrato_id)
        ->value('id');
    }
}
