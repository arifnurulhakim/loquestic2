<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $table = 'levels';

    protected $fillable = [
        'player_id',
        'level',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id'); // Corrected foreign key reference
    }

    public function level()
    {
        return $this->belongsTo(Leveling::class, 'level'); // Corrected foreign key reference
    }
}
