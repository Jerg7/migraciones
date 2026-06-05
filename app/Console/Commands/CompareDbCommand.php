<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class CompareDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:compare-db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compara los esquemas de bases de datos de QA y PROD, genera un reporte .txt y ofrece clonar las tablas de PROD en QA.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== COMPARADOR Y CLONADOR DE BASES DE DATOS (QA vs PROD) ===");

        // 1. Obtener y configurar conexiones
        $qa_connection = $this->getOrConfigureConnection('QA', 'mysql');
        $prod_connection = $this->getOrConfigureConnection('PROD', 'mysql_prod');

        // Validar conexiones antes de seguir
        if (!$this->testConnection($qa_connection) || !$this->testConnection($prod_connection)) {
            $this->error("No se pudo establecer conexión con una o ambas bases de datos. Cancelando operación.");
            return Command::FAILURE;
        }

        $qa_db_name = config("database.connections.{$qa_connection}.database");
        $prod_db_name = config("database.connections.{$prod_connection}.database");

        $this->info("Conectado con éxito a QA ({$qa_db_name}) y PROD ({$prod_db_name}).");
        $this->info("Iniciando la comparación de esquemas...");

        // 2. Comparación de esquemas
        $comparison_result = $this->compareDatabases($qa_connection, $prod_connection);

        // 3. Generación del reporte
        $report_path = $this->generateReport($comparison_result, $qa_connection, $prod_connection);
        $this->info("¡Comparación completada!");
        $this->info("Reporte detallado guardado en: {$report_path}");

        // Mostrar resumen rápido por consola
        $this->showConsoleSummary($comparison_result);

        // 4. Clonación de tablas de PROD a QA
        if ($this->confirm("¿Desea crear una copia de las tablas de PROD en QA?", false)) {
            $this->cloneDatabase($qa_connection, $prod_connection);
        }

        $this->info("Proceso finalizado con éxito.");
        return Command::SUCCESS;
    }

    private function getOrConfigureConnection(string $role, string $default): string
    {
        $name = $this->ask("Ingrese el nombre de la conexión para {$role}", $default);

        if (config("database.connections.{$name}") === null) {
            $this->warn("La conexión '{$name}' no está configurada en config/database.php.");
            if ($this->confirm("¿Desea configurar la conexión '{$name}' dinámicamente ahora?", true)) {
                $this->registerDynamicConnection($name);
            } else {
                $this->error("No se puede continuar sin la configuración de la conexión de {$role}.");
                exit(1);
            }
        }

        return $name;
    }

    private function registerDynamicConnection(string $name): void
    {
        $this->info("Configurando conexión dinámica para: {$name}");
        $host = $this->ask('Ingrese el host de la base de datos', '127.0.0.1');
        $port = $this->ask('Ingrese el puerto de la base de datos', '3306');
        $database = $this->ask('Ingrese el nombre de la base de datos');
        $username = $this->ask('Ingrese el usuario de la base de datos', 'root');
        $password = $this->secret('Ingrese la contraseña de la base de datos') ?? '';

        config(["database.connections.{$name}" => [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]]);

        // Purgar para aplicar la nueva configuración
        DB::purge($name);
    }

    private function testConnection(string $connection): bool
    {
        try {
            DB::connection($connection)->getPdo();
            return true;
        } catch (\Exception $e) {
            $this->error("Error al conectar a [{$connection}]: " . $e->getMessage());
            return false;
        }
    }

    private function compareDatabases(string $qa_conn, string $prod_conn): array
    {
        $qa_tables_raw = Schema::connection($qa_conn)->getTables();
        $prod_tables_raw = Schema::connection($prod_conn)->getTables();

        $qa_tables = array_column($qa_tables_raw, 'name');
        $prod_tables = array_column($prod_tables_raw, 'name');

        $tables_only_in_qa = array_diff($qa_tables, $prod_tables);
        $common_tables = array_intersect($qa_tables, $prod_tables);

        $columns_only_in_qa = [];
        $differing_columns = [];
        $indexes_only_in_qa = [];
        $foreign_keys_only_in_qa = [];

        foreach ($common_tables as $table) {
            // Comparar columnas
            $qa_columns_raw = Schema::connection($qa_conn)->getColumns($table);
            $prod_columns_raw = Schema::connection($prod_conn)->getColumns($table);

            $qa_columns = [];
            foreach ($qa_columns_raw as $col) {
                $qa_columns[$col['name']] = $col;
            }

            $prod_columns = [];
            foreach ($prod_columns_raw as $col) {
                $prod_columns[$col['name']] = $col;
            }

            // Columnas sólo en QA
            $diff_cols = array_diff(array_keys($qa_columns), array_keys($prod_columns));
            if (!empty($diff_cols)) {
                $columns_only_in_qa[$table] = $diff_cols;
            }

            // Comparar definiciones de columnas en común
            foreach (array_intersect(array_keys($qa_columns), array_keys($prod_columns)) as $col_name) {
                $qa_col = $qa_columns[$col_name];
                $prod_col = $prod_columns[$col_name];

                $differences = [];
                if ($qa_col['type'] !== $prod_col['type']) {
                    $differences[] = "Tipo: QA='{$qa_col['type']}', PROD='{$prod_col['type']}'";
                }
                if ($qa_col['nullable'] !== $prod_col['nullable']) {
                    $qa_null = $qa_col['nullable'] ? 'NULL' : 'NOT NULL';
                    $prod_null = $prod_col['nullable'] ? 'NULL' : 'NOT NULL';
                    $differences[] = "Nulabilidad: QA='{$qa_null}', PROD='{$prod_null}'";
                }
                if ($qa_col['default'] !== $prod_col['default']) {
                    $differences[] = "Por defecto: QA='{$qa_col['default']}', PROD='{$prod_col['default']}'";
                }

                if (!empty($differences)) {
                    $differing_columns[$table][$col_name] = $differences;
                }
            }

            // Comparar índices
            $qa_indexes_raw = Schema::connection($qa_conn)->getIndexes($table);
            $prod_indexes_raw = Schema::connection($prod_conn)->getIndexes($table);

            $qa_indexes = [];
            foreach ($qa_indexes_raw as $idx) {
                $qa_indexes[$idx['name']] = $idx;
            }

            $prod_indexes = [];
            foreach ($prod_indexes_raw as $idx) {
                $prod_indexes[$idx['name']] = $idx;
            }

            $diff_idx = array_diff(array_keys($qa_indexes), array_keys($prod_indexes));
            if (!empty($diff_idx)) {
                $indexes_only_in_qa[$table] = array_intersect_key($qa_indexes, array_flip($diff_idx));
            }

            // Comparar llaves foráneas
            $qa_fks_raw = Schema::connection($qa_conn)->getForeignKeys($table);
            $prod_fks_raw = Schema::connection($prod_conn)->getForeignKeys($table);

            $qa_fks = [];
            foreach ($qa_fks_raw as $fk) {
                $qa_fks[$fk['name']] = $fk;
            }

            $prod_fks = [];
            foreach ($prod_fks_raw as $fk) {
                $prod_fks[$fk['name']] = $fk;
            }

            $diff_fks = array_diff(array_keys($qa_fks), array_keys($prod_fks));
            if (!empty($diff_fks)) {
                $foreign_keys_only_in_qa[$table] = array_intersect_key($qa_fks, array_flip($diff_fks));
            }
        }

        return [
            'tables_only_in_qa' => $tables_only_in_qa,
            'columns_only_in_qa' => $columns_only_in_qa,
            'differing_columns' => $differing_columns,
            'indexes_only_in_qa' => $indexes_only_in_qa,
            'foreign_keys_only_in_qa' => $foreign_keys_only_in_qa,
        ];
    }

    private function generateReport(array $comparison_data, string $qa_conn, string $prod_conn): string
    {
        $qa_db = config("database.connections.{$qa_conn}.database");
        $prod_db = config("database.connections.{$prod_conn}.database");

        $content = "========================================================================\n";
        $content .= "REPORTE DE COMPARACIÓN DE BASES DE DATOS (QA vs PROD)\n";
        $content .= "========================================================================\n";
        $content .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Conexión QA: {$qa_conn} (Base de datos: {$qa_db})\n";
        $content .= "Conexión PROD: {$prod_conn} (Base de datos: {$prod_db})\n";
        $content .= "========================================================================\n\n";

        // 1. Tablas
        $content .= "1. TABLAS QUE EXISTEN EN QA PERO NO EN PROD:\n";
        $content .= "------------------------------------------------------------------------\n";
        if (empty($comparison_data['tables_only_in_qa'])) {
            $content .= "(Ninguna tabla diferida)\n";
        } else {
            foreach ($comparison_data['tables_only_in_qa'] as $table) {
                $content .= "- {$table}\n";
            }
        }
        $content .= "\n";

        // 2. Columnas
        $content .= "2. COLUMNAS QUE EXISTEN EN QA PERO NO EN PROD (EN TABLAS COMUNES):\n";
        $content .= "------------------------------------------------------------------------\n";
        if (empty($comparison_data['columns_only_in_qa'])) {
            $content .= "(Ninguna columna diferida)\n";
        } else {
            foreach ($comparison_data['columns_only_in_qa'] as $table => $columns) {
                $content .= "[Tabla: {$table}]\n";
                foreach ($columns as $col) {
                    $content .= "  - {$col}\n";
                }
            }
        }
        $content .= "\n";

        // 3. Diferencias estructurales
        $content .= "3. COLUMNAS CON DIFERENCIAS ESTRUCTURALES ENTRE QA Y PROD:\n";
        $content .= "------------------------------------------------------------------------\n";
        if (empty($comparison_data['differing_columns'])) {
            $content .= "(Ninguna columna con diferencias estructurales)\n";
        } else {
            foreach ($comparison_data['differing_columns'] as $table => $columns) {
                $content .= "[Tabla: {$table}]\n";
                foreach ($columns as $col_name => $diffs) {
                    $content .= "  [Columna: {$col_name}]\n";
                    foreach ($diffs as $diff) {
                        $content .= "    * {$diff}\n";
                    }
                }
            }
        }
        $content .= "\n";

        // 4. Índices
        $content .= "4. ÍNDICES QUE EXISTEN EN QA PERO NO EN PROD:\n";
        $content .= "------------------------------------------------------------------------\n";
        if (empty($comparison_data['indexes_only_in_qa'])) {
            $content .= "(Ningún índice diferido)\n";
        } else {
            foreach ($comparison_data['indexes_only_in_qa'] as $table => $indexes) {
                $content .= "[Tabla: {$table}]\n";
                foreach ($indexes as $idx_name => $idx) {
                    $cols = implode(', ', $idx['columns']);
                    $unique = $idx['unique'] ? 'Único' : 'No Único';
                    $primary = $idx['primary'] ? 'Primaria' : '';
                    $type = $primary ?: $unique;
                    $content .= "  - {$idx_name} ({$type}, Columnas: [{$cols}])\n";
                }
            }
        }
        $content .= "\n";

        // 5. Llaves Foráneas
        $content .= "5. LLAVES FORÁNEAS QUE EXISTEN EN QA PERO NO EN PROD:\n";
        $content .= "------------------------------------------------------------------------\n";
        if (empty($comparison_data['foreign_keys_only_in_qa'])) {
            $content .= "(Ninguna llave foránea diferida)\n";
        } else {
            foreach ($comparison_data['foreign_keys_only_in_qa'] as $table => $fks) {
                $content .= "[Tabla: {$table}]\n";
                foreach ($fks as $fk_name => $fk) {
                    $cols = implode(', ', $fk['columns']);
                    $ref_cols = implode(', ', $fk['foreign_columns']);
                    $content .= "  - {$fk_name} (Columna: [{$cols}] -> {$fk['foreign_table']}.[{$ref_cols}], On Update: {$fk['on_update']}, On Delete: {$fk['on_delete']})\n";
                }
            }
        }

        $filename = 'comparacion_db_' . date('Ymd_His') . '.txt';
        $filepath = base_path($filename);

        File::put($filepath, $content);

        return $filepath;
    }

    private function showConsoleSummary(array $comparison_data): void
    {
        $this->line('');
        $this->info("--- RESUMEN DE DIFERENCIAS DETECTADAS EN QA ---");

        $count_tables = count($comparison_data['tables_only_in_qa']);
        $count_columns = count($comparison_data['columns_only_in_qa']);
        $count_diff_cols = count($comparison_data['differing_columns']);
        $count_indexes = count($comparison_data['indexes_only_in_qa']);
        $count_fks = count($comparison_data['foreign_keys_only_in_qa']);

        $this->line("• Tablas exclusivas de QA: {$count_tables}");
        $this->line("• Tablas con columnas nuevas en QA: {$count_columns}");
        $this->line("• Tablas con columnas modificadas en QA: {$count_diff_cols}");
        $this->line("• Tablas con índices nuevos en QA: {$count_indexes}");
        $this->line("• Tablas con llaves foráneas nuevas en QA: {$count_fks}");
        $this->line('');
    }

    private function cloneDatabase(string $qa_conn, string $prod_conn): void
    {
        $this->info("=== CLONACIÓN DE TABLAS DE PROD A QA ===");

        $opcion = $this->choice(
            '¿Dónde desea aplicar el prefijo?',
            [
                '1' => 'Prefijo en el nombre de las tablas (ej: prod_usuarios)',
                '2' => 'Prefijo en el nombre de la base de datos (ej: crear copia_base_datos)',
            ],
            '1'
        );

        $prefix = $this->ask('Ingrese el prefijo (por defecto: prod_)', 'prod_');
        // Sanitizar prefijo para evitar caracteres inválidos en bases de datos o tablas
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);

        if (empty($prefix)) {
            $this->error("El prefijo no puede estar vacío.");
            return;
        }

        // Obtener tablas de PROD
        $prod_tables_raw = Schema::connection($prod_conn)->getTables();
        $prod_tables = array_column($prod_tables_raw, 'name');

        if (empty($prod_tables)) {
            $this->warn("No se encontraron tablas en la base de datos de PROD para clonar.");
            return;
        }

        $qa_host = config("database.connections.{$qa_conn}.host");
        $prod_host = config("database.connections.{$prod_conn}.host");
        $qa_port = config("database.connections.{$qa_conn}.port");
        $prod_port = config("database.connections.{$prod_conn}.port");

        $same_server = ($qa_host === $prod_host && $qa_port === $prod_port);

        $prod_db_original = config("database.connections.{$prod_conn}.database");
        $qa_db_original = config("database.connections.{$qa_conn}.database");

        $destination_db = $qa_db_original;

        if ($opcion === 'Prefijo en el nombre de la base de datos (ej: crear copia_base_datos)') {
            $destination_db = $prefix . $prod_db_original;
            $this->info("Se creará/usará la base de datos de destino: '{$destination_db}' en el servidor QA.");

            try {
                // Crear base de datos en QA si no existe
                DB::connection($qa_conn)->statement("CREATE DATABASE IF NOT EXISTS `{$destination_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Conectar temporalmente a la nueva base de datos
                config(["database.connections.{$qa_conn}.database" => $destination_db]);
                DB::purge($qa_conn);
                DB::reconnect($qa_conn);
                
                $this->info("Conexión de QA redirigida temporalmente a: {$destination_db}");
            } catch (\Exception $e) {
                $this->error("Error al crear o conectar a la base de datos '{$destination_db}': " . $e->getMessage());
                return;
            }
        }

        $this->warn("¡ATENCIÓN! Se procederá a copiar las tablas estructurales y datos.");
        $this->warn("Cualquier tabla existente con el mismo nombre y prefijo en el destino ({$destination_db}) será ELIMINADA.");
        if (!$this->confirm("¿Está seguro de que desea continuar con la clonación?", false)) {
            $this->info("Clonación cancelada.");
            return;
        }

        // Desactivar FK
        DB::connection($qa_conn)->statement('SET FOREIGN_KEY_CHECKS=0;');

        $bar = $this->output->createProgressBar(count($prod_tables));
        $bar->start();

        foreach ($prod_tables as $table) {
            // Definir nombre de tabla de destino
            $destination_table = ($opcion === 'Prefijo en el nombre de las tablas (ej: prod_usuarios)')
                ? $prefix . $table
                : $table;

            try {
                // 1. Eliminar tabla si ya existe
                DB::connection($qa_conn)->statement("DROP TABLE IF EXISTS `{$destination_table}`");

                // 2. Crear estructura de la tabla
                if ($same_server && $opcion === 'Prefijo en el nombre de las tablas (ej: prod_usuarios)') {
                    // Si están en el mismo servidor y es prefijo de tabla, podemos usar LIKE
                    DB::connection($qa_conn)->statement("CREATE TABLE `{$destination_db}`.`{$destination_table}` LIKE `{$prod_db_original}`.`{$table}`");
                } else {
                    // Si están en diferentes servidores o es nueva BD, usamos SHOW CREATE TABLE
                    $create_sql_raw = DB::connection($prod_conn)->select("SHOW CREATE TABLE `{$table}`");
                    if (empty($create_sql_raw)) {
                        continue;
                    }
                    $create_sql = $create_sql_raw[0]->{'Create Table'} ?? $create_sql_raw[0]->{'Create View'} ?? null;

                    if ($create_sql) {
                        // Limpiar constraints para evitar conflictos de nombres de FK y dependencias circulares durante creación
                        $lines = explode("\n", $create_sql);
                        $clean_lines = [];
                        foreach ($lines as $line) {
                            if (preg_match('/^\s*CONSTRAINT\s+/i', $line)) {
                                continue;
                            }
                            $clean_lines[] = $line;
                        }
                        $create_sql = implode("\n", $clean_lines);
                        $create_sql = preg_replace('/,\s*\)\s*ENGINE/i', "\n)", $create_sql);

                        // Renombrar la tabla en la consulta
                        $create_sql = preg_replace('/CREATE TABLE `'.$table.'`/', "CREATE TABLE `{$destination_table}`", $create_sql, 1);

                        DB::connection($qa_conn)->statement($create_sql);
                    }
                }

                // 3. Copiar datos usando cursor para eficiencia en memoria
                $rows = DB::connection($prod_conn)->table($table)->cursor();
                $chunk = [];
                $chunk_size = 1000;

                foreach ($rows as $row) {
                    $chunk[] = (array) $row;
                    if (count($chunk) >= $chunk_size) {
                        DB::connection($qa_conn)->table($destination_table)->insert($chunk);
                        $chunk = [];
                    }
                }

                if (!empty($chunk)) {
                    DB::connection($qa_conn)->table($destination_table)->insert($chunk);
                }

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error clonando la tabla {$table}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Reactivar FK
        DB::connection($qa_conn)->statement('SET FOREIGN_KEY_CHECKS=1;');

        // Si cambiamos la base de datos de QA, restaurarla al final por seguridad
        if ($opcion === 'Prefijo en el nombre de la base de datos (ej: crear copia_base_datos)') {
            config(["database.connections.{$qa_conn}.database" => $qa_db_original]);
            DB::purge($qa_conn);
            DB::reconnect($qa_conn);
        }

        $this->info("Clonación finalizada.");
    }
}
