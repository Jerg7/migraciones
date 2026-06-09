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
    protected $signature = 'app:compare-db {--databases= : Lista de bases de datos a comparar/clonar, separadas por coma}';

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
            return self::FAILURE;
        }

        $qa_db_default = config("database.connections.{$qa_connection}.database");
        $prod_db_default = config("database.connections.{$prod_connection}.database");

        // Determinar si se indicaron bases de datos específicas
        $databases_input = $this->option('databases');
        if (empty($databases_input)) {
            if ($this->confirm('¿Desea especificar las bases de datos a comparar y clonar?', false)) {
                $databases_input = $this->ask('Ingrese los nombres de las bases de datos separadas por comas (ej: db1,db2)');
            }
        }

        $databases = [];
        if (!empty($databases_input)) {
            $databases = array_filter(array_map('trim', explode(',', $databases_input)));
        }

        if (empty($databases)) {
            $this->info("Conectado con éxito a QA ({$qa_db_default}) y PROD ({$prod_db_default}).");
            $this->info("Iniciando la comparación de toda la base de datos...");
            $this->processDatabase($qa_connection, $prod_connection, $qa_db_default, $prod_db_default);
        } else {
            $this->info("Procesando las siguientes bases de datos: " . implode(', ', $databases));
            foreach ($databases as $db_name) {
                $this->info("\n------------------------------------------------------------");
                $this->info("Procesando Base de Datos: {$db_name}");
                $this->info("------------------------------------------------------------");

                // Asegurar conexión a las bases de datos por defecto para hacer la verificación de existencia
                config(["database.connections.{$qa_connection}.database" => $qa_db_default]);
                config(["database.connections.{$prod_connection}.database" => $prod_db_default]);
                DB::purge($qa_connection);
                DB::purge($prod_connection);

                try {
                    $prod_db_exists = !empty(DB::connection($prod_connection)->select(
                        "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
                        [$db_name]
                    ));
                } catch (\Exception $e) {
                    $this->error("Error al verificar existencia de base de datos en PROD: " . $e->getMessage());
                    continue;
                }

                if (!$prod_db_exists) {
                    $this->error("La base de datos '{$db_name}' no existe en el servidor de PROD. Saltando...");
                    continue;
                }

                try {
                    $qa_db_exists = !empty(DB::connection($qa_connection)->select(
                        "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
                        [$db_name]
                    ));
                } catch (\Exception $e) {
                    $this->error("Error al verificar existencia de base de datos en QA: " . $e->getMessage());
                    continue;
                }

                if (!$qa_db_exists) {
                    $this->warn("La base de datos '{$db_name}' no existe en el servidor de QA.");
                    if ($this->confirm("¿Desea crear la base de datos '{$db_name}' en QA para proceder?", true)) {
                        try {
                            DB::connection($qa_connection)->statement(
                                "CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                            );
                            $this->info("Base de datos '{$db_name}' creada con éxito en QA.");
                        } catch (\Exception $e) {
                            $this->error("No se pudo crear la base de datos '{$db_name}' en QA: " . $e->getMessage());
                            continue;
                        }
                    } else {
                        $this->warn("Saltando base de datos '{$db_name}'...");
                        continue;
                    }
                }

                // Configurar dinámicamente el nombre de la base de datos para ambas conexiones
                config(["database.connections.{$qa_connection}.database" => $db_name]);
                config(["database.connections.{$prod_connection}.database" => $db_name]);

                DB::purge($qa_connection);
                DB::purge($prod_connection);

                $this->processDatabase($qa_connection, $prod_connection, $db_name, $db_name);
            }
        }

        // Restaurar bases de datos por defecto al finalizar
        config(["database.connections.{$qa_connection}.database" => $qa_db_default]);
        config(["database.connections.{$prod_connection}.database" => $prod_db_default]);
        DB::purge($qa_connection);
        DB::purge($prod_connection);

        $this->info("\nProceso finalizado con éxito.");
        return self::SUCCESS;
    }

    private function processDatabase(string $qa_connection, string $prod_connection, string $qa_db_name, string $prod_db_name): void
    {
        // 2. Comparación de esquemas
        $comparison_result = $this->compareDatabases($qa_connection, $prod_connection);

        // 3. Generación del reporte
        $report_path = $this->generateReport($comparison_result, $qa_connection, $prod_connection);
        $this->info("¡Comparación completada!");
        $this->info("Reporte detallado guardado en: {$report_path}");

        // Mostrar resumen rápido por consola
        $this->showConsoleSummary($comparison_result);

        // 4. Clonación de tablas de PROD a QA
        if ($this->confirm("¿Desea crear una copia de la base de datos '{$prod_db_name}' de PROD en QA?", false)) {
            $this->cloneDatabase($qa_connection, $prod_connection);
        }
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
        $qa_db = config("database.connections.{$qa_conn}.database");
        $prod_db = config("database.connections.{$prod_conn}.database");

        $qa_tables_raw = Schema::connection($qa_conn)->getTables();
        $prod_tables_raw = Schema::connection($prod_conn)->getTables();

        $qa_tables = [];
        foreach ($qa_tables_raw as $table) {
            if (isset($table['schema']) && strtolower($table['schema']) === strtolower($qa_db)) {
                $qa_tables[] = $table['name'];
            }
        }

        $prod_tables = [];
        foreach ($prod_tables_raw as $table) {
            if (isset($table['schema']) && strtolower($table['schema']) === strtolower($prod_db)) {
                $prod_tables[] = $table['name'];
            }
        }

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

        $filename = 'comparacion_db_' . $qa_db . '_' . date('Ymd_His') . '.txt';
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

        $prod_db_original = config("database.connections.{$prod_conn}.database");
        $qa_db_original = config("database.connections.{$qa_conn}.database");

        // Obtener tablas de PROD
        $prod_tables_raw = Schema::connection($prod_conn)->getTables();
        $prod_tables = [];
        foreach ($prod_tables_raw as $table) {
            if (isset($table['schema']) && strtolower($table['schema']) === strtolower($prod_db_original)) {
                $prod_tables[] = $table['name'];
            }
        }

        if (empty($prod_tables)) {
            $this->warn("No se encontraron tablas en la base de datos de PROD para clonar.");
            return;
        }

        $qa_host = config("database.connections.{$qa_conn}.host");
        $prod_host = config("database.connections.{$prod_conn}.host");
        $qa_port = config("database.connections.{$qa_conn}.port");
        $prod_port = config("database.connections.{$prod_conn}.port");

        $same_server = ($qa_host === $prod_host && $qa_port === $prod_port);

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

        $this->warn("¡ATENCIÓN! Se procederá a clonar la base de datos.");
        $this->warn("Cualquier tabla o vista existente con el mismo nombre y prefijo en el destino ({$destination_db}) será ELIMINADA.");

        $tipo_clonacion = $this->choice(
            '¿Qué datos desea copiar?',
            [
                '1' => 'Solo estructura (sin datos) - Recomendado para bases de datos grandes',
                '2' => 'Estructura y muestra de datos (máximo 1000 registros por tabla)',
                '3' => 'Estructura y todos los datos (puede tardar si están en distintos servidores)',
            ],
            '1'
        );

        $copiar_datos = ($tipo_clonacion !== 'Solo estructura (sin datos) - Recomendado para bases de datos grandes');
        $limite_datos = ($tipo_clonacion === 'Estructura y muestra de datos (máximo 1000 registros por tabla)') ? 1000 : null;

        if (!$this->confirm("¿Está seguro de que desea continuar con la clonación?", false)) {
            $this->info("Clonación cancelada.");
            return;
        }

        // Desactivar FK
        DB::connection($qa_conn)->statement('SET FOREIGN_KEY_CHECKS=0;');

        $bar = $this->output->createProgressBar(count($prod_tables));
        $bar->start();

        $foreign_keys = [];

        foreach ($prod_tables as $table) {
            // Definir nombre de tabla de destino
            $destination_table = ($opcion === 'Prefijo en el nombre de las tablas (ej: prod_usuarios)')
                ? $prefix . $table
                : $table;

            try {
                // 1. Obtener estructura de la tabla (obviando vistas por completo)
                $create_sql_raw = DB::connection($prod_conn)->select("SHOW CREATE TABLE `{$table}`");
                if (empty($create_sql_raw)) {
                    $bar->advance();
                    continue;
                }

                $is_view = isset($create_sql_raw[0]->{'Create View'});
                if ($is_view) {
                    // Obviar vistas por completo
                    $bar->advance();
                    continue;
                }

                $create_sql = $create_sql_raw[0]->{'Create Table'} ?? null;
                if (!$create_sql) {
                    $bar->advance();
                    continue;
                }

                // 2. Eliminar tabla si ya existe
                DB::connection($qa_conn)->statement("DROP VIEW IF EXISTS `{$destination_table}`");
                DB::connection($qa_conn)->statement("DROP TABLE IF EXISTS `{$destination_table}`");

                // Extraer y limpiar las foreign keys del DDL de creación
                $lines = explode("\n", $create_sql);
                $clean_lines = [];
                foreach ($lines as $line) {
                    if (preg_match('/^\s*CONSTRAINT\s+`([^`]+)`\s+FOREIGN\s+KEY\s*\(([^)]+)\)\s*REFERENCES\s+`([^`]+)`\s*\(([^)]+)\)(.*)$/i', $line, $matches)) {
                        $fk_name = $matches[1];
                        $fk_cols = $matches[2];
                        $ref_table = $matches[3];
                        $ref_cols = $matches[4];
                        $fk_options = $matches[5];

                        // Renombrar FK y tabla de destino si es necesario
                        $destination_fk_name = ($opcion === 'Prefijo en el nombre de las tablas (ej: prod_usuarios)') ? $prefix . $fk_name : $fk_name;
                        
                        // Si excede los 64 caracteres (límite de MySQL), lo truncamos y le añadimos un hash único
                        if (strlen($destination_fk_name) > 64) {
                            $hash = substr(md5($fk_name), 0, 8);
                            $destination_fk_name = substr($destination_fk_name, 0, 55) . '_' . $hash;
                        }

                        $destination_ref_table = ($opcion === 'Prefijo en el nombre de las tablas (ej: prod_usuarios)') ? $prefix . $ref_table : $ref_table;

                        $fk_sql = "ALTER TABLE `{$destination_table}` ADD CONSTRAINT `{$destination_fk_name}` FOREIGN KEY ({$fk_cols}) REFERENCES `{$destination_ref_table}` ({$ref_cols}){$fk_options}";
                        
                        // Limpiar comas finales de la definición de FK
                        $fk_sql = rtrim($fk_sql, ',');

                        $foreign_keys[] = [
                            'table' => $destination_table,
                            'sql' => $fk_sql
                        ];
                        continue;
                    }
                    $clean_lines[] = $line;
                }

                // Si eliminamos constraints, la última línea de definición podría quedar con una coma al final.
                // La última línea de definición es la anterior a la de cierre (ej: ") ENGINE=InnoDB...")
                $total_lines = count($clean_lines);
                if ($total_lines >= 3) {
                    $last_def_index = $total_lines - 2;
                    $clean_lines[$last_def_index] = rtrim($clean_lines[$last_def_index]);
                    if (str_ends_with($clean_lines[$last_def_index], ',')) {
                        $clean_lines[$last_def_index] = substr($clean_lines[$last_def_index], 0, -1);
                    }
                }

                $create_sql = implode("\n", $clean_lines);

                // Renombrar la tabla en la consulta
                $create_sql = preg_replace('/CREATE TABLE `'.$table.'`/', "CREATE TABLE `{$destination_table}`", $create_sql, 1);

                DB::connection($qa_conn)->statement($create_sql);

                // 3. Copiar datos usando cursor para eficiencia en memoria o copia directa (solo si se solicita)
                if ($copiar_datos) {
                    $copiado_exitoso = false;

                    // Si están en el mismo servidor y se requiere copiar todo, intentar copia directa a nivel de base de datos
                    if ($same_server && $limite_datos === null) {
                        try {
                            DB::connection($qa_conn)->statement("INSERT INTO `{$destination_table}` SELECT * FROM `{$prod_db_original}`.`{$table}`");
                            $copiado_exitoso = true;
                        } catch (\Exception $e) {
                            // En caso de fallar por permisos, continúa con el fallback del cursor en PHP
                        }
                    }

                    if (!$copiado_exitoso) {
                        $query = DB::connection($prod_conn)->table($table);
                        if ($limite_datos !== null) {
                            $query->limit($limite_datos);
                        }
                        $rows = $query->cursor();
                        
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
                    }
                }

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error clonando la tabla {$table}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // 4. Aplicar llaves foráneas recolectadas
        if (!empty($foreign_keys)) {
            $this->info("Aplicando llaves foráneas en la base de datos de QA...");
            $fk_bar = $this->output->createProgressBar(count($foreign_keys));
            $fk_bar->start();
            $fk_errors = [];

            foreach ($foreign_keys as $fk) {
                try {
                    DB::connection($qa_conn)->statement($fk['sql']);
                } catch (\Exception $e) {
                    $fk_errors[] = "Error aplicando FK en la tabla {$fk['table']}: " . $e->getMessage();
                }
                $fk_bar->advance();
            }
            $fk_bar->finish();
            $this->newLine(2);

            if (!empty($fk_errors)) {
                $this->warn("Se encontraron algunos errores al aplicar las llaves foráneas:");
                foreach ($fk_errors as $err) {
                    $this->error("  - {$err}");
                }
                $this->newLine();
            } else {
                $this->info("¡Todas las llaves foráneas fueron aplicadas con éxito!");
            }
        }

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
