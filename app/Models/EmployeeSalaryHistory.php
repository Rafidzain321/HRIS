<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryHistory extends Model
{
    protected $table = 'employee_salary_history';

    protected $fillable = [
        'employee_id', 'label', 'nominal', 'tahun', 'bulan', 'urutan',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
