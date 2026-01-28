<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Log;

class DeleteTercerosMenoresCommnad extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-terceros-menores-commnad';

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
        $automovil = env("DB_DATABASE_AUTOMOVIL");
        $personas = env("DB_DATABASE_PERSONAS");

        $this->info("Obteniendo menores de edad...");
        Log::info("Obteniendo menores de edad...");
        
        $obtener_menores = DB::connection("mysql")
        ->table("terceros")
        ->select("terceros.*")
        ->leftJoin("{$personas}.certificados_terceros as certificados_personas", "terceros.id_terceros", "certificados_personas.tercero_id")
        ->leftJoin("{$automovil}.certificados_terceros as certificados_automovil", "terceros.id_terceros", "certificados_automovil.tercero_id")
        ->leftJoin("contratantes", "terceros.id_terceros", "contratantes.tercero_id")
        ->leftJoin("proveedor", "terceros.id_terceros", "proveedor.tercero_id")
        ->leftJoin("intermediarios", "terceros.id_terceros", "intermediarios.tercero_id")
        ->leftJoin("archivos_terceros", "terceros.id_terceros", "archivos_terceros.tercero_id")
        ->where("terceros.cod_documento", 3)
        // ->whereRaw("CHAR_LENGTH(terceros.cedula) > 8")
        ->where("terceros.cedula", ">", "40000000")
        // ->whereRaw("DATE(terceros.fecha_registro) < '2025-08-01'")
        ->whereNull("certificados_personas.id")
        ->whereNull("certificados_automovil.id")
        ->whereNull("contratantes.id")
        ->whereNull("proveedor.id")
        ->whereNull("intermediarios.id")
        ->whereNull("archivos_terceros.id")
        ->get();
        // ->count();
        // dd($obtener_menores->toArray()[0]);

        $barRel = $this->output->createProgressBar(count($obtener_menores));
        $this->info("Eliminando menores de edad...");
        $barRel->start();

        //* Eliminamos los menores de edad
        foreach ( $obtener_menores as $menor ) {
            try {
                DB::transaction(function () use ($menor) {
                    DB::connection("mysql")
                        ->table("terceros_perfiles")
                        ->where("terceros_perfiles.tercero_id", $menor->id_terceros)
                        ->delete();

                    DB::connection("mysql")
                        ->table("terceros")
                        ->where("terceros.id_terceros", $menor->id_terceros)
                        ->delete();

                    Log::info("Menor eliminado: " . $menor->id_terceros);
                });
            } catch (\Exception $e) {
                Log::error("Error al eliminar menor: " . $menor->id_terceros . " " . $e->getMessage());
            }

            $barRel->advance();
        }
        
        $barRel->finish();
        $this->newLine(2);
        $this->info("Menores eliminados...");
        Log::info("Menores eliminados...");
    }
}
