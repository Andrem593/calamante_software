<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UpdateClientRoutesFromExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:update-routes 
                            {file? : Ruta al archivo Excel (.xlsx o .xls)}
                            {--dry-run : Ejecutar en modo simulación sin aplicar cambios en la base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza las rutas de sucursales y pone inactivos los clientes cuya ruta sea NULL desde un archivo Excel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->resolveFilePath($this->argument('file'));

        if (!$filePath || !File::exists($filePath)) {
            $this->error("No se encontró el archivo Excel en la ruta especificada: " . ($filePath ?? 'null'));
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info("============================================================");
        $this->info("Procesando archivo: {$filePath}");
        if ($dryRun) {
            $this->warn("MODO SIMULACIÓN (--dry-run) ACTIVADO: No se guardarán cambios en la BD.");
        }
        $this->info("============================================================");

        $this->info("Cargando hoja de cálculo...");
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows) || count($rows) < 2) {
            $this->error("El archivo está vacío o no tiene filas de datos.");
            return 1;
        }

        // Header row: index 0
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $rows[0]);
        $clientIdx = array_search('cliente_id', $header);
        $branchIdx = array_search('sucursal_id', $header);
        $routeIdx = array_search('ruta', $header);

        if ($clientIdx === false || $routeIdx === false) {
            // Fallback por posiciones estándar si las cabeceras varían
            $clientIdx = 0;
            $branchIdx = 3;
            $routeIdx = 5;
        }

        $totalRows = 0;
        $activeCount = 0;
        $inactiveCount = 0;
        $branchesUpdated = 0;
        $routeBreakdown = [];
        $errors = [];

        $dataRows = array_slice($rows, 1);

        $this->output->progressStart(count($dataRows));

        DB::beginTransaction();

        try {
            foreach ($dataRows as $lineNum => $row) {
                $this->output->progressAdvance();

                // Ignorar filas totalmente vacías
                if (empty(array_filter($row, fn($val) => $val !== null && trim((string)$val) !== ''))) {
                    continue;
                }

                $rawClientId = $row[$clientIdx] ?? null;
                $rawBranchId = $branchIdx !== false ? ($row[$branchIdx] ?? null) : null;
                $rawRoute = $row[$routeIdx] ?? null;

                if (empty($rawClientId) || !is_numeric($rawClientId)) {
                    continue;
                }

                $clientId = (int) $rawClientId;
                $branchId = (!empty($rawBranchId) && is_numeric($rawBranchId)) ? (int) $rawBranchId : null;
                $routeStr = trim((string) $rawRoute);
                $isRouteNull = ($routeStr === '' || strtoupper($routeStr) === 'NULL' || strtoupper($routeStr) === 'N/A');

                $totalRows++;

                if ($isRouteNull) {
                    // Marcar cliente como INACTIVO
                    $inactiveCount++;
                    if (!$dryRun) {
                        Client::where('id', $clientId)->update(['is_active' => false]);
                        if ($branchId) {
                            Branch::where('id', $branchId)->update(['route' => null]);
                        }
                    }
                } else {
                    // Ruta válida: Limpiar texto a mayúsculas
                    $cleanRoute = strtoupper($routeStr);
                    $routeBreakdown[$cleanRoute] = ($routeBreakdown[$cleanRoute] ?? 0) + 1;
                    $activeCount++;

                    if (!$dryRun) {
                        // Asegurar cliente activo
                        Client::where('id', $clientId)->update(['is_active' => true]);

                        // Asignar ruta a la sucursal indicada
                        if ($branchId) {
                            $updated = Branch::where('id', $branchId)->update(['route' => $cleanRoute]);
                            if ($updated) {
                                $branchesUpdated++;
                            }
                        } else {
                            // Si no hay branch_id específico, asignar a todas las sucursales del cliente
                            Branch::where('client_id', $clientId)->update(['route' => $cleanRoute]);
                            $branchesUpdated++;
                        }
                    } else {
                        if ($branchId) {
                            $branchesUpdated++;
                        }
                    }
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->output->progressFinish();
            $this->error("Error durante el procesamiento: " . $e->getMessage());
            return 1;
        }

        $this->output->progressFinish();
        $this->newLine();

        $this->info("=================== RESUMEN DE EJECUCIÓN ===================");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Filas de datos procesadas', $totalRows],
                ['Clientes puestos como INACTIVOS (Ruta NULL)', $inactiveCount],
                ['Clientes mantenidos/activados como ACTIVOS', $activeCount],
                ['Sucursales actualizadas con ruta', $branchesUpdated],
            ]
        );

        $this->info("\nDistribución de rutas aplicadas:");
        $routeTableData = [];
        arsort($routeBreakdown);
        foreach ($routeBreakdown as $route => $count) {
            $routeTableData[] = [$route, $count];
        }
        $this->table(['Ruta', 'Cantidad Sucursales'], $routeTableData);

        if ($dryRun) {
            $this->warn("\n[SIMULACIÓN] No se aplicó ningún cambio en la base de datos.");
            $this->line("Para aplicar los cambios reales, ejecuta:");
            $this->line("  php artisan clients:update-routes " . escapeshellarg($filePath));
        } else {
            $this->info("\n¡Base de datos actualizada exitosamente!");
        }

        return 0;
    }

    /**
     * Determina la ruta absoluta del archivo Excel.
     */
    protected function resolveFilePath(?string $path): ?string
    {
        if ($path) {
            if (File::isAbsolutePath($path)) {
                return $path;
            }
            return base_path($path);
        }

        // Buscar ubicaciones comunes por defecto
        $candidates = [
            base_path('../clientes-5.xlsx'),
            base_path('clientes-5.xlsx'),
            '/Users/mac/calamante/calamante_software/clientes-5.xlsx',
        ];

        foreach ($candidates as $cand) {
            if (File::exists($cand)) {
                return realpath($cand);
            }
        }

        return null;
    }
}
