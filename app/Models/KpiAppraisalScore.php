<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiAppraisalScore extends Model
{
    protected $fillable = ['kpi_appraisal_id', 'kpi_criteria_id', 'nilai'];

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(KpiAppraisal::class, 'kpi_appraisal_id');
    }

    public function criteria(): BelongsTo
    {
        return $this->belongsTo(KpiCriteria::class, 'kpi_criteria_id');
    }
}
