<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Lot de clàusula del contracte amb CatSalut: territori ample (B1 Barcelona, B9 Vallès).
 * `domi_zone` guarda l'equivalent a domi (DOMI-BCN / DOMI-VALLES) per poder conciliar sense
 * haver de mantenir una taula de traducció a part.
 */
class Lot extends Model
{
    protected $fillable = ['code', 'name', 'domi_zone', 'sort', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lot_user');
    }
}
