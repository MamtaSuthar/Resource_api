<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Guard string. // web, api
     * 
     * @var string
     */
    protected $guard = 'web';

    /**
     * Authentication driver.
     *
     * @var \Illuminate\Auth\SessionGuard|\Tymon\JWTAuth\JWTGuard
     */
    protected $auth;

    protected $_e_data = [];

    public function __construct()
    {
        $this->auth = $this->authManager();

        $this->middleware(function($request, $next) {

            if( !empty($user = $this->user()) ) {
                $this->_e_data['user_blog_subscription'] =
                    $user_blog_subscription = $user->active_blog_subscription()->exists();

                if( $this->guard == 'web' ) {
                    view()->share('user_blog_subscription', $user_blog_subscription);
                }
            }

            // restrict web/admin/vendor uploading images more than specified file size globally
            if( ! $request->is(['api/*']) && $request->isMethod('post') ) {
                $imageMimeTypes = ['image/jpeg','image/jpg','image/gif','image/png','image/bmp','image/svg+xml'];;
                
                $maxImageFileSize = 800000; //bytes in 600 kb

                foreach( request()->file() as $file ) {
                    if( in_array($file->getMimeType(), $imageMimeTypes) ) {
                        if( $file->getSize() > $maxImageFileSize ) {
                            if( $request->ajax() ) {
                                return response()->json(['error' => 'Image size cannot exceed 600kb']);
                            }
                            return back()->withInput()->withError('Image size cannot exceed 600kb');
                        }
                    }
                }
            }

            return $next($request);
        });
    }


    protected function authManager()
    {
        return auth($this->guard);
    }

    /**
     * Return current logged in user.
     *
     * @return \App\Models\User|null
     */
    public function user()
    {
        if( !$this->auth ) {
            $this->auth = $this->authManager();
        }
        
        return $this->auth->user();
    }

    public function isRequestForWeb()
    {
        return $this->guard === 'web';
    }

    protected function validation_error_response(Validator $validator)
    {
        $_errors = $validator->errors()->messages();

        return response()->json([
            'status' => false,
            'data' => [
                'error' => $_errors
            ]
        ]);
    }

    protected function use_coupon($couponCode, $amount)
    {
        $coupon = \App\Models\CouponCode::checkCode(strtoupper($couponCode));
        $amount = floatval($amount);

        if( !empty($coupon) && $amount ) {
            $coupon_type = trim($coupon['type']);
            $coupon_val = floatval($coupon['discount']);

            if( $coupon_type == 'percentage' ) {
                $amount -= $amount * ($coupon_val/100);
            }
            elseif( $coupon_type == 'amount' ) {
                $amount -= $coupon_val;
            }

            if( $amount <= 0 ) {
                $amount = 0;
            }

            if( $user = $this->user() ) {
                $now = date('Y-m-d H:i');

                $user->user_used_coupons()->updateOrCreate(
                    ['code' => $couponCode, 'user_id' => $user->id],
                    ['code' => $couponCode, 'created_at' => $now, 'updated_at' => $now ]
                );

                if( request()->hasSession() ) {
                    session()->put('applying_coupon', $couponCode);
                }
            }
        }
        
        return $amount;
    }
}
