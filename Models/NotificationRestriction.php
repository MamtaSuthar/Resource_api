<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRestriction extends Model
{
    use HasFactory;

    protected $table = 'notification_restriction';

    protected $guarded = ['id'];

    public function notification()
    {
        return $this->belongsTo(NotifTemplate::class, 'notif_template_id');
    }
}
