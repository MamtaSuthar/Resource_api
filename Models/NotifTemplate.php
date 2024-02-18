<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotifTemplate extends Model
{
    use HasFactory;

    protected $table = 'notif_templates';

    protected $guarded = ['id'];

    public function restrictions()
    {
        return $this->hasMany(NotificationRestriction::class, 'notif_template_id');
    }
}
