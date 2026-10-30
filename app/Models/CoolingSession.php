<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CoolingSession extends Model {
    protected $guarded = [];
    protected function casts(): array {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime',
            'eco_at' => 'immutable_datetime', 'assumptions' => 'array', 'estimate' => 'array'];
    }
}
