<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EmployeeImport;

class ImportEmployeesCommand extends Command
{
    protected $signature   = 'akm:import-employees {file : Path ke file Excel}';
    protected $description = 'Import data karyawan dari file Excel AKM';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File tidak ditemukan: $file");
            return 1;
        }

        $this->info("Mengimport dari: $file");
        $bar = $this->output->createProgressBar();
        $bar->start();

        Excel::import(new EmployeeImport($bar), $file);

        $bar->finish();
        $this->newLine();
        $this->info('✅ Import selesai!');
        return 0;
    }
}
