<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['id' => 'integer', 'admin_id' => 'integer', 'target_id' => 'integer', 'details' => 'array'];
    }

    public function admin()
    {
        return $this->belongsTo(Administrator::class, 'admin_id');
    }
}
