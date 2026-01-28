<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloneBaseDatosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clone-base-datos-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clona tablas, datos y RECONSTRUYE las relaciones entre dos BDs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $origen = $this->ask('Ingrese el nombre de la base de datos de origen');
        $destino = $this->ask('Ingrese el nombre de la base de datos de destino');
        $prefijo = $this->ask('Ingrese el prefijo de la base de datos de destino');

        $this->info("🚀 Arrancando la clonación de '{$origen}' a '{$destino}'...");

        // Desactivamos los frenos de seguridad (Foreign Keys)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            //* COPIAR TABLAS Y DATOS 
            
            // Obtenemos solo las tablas (no vistas, etc)
            $tablas = DB::select("
                SELECT TABLE_NAME 
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_TYPE = 'BASE TABLE'", 
            [$origen]);

            $bar = $this->output->createProgressBar(count($tablas));
            $this->info("Copiando Tablas y Datos...");
            $bar->start();

            foreach ($tablas as $tabla) {
                $nombre_tabla = $tabla->TABLE_NAME;

                // Borramos si existe en el destino (para empezar limpio)
                DB::statement("DROP TABLE IF EXISTS `{$destino}`.`{$prefijo}_{$nombre_tabla}`");

                // Creamos la estructura (sin relaciones todavía)
                DB::statement("CREATE TABLE `{$destino}`.`{$prefijo}_{$nombre_tabla}` LIKE `{$origen}`.`{$nombre_tabla}`");

                // Inyectamos la data
                DB::statement("INSERT INTO `{$destino}`.`{$prefijo}_{$nombre_tabla}` SELECT * FROM `{$origen}`.`{$nombre_tabla}`");

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Tablas y datos copiados. Ahora vamos con las relaciones...");

            //* RECONSTRUIR RELACIONES

            // Consultamos el information_schema de MySQL para ver las FK de la base vieja
            $relaciones = DB::select("
                SELECT 
                    TABLE_NAME, 
                    COLUMN_NAME, 
                    CONSTRAINT_NAME, 
                    REFERENCED_TABLE_SCHEMA, 
                    REFERENCED_TABLE_NAME, 
                    REFERENCED_COLUMN_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$origen]);

            $barRel = $this->output->createProgressBar(count($relaciones));
            $this->info("Construyendo Relaciones...");
            $barRel->start();

            foreach ($relaciones as $fk) {
                // Si la FK apuntaba a la base vieja, la redirigimos a la nueva.
                // Si apuntaba a cualquier otro lado (incluso a la destino original), la dejamos quieta.
                $referenced_table_name = ($fk->REFERENCED_TABLE_SCHEMA == $origen) ? "{$prefijo}_{$fk->REFERENCED_TABLE_NAME}" : $fk->REFERENCED_TABLE_NAME;

                try {
                    $sql = "ALTER TABLE `{$destino}`.`{$prefijo}_{$fk->TABLE_NAME}` 
                            ADD CONSTRAINT `{$fk->CONSTRAINT_NAME}_{$prefijo}` 
                            FOREIGN KEY (`{$fk->COLUMN_NAME}`) 
                            REFERENCES `{$fk->REFERENCED_TABLE_SCHEMA}`.`{$referenced_table_name}` (`{$fk->REFERENCED_COLUMN_NAME}`)
                            ON DELETE RESTRICT ON UPDATE CASCADE";
                    
                    DB::statement($sql);
                } catch (\Exception $e) {
                    $this->newLine();
                    // Usamos warn en vez de error para que no detenga el script visualmente
                    $this->warn("Salto la FK {$fk->CONSTRAINT_NAME}: Probablemente inconsistencia de datos (IDs huérfanos).");
                }
                
                $barRel->advance();
            }

            $barRel->finish();
            $this->newLine(2);
            $this->info("Relaciones construidas.");

            $this->info("Clonando y Corrigiendo Vistas...");
            
            // Convertimos esto en un array simple para buscar rapido
            $lista_tablas_reales = array_column(array_map(function($t) { return (array)$t; }, $tablas), 'TABLE_NAME');

            $vistas = DB::select("
                SELECT TABLE_NAME 
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_TYPE = 'VIEW'", 
            [$origen]);

            $barView = $this->output->createProgressBar(count($vistas));
            $barView->start();

            foreach ($vistas as $vista) {
                $nombre_vista = $vista->TABLE_NAME;
                
                try {
                    $data_view = DB::select("SHOW CREATE VIEW `{$origen}`.`{$nombre_vista}`");
                    
                    if (empty($data_view)) { continue; }

                    $create_view_sql = $data_view[0]->{'Create View'};
                    
                    // Eliminamos el DEFINER
                    $nuevo_sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', '', $create_view_sql);
                                        
                    // Regex para encontrar patrones: `base_datos_origen`.`tabla`
                    // Función callback para decidir si agregamos prefijo o no
                    $nuevo_sql = preg_replace_callback(
                        "/`{$origen}`\.`([^`]+)`/", // Busca: `base_datos_origen`.`tabla`
                        function ($matches) use ($destino, $prefijo, $lista_tablas_reales) {
                            $nombre_tabla_clonada = $matches[1]; // El nombre de la tabla
                            
                            // Si el nombre está en la lista de tablas reales, le pegamos el prefijo
                            if (in_array($nombre_tabla_clonada, $lista_tablas_reales)) {
                                return "`{$destino}`.`{$prefijo}_{$nombre_tabla_clonada}`";
                            }
                            
                            // Si no es tabla (es otra vista), solo cambiamos la BD
                            return "`{$destino}`.`{$nombre_tabla_clonada}`";
                        },
                        $nuevo_sql
                    );

                    // Corrección final del nombre de la vista propia
                    DB::statement("DROP VIEW IF EXISTS `{$destino}`.`{$nombre_vista}`");
                    DB::statement($nuevo_sql);
                    
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("Error en vista {$nombre_vista}: " . $e->getMessage());
                }

                $barView->advance();
            }
            $barView->finish();

            $this->newLine(2);
            $this->info("Vistas clonadas.");

        } catch (\Exception $e) {
            $this->error("Explotó: " . $e->getMessage());
        } finally {
            // Volvemos a activar la seguridad pase lo que pase
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->newLine(2);
        $this->info("Clonación terminada exitosamente.");
    }
}
