<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportBranchRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:import-routes {file? : Ruta al archivo SQL o Excel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa y actualiza la columna route de la tabla branches en la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sqlPath = $this->argument('file') ?? base_path('update_branch_routes.sql');

        if (!File::exists($sqlPath)) {
            $this->error("No se encontró el archivo SQL en: {$sqlPath}");
            return 1;
        }

        $this->info("Ejecutando script de actualización de rutas...");
        
        $sql = File::get($sqlPath);
        
        DB::transaction(function () use ($sql) {
            DB::unprepared($sql);
        });

        $this->info("¡Actualización de rutas de sucursales completada con éxito!");
        return 0;
    }
}
