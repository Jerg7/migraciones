<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\InmaController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InmaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inma-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $this->info('Iniciando importación de versiones de Inma...');
        $this->newLine();
        $this->info('Obteniendo detalles de INMA...');

        $_inmaController = app(InmaController::class);
        $marcas = $_inmaController->getMarcas()->getData();
        $modelos = $_inmaController->getModelos()->getData();
        $versiones = $_inmaController->getVersiones()->getData();

        $this->info('Versiones obtenidas...');

        foreach ( $versiones as $version ) {
            $marca = Arr::first($marcas, function ($marca) use ($version) {
                return $marca->marca_codigo == $version->marca_codigo;
            });

            $modelo = Arr::first($modelos, function ($modelo) use ($version) {
                return $modelo->marca_codigo == $version->marca_codigo && $modelo->modelo_codigo == $version->modelo_codigo;
            });

            $version->marca_descripcion = $marca->marca_descripcion;
            $version->modelo_descripcion = $modelo->modelo_descripcion;
        }

        $this->newLine();
        $this->info('Insertando marcas nuevas...');
        $bar = $this->output->createProgressBar(count($versiones));
        $bar->start();

        foreach ( $versiones as $version ) {
            $this->consultarMarcasExistentes($version->marca_codigo, $version->marca_descripcion);
            $bar->advance();
        }

        $bar->finish();

        $this->newLine();
        $this->info('Insertando modelos nuevas...');
        $bar = $this->output->createProgressBar(count($versiones));
        $bar->start();

        foreach ( $versiones as $version ) {
            $this->consultarModelosExistentes(
                $version->marca_codigo,
                $version->modelo_codigo,
                $version->modelo_descripcion
            );
            $bar->advance();
        }

        $bar->finish();

        $this->newLine();
        $this->info('Insertando versiones nuevas...');
        $bar = $this->output->createProgressBar(count($versiones));
        $bar->start();

        foreach ( $versiones as $version ) {
            $this->consultarVersionesExistentes(
                $version->marca_codigo,
                $version->modelo_codigo,
                $version->civi,
                $version->anio_fabricacion,
                $version->version_descripcion
            );
            $bar->advance();
        }

        $bar->finish();

        $this->newLine();
        $this->info('Nuevas versiones insertadas exitosamente.');
    }

    /**
     * Consulta si la marca existe en la base de datos
     * 
     * @param string $marca_codigo
     * @param string $marca_descripcion
     * @return void
     */
    public function consultarMarcasExistentes(string $marca_codigo, string $marca_descripcion)
    {
        DB::transaction(function () use ($marca_codigo, $marca_descripcion) {
            $consultar_marcas = DB::connection('mysql_automovil')->table('marcas')
            ->where('cod_marca', $marca_codigo)
            ->where('descripcion', 'LIKE', "%{$marca_descripcion}%")
            ->exists();

            if ( !$consultar_marcas ) {
                DB::connection('mysql_automovil')->table('marcas')
                ->insert([
                    'cod_marca' => $marca_codigo,
                    'descripcion' => $marca_descripcion,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    /**
     * Consulta si el modelo existe en la base de datos
     * 
     * @param string $marca_codigo
     * @param string $modelo_codigo
     * @param string $modelo_descripcion
     * @return void
     */
    public function consultarModelosExistentes(string $marca_codigo, string $modelo_codigo, string $modelo_descripcion)
    {
        DB::transaction(function () use ($marca_codigo, $modelo_codigo, $modelo_descripcion) {
            $consultar_modelos = DB::connection('mysql_automovil')->table('modelos')
            ->where('cod_marca', $marca_codigo)
            ->where('cod_modelo', $modelo_codigo)
            ->where('descripcion', 'LIKE', "%{$modelo_descripcion}%")
            ->exists();

            if ( !$consultar_modelos ) {
                DB::connection('mysql_automovil')->table('modelos')
                ->insert([
                    'cod_marca' => $marca_codigo,
                    'cod_modelo' => $modelo_codigo,
                    'descripcion' => $modelo_descripcion,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    /**
     * Consulta si la version existe en la base de datos
     * 
     * @param string $marca_codigo
     * @param string $modelo_codigo
     * @param string $civi
     * @param int $anio_vehiculo
     * @return void
     */
    public function consultarVersionesExistentes(
        string $marca_codigo, 
        string $modelo_codigo, 
        string $civi, 
        int $anio_vehiculo, 
        string $version_descripcion
    ) {
        DB::transaction(function () use ($marca_codigo, $modelo_codigo, $civi, $anio_vehiculo, $version_descripcion) {
            $consultar_versiones = DB::connection('mysql_automovil')->table('versiones')
            ->where('cod_marca', $marca_codigo)
            ->where('cod_modelo', $modelo_codigo)
            ->where('civi', $civi)
            ->where('anio_vehiculo', $anio_vehiculo)
            ->exists();

            if ( !$consultar_versiones ) {
                DB::connection('mysql_automovil')->table('versiones')
                ->insert([
                    'cod_marca' => $marca_codigo,
                    'cod_modelo' => $modelo_codigo,
                    'civi' => $civi,
                    'descripcion' => $version_descripcion,
                    'anio_vehiculo' => $anio_vehiculo,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }
}
