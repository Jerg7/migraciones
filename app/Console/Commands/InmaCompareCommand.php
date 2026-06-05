<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InmaService;

class InmaCompareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inma-compare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compara los datos de la API de INMA con la base de datos para mostrar qué registros nuevos se agregarían';

    /**
     * Execute the console command.
     */
    public function handle(InmaService $inma_service)
    {
        $this->info('Iniciando comparación de versiones de Inma...');
        $this->newLine();
        $this->info('Obteniendo detalles de INMA...');

        try {
            $versiones = $inma_service->getPreparedVersiones();
        } catch (\Exception $e) {
            $this->error('Error al obtener datos de INMA: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Versiones obtenidas...');
        $this->info('Comparando datos con la base de datos...');

        $diferencias = $inma_service->compareInmaData($versiones);

        $marcas_nuevas = $diferencias['marcas'];
        $modelos_nuevos = $diferencias['modelos'];
        $versiones_nuevas = $diferencias['versiones'];

        // Mostrar resumen
        $this->newLine();
        $this->info('Resumen de nuevos registros a insertar:');
        $this->table(
            ['Tipo', 'Cantidad'],
            [
                ['Marcas nuevas', count($marcas_nuevas)],
                ['Modelos nuevos', count($modelos_nuevos)],
                ['Versiones nuevas', count($versiones_nuevas)],
            ]
        );

        if (count($marcas_nuevas) > 0) {
            $this->newLine();
            $this->info('Marcas nuevas a registrar:');
            $this->table(['Código', 'Descripción'], $marcas_nuevas);
        }

        if (count($modelos_nuevos) > 0) {
            $this->newLine();
            $this->info('Modelos nuevos a registrar:');
            $this->table(['Cód. Marca', 'Cód. Modelo', 'Descripción'], $modelos_nuevos);
        }

        if (count($versiones_nuevas) > 0) {
            $this->newLine();
            $this->info('Versiones nuevas a registrar (mostrando las primeras 50):');
            $this->table(
                ['Cód. Marca', 'Cód. Modelo', 'CIVI', 'Año', 'Descripción'],
                array_slice($versiones_nuevas, 0, 50)
            );

            if (count($versiones_nuevas) > 50) {
                $this->comment('... y ' . (count($versiones_nuevas) - 50) . ' versiones nuevas más.');
            }
        }

        if (count($marcas_nuevas) === 0 && count($modelos_nuevos) === 0 && count($versiones_nuevas) === 0) {
            $this->info('La base de datos está completamente al día. No se insertará ningún registro.');
        }

        return self::SUCCESS;
    }
}
