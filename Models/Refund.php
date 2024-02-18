<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'refunds';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'refund_id');
    }

    public function entity()
    {
        $entity = null;

        switch( $this->for ) {
            case 'plan_subscription':
                $entity = UserSubscription::class;
                break;
            case 'blog_subscription':
                $entity = UserBlogSubscription::class;
                break;
            case 'direct_purchase_magazine':
                $entity = Magazine::class;
                break;
            case 'direct_purchase_newspaper':
                $entity = Newspaper::class;
                break;
        }

        if( empty($entity) ) {
            return null;
        }

        return $this->belongsTo($entity, 'entity_id');
    }

    public function getStatusStrAttribute()
    {
        $status = '';

        switch( $this->status ) {
            case 'requested':
                $status = 'Requested'; break;
            case 'success':
                $status = 'Refund Processed'; break;
            default:
                $status = 'Refund Initiated';
        }       

        return $status;
    }

    public function getEntityStrAttribute()
    {
        switch( $this->for ) {
            case 'plan_subscription':
                return 'Plan Subscription';
            case 'blog_subscription':
                return 'Blogging Subscription';
            case 'direct_purchase_magazine':
                return 'Magazine';
            case 'direct_purchase_newspaper':
                return 'Newspaper';
        }

        return '';
    }

    public function getPlanNameAttribute()
    {

        return $this->planid;
    }

    public function isRefundInitiated()
    {
        return $this->status !== 'requested';
    }
}
