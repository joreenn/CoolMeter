<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CoolingSetting extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['values' => 'array']; }
    public static function current(): array {
        return array_replace(config('cooling'), static::find(1)?->values ?? []);
    }
}
