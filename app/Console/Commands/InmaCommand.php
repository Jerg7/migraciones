<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InmaService;
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
    protected $description = 'Importa marcas, modelos y versiones desde la API de INMA a la base de datos de forma optimizada';

    /**
     * Execute the console command.
     */
    public function handle(InmaService $_InmaService)
    {
        $this->info('Iniciando importación de versiones de Inma...');
        $this->newLine();
        $this->info('Obteniendo detalles de INMA...');

        try {
            $versiones = $_InmaService->getPreparedVersiones();
        } catch (\Exception $e) {
            $this->error('Error al obtener datos de INMA: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Versiones obtenidas...');
        $this->info('Comparando datos con la base de datos...');

        $diferencias = $_InmaService->compareInmaData($versiones);

        $marcas_nuevas = $diferencias['marcas'];
        $modelos_nuevos = $diferencias['modelos'];
        $versiones_nuevas = $diferencias['versiones'];

        if (count($marcas_nuevas) === 0 && count($modelos_nuevos) === 0 && count($versiones_nuevas) === 0) {
            $this->info('La base de datos está al día. No hay nuevos registros para importar.');
            return self::SUCCESS;
        }

        // Insertar marcas nuevas
        if (count($marcas_nuevas) > 0) {
            $this->newLine();
            $this->info('Insertando marcas nuevas...');
            $bar = $this->output->createProgressBar(count($marcas_nuevas));
            $bar->start();

            foreach ($marcas_nuevas as $marca) {
                DB::connection('mysql_automovil')->table('marcas')->insert([
                    'cod_marca' => $marca['cod_marca'],
                    'descripcion' => $marca['descripcion'],
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
                $bar->advance();
            }

            $bar->finish();
        }

        // Insertar modelos nuevos
        if (count($modelos_nuevos) > 0) {
            $this->newLine();
            $this->info('Insertando modelos nuevos...');
            $bar = $this->output->createProgressBar(count($modelos_nuevos));
            $bar->start();

            foreach ($modelos_nuevos as $modelo) {
                DB::connection('mysql_automovil')->table('modelos')->insert([
                    'cod_marca' => $modelo['cod_marca'],
                    'cod_modelo' => $modelo['cod_modelo'],
                    'descripcion' => $modelo['descripcion'],
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
                $bar->advance();
            }

            $bar->finish();
        }

        // Insertar versiones nuevas
        if (count($versiones_nuevas) > 0) {
            $this->newLine();
            $this->info('Insertando versiones nuevas...');
            $bar = $this->output->createProgressBar(count($versiones_nuevas));
            $bar->start();

            foreach ($versiones_nuevas as $version) {
                DB::connection('mysql_automovil')->table('versiones')->insert([
                    'cod_marca' => $version['cod_marca'],
                    'cod_modelo' => $version['cod_modelo'],
                    'civi' => $version['civi'],
                    'descripcion' => $version['descripcion'],
                    'anio_vehiculo' => $version['anio_vehiculo'],
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
                $bar->advance();
            }

            $bar->finish();
        }

        $this->newLine();
        $this->info('Nuevas marcas, modelos y versiones insertadas exitosamente.');

        return self::SUCCESS;
    }
}
