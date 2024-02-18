<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Newspaper extends PremiumContent
{
    use HasFactory;

    protected $guarded = ['id'];

    ## Define Date Casting ##
    protected $casts = [
        'published_date' => 'date:Y-m-d'
    ];


    ## Define Query Scopes ##
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeByCategory($query, int $id)
    {
        return $query->where('category_id', $id);
    }

    ## Define Mutators ##
    public function setPublishedDateAttribute($value)
    {
        $this->attributes['published_date'] = now()->parse($value)->format('Y-m-d');
    }

    ## Define Accessors ##
    public function getStatusTextAttribute()
    {
        $status = [
            0 => 'Deactive',
            1 => 'Active'
        ];
        return $status[$this->status];
    }

    public function getTagsStringAttribute()
    {
        return implode(',', $this->tags()->pluck('name')->toArray());
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // users who directly purchased this newspaper
    public function users_who_bought()
    {
        return $this->belongsToMany(User::class, UserOneTimePurchase::class, 'package_id', 'user_id')
            ->withPivot(['price', 'pay_status'])
            ->wherePivot('package_type', 'newspaper');
    }

    public function getTypeAttribute()
    {
        return 'Newspaper';
    }
}
