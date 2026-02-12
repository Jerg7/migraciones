<?php

namespace App\Console\Commands;

use DB;
use Hash;
use Illuminate\Console\Command;

class MigrateSiniestrosUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-siniestros-users-command';

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
        $general = env("DB_DATABASE");
        $password_general = Hash::make('Miranda2026.*');

        $usuarios_faltantes = DB::connection('mysql_personas')->table('siniestros')
        ->join("{$general}.usuarios", "siniestros.id_usuario", "usuarios.id_usuario")
        ->join("{$general}.detalle_usuario", "detalle_usuario.id_usuario", "usuarios.id_usuario")
        ->whereNotExists(function($query) use ($general) {
            $query->select(DB::raw(1))
            ->from("{$general}.users")
            ->whereRaw('users.username = usuarios.nick OR users.email = detalle_usuario.correo');
        })
        ->select(
            'usuarios.nick',
            'usuarios.clave',
            'detalle_usuario.correo',
            'detalle_usuario.nombres',
            'detalle_usuario.apellidos'
        )
        ->groupBy('usuarios.nick', 'usuarios.clave', 'detalle_usuario.correo', 'detalle_usuario.nombres', 'detalle_usuario.apellidos')
        ->get();

        $this->info("Usuarios faltantes: " . $usuarios_faltantes->count());

        foreach ($usuarios_faltantes as $usuario) {
            DB::connection('mysql')->table('users')->insert([
                'username' => $usuario->nick,
                'password' => $password_general,
                'password_confirmed' => 0,
                'email' => $usuario->correo,
                'name' => "{$usuario->nombres} {$usuario->apellidos}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("Usuarios migrados exitosamente");
    }
}
