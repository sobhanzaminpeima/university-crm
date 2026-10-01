<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUpdatesOffset extends Model
{
    protected $table = 'telegram_updates_offset';

    protected $fillable = ['last_update_id'];
}
