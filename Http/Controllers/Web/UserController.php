<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Validator;
use App\Models\User;
use App\Models\Tag;
use App\Models\Newspaper;
use App\Models\Magazine;
use App\Models\Blog;
use App\Models\Video;
use App\Models\Gallery;
use App\Models\Podcast;
use App\Models\Category;
use App\Models\VerifyUser;
use App\Mail\CustomerVerify;
use Hash;
use Illuminate\Support\Facades\Redirect;
use Cookie;
use App\Models\UserInfo;
use App\Traits\CommonTrait;
use Monarobase\CountryList\CountryListFacade;
use App\Traits\ManageUserTrait;
use App\Models\Position;
use App\Models\UserBookmark;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\AlbumResource;
use App\Models\Albums;
use App\Models\Content;
use App\Models\HeardFrom;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{

    use ManageUserTrait, CommonTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function getUserPreferances($userid)
    {
        $ft = UserInfo::where('user_id', $userid)->first()->favourite_topics ?? [];
        $topics = !empty($ft) ? json_decode($ft) : [];
        return $topics;
    }

    public function index()
    {
        $positions = _h_cache("home_pos", function() {
            return Position::all();
        });

        $filterd = $positions->sortBy('position');
        $posValue = $filterd->values();

        $user = $this->user();

        $topics = $this->getUserPreferances($user ? $user->id : 0);

        $bnews = $user ? UserBookmark::where('user_id', $user->id)->where('type', 'newspaper')->pluck('pid')->all() : [];
        $bmags = $user ? UserBookmark::where('user_id', $user->id)->where('type', 'magazine')->pluck('pid')->all() : [];

        $btopstory = $user ? UserBookmark::where('user_id', $user->id)->where('type', 'top_story')->pluck('pid')->all() : [];
        $bpromoted = $user ? UserBookmark::where('user_id', $user->id)->where('type', 'popular_content')->pluck('pid')->all() : [];

        $categories  = Category::query()->active()->latest()->limit(12);

        $mags = Magazine::with(['category', 'publication'])
            ->active()->latest()->limit(8);

        $news = Newspaper::with(['category', 'publication'])
            ->active()->latest()->limit(8);

        $promoted_content = Blog::with('blog_category')->where('promoted', 1)
            ->active()->latest()->limit(8);

        $top_story = Blog::with('blog_category')->where('top_story', 1)->latest()->active()->limit(8);

        $with_topics = '0';

        if( ! empty($topics) ) {
            $with_topics = '1';

            $categories->whereIn('id', $topics);

            $mags->whereIn('category_id', $topics);

            $news->whereIn('category_id', $topics);

            $promoted_content->whereIn('blog_category_id', $topics);

            $top_story->whereIn('blog_category_id', $topics);
        }

        $categories = _h_cache("home_categories{$with_topics}", function() use($categories) {
            return $categories->get();
        });

        $mags = _h_cache("home_mags{$with_topics}", function() use($mags) {
            return $mags->get();
        });

        $news = _h_cache("home_news{$with_topics}", function() use($news) {
            return $news->get();
        });

        $promoted_content = _h_cache("home_pc{$with_topics}", function() use($promoted_content) {
            return $promoted_content->get();
        });

        // $top_story = _h_cache("home_ts{$with_topics}", function() use($top_story) {
            $top_story = $top_story->get();
        // });

        $videos  = _h_cache("home_v", function() {
            return Video::active()->latest()->limit(8)->get();
        });

        $galleries = _h_cache("home_g", function() {
            return Albums::active()->latest()->limit(8)->get();
        });

        $podcasts  = _h_cache("home_p", function() {
            return Podcast::active()->latest()->limit(8)->get();
        });

        $tags = _h_cache("home_t", function() {
            return Tag::latest()->get();
        });

        $instadata = _h_cache("home_insta", function() {
            return $this->instaDataWeb();
        }, 4800);

        $blogs = [];

        // $lastBlogRec = Blog::orwhere('promoted', 1)->orwhere('top_story', 1)->orderBy('id', 'desc')->take(6)->active()->latest()->get();
        $lastBlogRec = [];

        foreach ($lastBlogRec as $blog) {
            $blog['content_image'] = strpos($blog['content_image'], 'http') !== 0
                ? asset("storage/" . $blog['content_image'])
                : $blog['content_image'];

            array_push($blogs, $blog);
        }

        return view('customer.welcome')->with(['news' => $news, 'mags' => $mags, 'promoted_content' => $promoted_content, 'top_story' => $top_story, 'videos' => $videos, 'galleries' => $galleries, 'podcasts' => $podcasts, 'categories' => $categories, 'tags' => $tags, 'slider' => $blogs, 'instadata' => $instadata, 'posValue' => $posValue, 'bnews' => $bnews, 'bmags' => $bmags, 'btopstory' => $btopstory, 'bpromoted' => $bpromoted]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register()
    {
        // $states= nigeriaStates();
        $countries =  CountryListFacade::getList('en');
        // dd($countries);
        if (auth()->user()) {
            return redirect()->route('home')->with('error', 'Already Logged in.');
        }

        $heard_froms = HeardFrom::where('status', 1)->get();

        return view('customer.pages.register', compact('countries', 'heard_froms'));
    }
    public function store(Request $request)
    {
        //
        // dd($request->all());
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'regex:/(^[A-Za-z0-9 ]+$)+/', 'min:3', 'max:70'],
            'email' => ['required', 'email', 'unique:users', 'max:50'],
            'phone'         => ['required', 'digits_between:8,15', 'unique:users,phone'],
            'dob'           => ['required', 'nullable', 'date'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required'],
            'country'       => ['required', 'string'],
            'referred_from'   => ['nullable', 'max:1000'],
            'refer_code'   => ['nullable', 'max:8'],
            'terms' => ['required'],
            'mathcaptcha' => 'required|mathcaptcha',

            // 'refer_by'   => ['nullable']
        ], [
            'full_name.required' => "Please enter your name between 3 to 70 characters"
        ]);
        // dd($validated);
        try {
            $refer_code = $validated['refer_code'];
            unset($validated['refer_code']);
            # Update Query Data
            $validated['password'] = Hash::make($validated['password']);
            $validated['first_name'] = $validated['full_name'];
            $validated['refer_code'] = $this->getReferralCode($validated['full_name']);
            $validated['referred_from'] = $validated['referred_from'] ?? '';
            $validated['refer_by'] = (isset($refer_code) && $refer_code != "") ? $this->getUserByRefercode($refer_code) : 0;
            if (isset($refer_code) && $this->getUserByRefercode($refer_code) == 0) {
                return back()->with('error', 'Refer code not valid');
            }


            // dd($validated);    
            // $validated['last_name']=$validated['full_name'];
            # Create User Account
            $user = User::create(
                collect($validated)->toArray()
            );

            # Assign Role
            $user->syncRoles([User::CUSTOMER]);
            # Create User Info
            $user->info()->create([
                'dob'       => now()->parse($request->input('dob'))->format('Y-m-d'),
                'country'   => $request->input('country'),
            ]);
            /*send mail function*/
            try {
                $this->sendverifyMail($user, $user->id);
            } catch (\Exception $e) {
                logger('Signup issue: ' . $e->getMessage());
            }
            if ($validated['refer_by']) {
                $this->generateCouponCode($user->refer_by);
            }



            return redirect()->route('login')->withSuccess('Account Successfully Created, Log in to your email to verify your account');
        } catch (\Exception $e) {
            dd($e);
            logger($e->getMessage());
        }
    }

    public function web_terms()
    {
        $content = Content::where('slug', 'web_terms')->first();

        return view('customer.webTerms.webterms', compact('content'));
    }
    public function login(Request $request)
    {
        // dd($request->all());
        // $request->validate([
        //     'email'     => ['required','email'],
        //     'password'  => ['required']
        // ]);
        $request->validate([
            'email'     => ['required', 'email'],
            'password'  => ['required']
        ]);

        # Check User Authentication using credentials
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status != 1) {
            return back()->with('error', 'Unauthorized! Your Account is suspended.');
        }
        // $user = $this->user();
        # Validate for the customer only
        if (!$user->isCustomer()) {
            return back()->with('error', 'Unauthorized! Not a valid  User.');
        }
        if (!$user['verified']) {
            return back()->with('error', 'Please verify your registered email first.');
        }
        // Auth::login($user, $request->has('remember_me'));
        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials, $request->has('remember') == "on")) {

            if ($redirect_route = $request->session()->pull('redirect_route')) {

                return redirect($redirect_route)
                    ->withSuccess('Login Successfully');
            }

            // return redirect()->route('customer.home')
            //     ->withSuccess('Login Successfully');

            // log this user out from other devices
            Auth::logoutOtherDevices($request->get('password'));

            return redirect()->intended('/')
                ->withSuccess('Login Successfully');
        }
        return redirect()->route('login');
    }



    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
    public function homepage()
    {
        return view('customer.welcome');
    }

    # function usedfor sendmail using userverify mail
    public function sendverifyMail($user, $user_id)
    {
        $verify_user = VerifyUser::create([
            'user_id' => $user_id,
            'token'     => sha1(time())
        ]);
        // echo "<pre>";
        // print_r(User::with('verifyuser')->get());
        // die;
        Mail::to($user)->send(new CustomerVerify($user));
    }

    public function verifyLink($token)
    {
        $verifyuser = VerifyUser::where('token', $token)->first();
        
        if (isset($verifyuser)) {
            $user = $verifyuser->user;

            $redirect = redirect('/topstory');
            $message = '';

            $user_role = request()->query('smp');

            if (!$user->verified) {

                $verifyuser->user->verified = 1;
                $verifyuser->user->save();
                $email = $user->email;

                if( $user_role != 'vendor' ) {

                    try {
                        Mail::send(
                            'customer.email.confirmemail',
                            array(
                                'name' => $user->first_name . " " . $user->last_name,
                                'email' => $user->email
                            ),
                            function ($message) use ($email) {

                                $message->to($email)->subject('Confirmation Email');
                            }
                        );
                    } catch (\Exception $e) {
                        logger(' issue: ' . $e->getMessage());
                    }
                }

                $message = 'Your email is verified. You can now log in';
            } else {
                $message = 'Your email is already verified. You can log in.';
            }

            if( $user_role == 'vendor' ) {
                $redirect = redirect('/vendor');
            }

            return $redirect->withSuccess($message);
        }

        return redirect()->route('home')
            ->with('error', 'Something Went Wrong.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getstory(Request $request)
    {
        $type = '';
        $id = '';
        if ($request->has('t') && $request->t) {
            $type = $request->t;
        } else {
            return Redirect::to('/', $status = 302)->with('error', 'Invalid URL Given.');
        }

        if ($request->has('tid') && $request->tid) {
            $id = $request->tid;
        } else {
            return Redirect::to('/', $status = 302)->with('error', 'Invalid URL Given.');
        }

        if (in_array($type, ['newspaper', 'magazines']) && $id != '') {
            $type = ($type == 'newspaper') ? 'newspapers' : 'magazines';
            $path = '/' . $type . '/' . $id . '/details';
            return Redirect::to($path, $status = 302);
        } elseif (in_array($type, ['top_story', 'popular_content']) && $id != '') {
            $type = ($type == 'popular_content') ? 'promoted' : 'topstory';
            $path = '/' . $type . '/' . $id . '/details';
            return Redirect::to($path, $status = 302);
        } elseif ($type === 'video' && $id !== '') {
            return redirect()->route('video.view', ['video' => $id]);
        } elseif ($type === 'podcast' && $id !== '') {
            return redirect()->route('podcast.view', ['podcast' => $id]);
        } else {
            return Redirect::to('/', $status = 302)->with('error', 'Invalid URL Given.');
        }
    }

    public function check_coupon(Request $request)
    {
        $coupon = $request->get('coupon');
        $coupon = \App\Models\CouponCode::checkCode(strtoupper($coupon));

        return \App\Api\ApiResponse::ok(__('Coupon'), [
            'valid' => !empty($coupon),
            'coupon' => $coupon
        ]);
    }

    public function reloadCaptcha()
    {
        app('mathcaptcha')->reset();

        return response()->json([
            'mathcaptchaLabel'  => app('mathcaptcha')->label(),
        ]);
    }

    public function verifyLinkF($token)
    {
        $verifyuser = VerifyUser::where('token', $token)->first();
        
        if (isset($verifyuser)) {
            $user = $verifyuser->user;

            $redirect = redirect('/topstory');
            $message = '';

            $user_role = request()->query('smp');

            if (!$user->verified) {

                $verifyuser->user->verified = 1;
                $verifyuser->user->save();
                $email = $user->email;

                if( $user_role != 'vendor' ) {

                    try {
                        Mail::send(
                            'customer.email.confirmemail',
                            array(
                                'name' => $user->first_name . " " . $user->last_name,
                                'email' => $user->email
                            ),
                            function ($message) use ($email) {

                                $message->to($email)->subject('Confirmation Email');
                            }
                        );
                    } catch (\Exception $e) {
                        logger(' issue: ' . $e->getMessage());
                    }
                }

                $message = 'Your email is verified. You can now log in';
            } else {
                $message = 'Your email is already verified. You can log in.';
            }

            if( $user_role == 'vendor' ) {
                $redirect = redirect('/vendor');
            }

            return $redirect->withSuccess($message);
        }

        return redirect()->route('home')
            ->with('error', 'Something Went Wrong.');
    }
}
