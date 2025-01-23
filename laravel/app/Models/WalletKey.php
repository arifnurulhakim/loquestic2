<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletKey extends Model
{
    protected $table = 'wallet_keys';

    protected $fillable = [
        'player_id',
        'key',


    ];

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id', 'id');
    }
}
