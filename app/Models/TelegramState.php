<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramState extends Model
{
    protected $table = 'telegram_states';

    protected $fillable = [
        'chat_id',
        'flow',
        'step',
        'data_json',
    ];

    public function data(): array
    {
        return json_decode((string) $this->data_json, true) ?: [];
    }

    public function setData(array $data): void
    {
        $this->data_json = json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
