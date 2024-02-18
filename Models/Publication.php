<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Publication extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // get active subscriptions on this publication
    public function subscriptions()
    {
        $id = $this->id;

        return UserSubscription::query()
            ->active()
            ->whereHas('plan', function($query) use($id) {
                $query->whereHas('publications',
                    function($innerQuery) use($id) {
                        $innerQuery->where('publications.id', $id);
                    });
            })
            ->get();
    }

    public function subscribed_users()
    {
        $users = $this->subscriptions()
            ->pluck('user_id')->unique()->toArray();

        if( !empty($users) ) {
            $users = User::PushEnabled()
                ->find($users);

            if( $users->isNotEmpty() ) {
                return $users;
            }
        }

        return false;
    }

    public function magazines()
    {
        return $this->hasMany(Magazine::class, 'publication_id');
    }

    public function newspapers()
    {
        return $this->hasMany(Newspaper::class, 'publication_id');
    }

    # Define Query Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * # Define Accessors
     */
    public function getStatusTextAttribute()
    {
        $status = [
            0 => 'Deactive',
            1 => 'Active'
        ];
        return $status[$this->status];
    }
}
