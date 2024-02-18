<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'vendor_verified_at' => 'datetime',
        'download_date' => 'datetime'
    ];


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        $request = request();

        if( \in_array($request->get('platform'), ['ios', 'android', 'web']) ) {
            return ['user_platform' => $request->get('platform')];
        }

        return [];
    }

    # Define User Role Types Constant
    public const SUPERADMIN = 'superadmin';
    public const ADMIN      = 'admin';
    public const EDITOR     = 'editor';
    public const REPORTER   = 'reporter';
    public const VENDOR     = 'vendor'; // Vendor or Author
    public const CUSTOMER   = 'user'; // Customer OR User
    public const COMPANY    = 'company';


    public function isSuperAdmin()
    {
        return $this->hasRole(self::SUPERADMIN);
    }

    public function isAdmin()
    {
        return $this->isSuperAdmin() || $this->hasRole(self::ADMIN);
    }

    public function isEditor()
    {
        return $this->hasRole(self::EDITOR);
    }

    public function isReporter()
    {
        return $this->hasRole(self::REPORTER);
    }

    public function isVendor()
    {
        return $this->hasRole(self::VENDOR);
    }
    
    public function isCustomer()
    {   
        return $this->hasRole(self::CUSTOMER);
    }

    public function isCompany()
    {
        return $this->hasRole(self::COMPANY);
    }

    /**
     * Note roles() method defined in HasRoles traits
     */
    public function role()
    {
        return $this->roles->first();
    }

    public function getRoleNameAttribute()
    {
        return ($role = $this->role()) ? $role->name : '';
    }

    public function getStatusTextAttribute()
    {
        $status = [
            0 => 'blocked',
            1 => 'active',
        ];

        return $status[$this->status];
    }

    public function getNameAttribute()
    {
        return $this->last_name 
            ? $this->first_name." ".$this->last_name 
        : $this->first_name;
    }

    public function format()
    {

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name ?? '',
            'role_name'  => $this->role_name, 
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'dob'   => $this->dob ?? null,
            'country' => $this->country,
            'gender' => $this->gender,
            'email_verified_at' => $this->email_verified_at,
            'refer_code'=>$this->refer_code
        ];
    }

    
    public function isBlocked()
    {
        return $this->status === 0;
    }
    
    public function isVendorVerified()
    {
        return !is_null($this->vendor_verified) && $this->vendor_verified;
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function magazine_downloads()
    {
        return $this->belongsToMany(Magazine::class, 'user_downloads', 'user_id', 'file_id')
            ->wherePivot('file_type', '=', 'magazine')
            ->withPivot(['file_type', 'created_at']);
    }

    public function newspaper_downloads()
    {
        return $this->belongsToMany(Newspaper::class, 'user_downloads', 'user_id', 'file_id')
            ->wherePivot('file_type', '=', 'newspaper')
            ->withPivot(['file_type', 'created_at']);
    }

    # User may have one information record
    public function info()
    {
        return $this->hasOne(UserInfo::class);
    }

    public function get_info($column)
    {
        if( !$this->info ) {
            return null;
        }
        
        return $this->info->{$column};
    }

    # obsolete
    # User has one subscription
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function subscriptions()
    {
        return $this->subscriptions_all()
            ->where('pay_status', '!=', 0);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'user_id');
    }

    public function active_subscriptions()
    {
        $now = now()->format('Y-m-d H:i:s');

        return $this->subscriptions_all()
            ->where('pay_status', 1)
            ->whereDate('subscribed_at', '<=', $now)
            ->whereDate('expires_at', '>=', $now);
    }

    public function active_blog_subscription()
    {
        return $this->active_subscriptions()
            ->whereHas('plan', function($query) {
                $query->where('type', 'premium');
            });
    }

    public function subscriptions_all()
    {
        return $this->hasMany(UserSubscription::class, 'user_id')
            ->whereNotNull('plan_duration_id');
    }

    // @obsolete
    public function blog_subscriptions()
    {
        return $this->blog_subscriptions_all()
            ->where('pay_status', '!=', 0);
    }

    // @obsolete
    public function blog_active_subscriptions()
    {
        $now = now()->format('Y-m-d H:i:s');

        return $this->blog_subscriptions_all()
            ->where('pay_status', 1)
            ->whereDate('subscribed_at', '<=', $now)
            ->whereDate('expires_at', '>=', $now);
    }

    // @obsolete
    public function blog_subscriptions_all()
    {
        return $this->hasMany(UserBlogSubscription::class, 'user_id')
            ->whereNotNull('blog_plan_duration_id');
    }

    public function setting()
    {
        return $this->hasOne(UserSetting::class, 'user_id');
    }

    public function scopePushEnabled($query)
    {
        return $query->whereDoesntHave('setting', function($query) {
            $query->where('push_notification', 0);
        });
    }

    // vendor
    public function magazines()
    {
        return $this->hasMany(Magazine::class, 'user_id');
    }

    // vendor
    public function newspapers()
    {
        return $this->hasMany(Newspaper::class, 'user_id');
    }
    
    public function verifyuser(){
        return $this->hasOne('App\Models\VerifyUser');
    }

    // $magNewsDate : Carbon
    public function isSubToPublication(Publication $publication, $magNewsDate = null)
    {
        $subscriptions = $this->active_subscriptions;
        $subscriptions->load('plan.publications');

        foreach($subscriptions as $subscription) {
            if( $subscription->plan->publications->contains($publication) ) {

                // this magazine/newspaper must be published
                // after the purchase of this successful subscription
                if( $magNewsDate
                    && $magNewsDate instanceof Carbon
                    && !($magNewsDate->isSameDay($subscription->subscribed_at))
                    && $magNewsDate->lt($subscription->subscribed_at) ) {
                    return -1;
                }

                return true;
            }
        }

        return false;
    }

    public function bought_magazines()
    {
        return $this->belongsToMany(Magazine::class, UserOneTimePurchase::class, 'user_id', 'package_id')
            ->wherePivot('package_type', 'magazine')
            ->withPivot(['pay_status'])
            ->orderBy('user_onetime_purchases.created_at', 'DESC');
    }

    public function bought_newspapers()
    {
        return $this->belongsToMany(Newspaper::class, UserOneTimePurchase::class, 'user_id', 'package_id')
            ->wherePivot('package_type', 'newspaper')
            ->withPivot(['pay_status'])
            ->orderBy('user_onetime_purchases.created_at', 'DESC');
    }

    public function hasBoughtMagazine(Magazine $magazine)
    {
        return $this->bought_magazines
            ->where('pivot.pay_status', 1)
            ->contains($magazine);
    }

    public function hasBoughtNewspaper(Newspaper $newspaper)
    {
        return $this->bought_newspapers
            ->where('pivot.pay_status', 1)
            ->contains($newspaper);
    }

    public static function getUsersForNotification($event, ?Category $category = null)
    {
        $notif_template = \App\Models\NotifTemplate::query()
            // ->where('event', 'new_blogs')
            ->where('event', $event)
            ->with(['restrictions'])
            ->first();

        if( empty($notif_template) ) {
            return null;
        }

        $age_group = 'all';
        $gender = 'all';

        $restriction = $notif_template->restrictions;

        if( $category ) {
            $restriction = $restriction
                ->where('category_id', $category->id);
        }

        if( $restriction = $restriction->first() ) {
            $age_group = $restriction->age_group;
            $gender = $restriction->gender;
        }

        $users = self::PushEnabled();

        if( $age_group !== 'all' && ($age_group = intval($age_group)) ) {
            $users->whereRaw("TIMESTAMPDIFF(YEAR, dob, CURDATE()) >= ?", [$age_group]);
        }

        if( $gender !== 'all' ) {
            $users->where('gender', $gender);
        }

        $users = $users
            ->get()
            ->pluck('id')
            ->toArray();

        return [ $users, $notif_template ];
    }

    public function getIsCurrencyLocalAttribute()
    {
        return $this->my_currency === 'GHS';
    }

    public function getMyCurrencyAttribute()
    {
        if( $this->userPlatform == 'ios' ) {
            return 'USD';
        }
        
        if ($this->country != 'GH' && $this->country != 'GHA') {
            return 'USD';
        }

        return 'GHS';
    }
    public function myCoupons()
    {
        return $this->hasMany(CouponCode::class, 'user_id');
    }
    public function myValidCoupons(){
        $user_id = $this->id;

        $coupons = CouponCode::query()
            ->where(function($query) use($user_id) {
                $query->where('user_id', $user_id)
                    ->orWhereNull('user_id');
            })
            ->where('used_times','>',0)
            ->get();

        foreach ($coupons as $key => $coupon) {
            $now = date('Y-m-d H:i');
            
            $couponLastDate = date('Y-m-d', strtotime("+{$coupon->valid_for} days", strtotime($coupon->created_at)));

            if($now > $couponLastDate){
                $coupons->pull($key);
            }
        }

        return $coupons;

    }
    public function referralUser($id){
        $user = User::find($id);
        return (($user)?$user->getNameAttribute().'('.$user->email.')':'');
    }

    // people "this user" has referred this app to
    public function referred_to()
    {
        return $this->hasMany(User::class, 'refer_by', 'id');
    }

    // individual who referred "this user"
    public function referred_by()
    {
        return $this->belongsTo(User::class, 'id', 'refer_by');
    }

    public function user_used_coupons()
    {
        return $this->hasMany(UserUsedCoupon::class, 'user_id');
    }

    public function getUserPlatformAttribute()
    {
        try {

            if( request()->is('api/*') ) {
                /** @var \Tymon\JWTAuth\JWTAuth $auth */
                $auth = auth('api');
                
                $payload = $auth->parseToken()->getPayload();

                return $payload->get('user_platform');
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return null;
    }

    public function getIsIosAttribute()
    {
        return $this->userPlatform == 'ios';
    }

    public function getSubscribedPublications()
    {
        $publications = collect();

        $subscriptions = $this->active_subscriptions()
            ->with(['plan.publications'])
            ->get();

        foreach( $subscriptions as $subscription ) {
            
            if( $subscription->plan && $subscription->plan->publications ) {

                $pubs = $subscription->plan->publications
                    ->map(function($pub) use($subscription) {
                        $pub->setAttribute('puchased_at', $subscription->subscribed_at);
                        return $pub;
                    });

                $publications = $publications->merge($pubs);
            }
        }

        return $publications;
    }
}
