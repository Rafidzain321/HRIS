<?php
namespace App\Models;
// ActivityLog.php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'module', 'target_name', 'description', 'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper static method untuk log mudah dari mana saja
    public static function record(string $action, string $module, ?string $target = null, ?string $desc = null): void
    {
        static::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'module'      => $module,
            'target_name' => $target,
            'description' => $desc,
            'ip_address'  => request()->ip(),
        ]);
    }
}
