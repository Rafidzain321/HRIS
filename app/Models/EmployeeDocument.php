<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $fillable = ['employee_id', 'tipe', 'nama_file', 'path', 'size', 'uploaded_by'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getSizeFormattedAttribute(): string
    {
        if (!$this->size) return '—';
        if ($this->size < 1024) return $this->size . ' B';
        if ($this->size < 1048576) return round($this->size / 1024, 1) . ' KB';
        return round($this->size / 1048576, 1) . ' MB';
    }

    public function getTipeLabel(): string
    {
        return match($this->tipe) {
            'ktp'          => 'KTP',
            'sio'          => 'Sertifikat SIO',
            'foto'         => 'Foto Karyawan',
            'kk'           => 'Kartu Keluarga',
            'bpjs'         => 'BPJS Ketenagakerjaan',
            'bpjs_kesehatan' => 'BPJS Kesehatan',
            'cv'           => 'CV / Curriculum Vitae',
            'skck'         => 'SKCK',
            default        => 'Dokumen Lainnya',
        };
    }
}
