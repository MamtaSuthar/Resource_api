<?php

namespace App\Http\Controllers;

use App\Exports\DownloadReportExport;
use App\Exports\DownloadSubscriptionFile;
use App\Exports\UserReportExport;
use App\Models\ActivityCount;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Models\UserSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use PDF;
use DB;
use Maatwebsite\Excel\Excel;

class ReportController extends Controller
{
    use CommonTrait;
    public function userreport(Request $request,$type){
        # User Query Instance
        $query = User::whereHas('roles', function($q){
            $q->where('name', '<>', User::SUPERADMIN);
        });
        # Handle Filter Queries
        // if($request->has('subsc_type') && !is_null($request->subsc_type)){
        //     $query->whereHas('subscription', function($q) use ($request){
        //         $q->where('status', $request->input('subsc_type'));
        //     });
        // }
        # Filter Users By Type
        // $type = $request->input('type');
        if(in_array($type, [User::VENDOR, User::CUSTOMER])){
            $query->whereHas('roles', function($q) use ($type){
                $q->where(['name' => $type]);
            });
        }
        $query = $this->checkFilterValuesforReport($request,$query);
        # Get Users Collection
        $users = $query->orderBy('id','DESC')->get();
        $countries = User::whereHas('roles', function($q) use ($type){
            $q->where(['name' => $type]);
        })->pluck('country')->unique()->filter();
        return view('admin.report.userreport', compact('users','type','countries'));
    }
    public function getuserReport(Request $request,$type,$filetype){
        # User Query Instance
        $query = User::whereHas('roles', function($q){
            $q->where('name', '<>', User::SUPERADMIN);
        });
        if(in_array($type, [User::VENDOR, User::CUSTOMER])){
            $query->whereHas('roles', function($q) use ($type){
                $q->where(['name' => $type]);
            });
        }
        // $query = $this->checkFilterValuesforReport($request,$query);
        if( $starts_at = \strtotime($request->starts_at) ) {
            $query->whereDate('created_at', '>=', date('Y-m-d H:i:s', $starts_at));
        }
        if( $starts_at = \strtotime($request->ends_at) ) {
            $query->whereDate('created_at', '<=', date('Y-m-d H:i:s', $starts_at));
        }
        $users = $query->orderBy('id','DESC')->get();
        if($filetype=='pdf'){
            $mpdf = new \Mpdf\Mpdf(
                ['tempDir' => storage_path('temp')]
            );
            $htmlData =view('admin.report.pdfViews.userPDF', compact('users','type'))->render();
            $mpdf->WriteHTML($htmlData);
            $mpdf->Output("UserReport.pdf", "D");
        }else{
            $user = new UserReportExport($users,$type);
            return \Excel::download($user,'UserReport.xls');
        }
    }
    //==========================================================================================================
    //                      For download report
    //==========================================================================================================
    public function download_report(Request $request){
        $getUsers = DB::table('user_downloads')->pluck('user_id')->unique()->values();
        $users = User::whereIn('id',$getUsers->all());
        $users = $this->downloadFilterCheck($request,$users);
        $users = $users->get();
        
        return view('admin.report.download_report',compact('users'));
    }

    private function downloadFilterCheck($request,$query){
        if($request->has('email') && $request->email ){
            $query = $query->where('email','LIKE',"%".$request->email."%");
        }
        return $query;
    }
    public function download_report_file(Request $request){
        $type = $request->type;
        if($request->type=='main'){
            $getUsers = DB::table('user_downloads')->pluck('user_id')->unique()->values();
            $users = User::whereIn('id',$getUsers->all());
            $users = $this->downloadFilterCheck($request,$users);
            $users = $users->get();
            if($request->file_type=='pdf'){
                $mpdf = new \Mpdf\Mpdf(
                    ['tempDir' => storage_path('temp')]
                );
                $htmlData =view('admin.report.pdfViews.download_report_file', compact('users','type'))->render();
                $mpdf->WriteHTML($htmlData);
                $mpdf->Output("File Download Report.pdf", "D");
            }else{
                $user = new DownloadReportExport($users,$type);
                return \Excel::download($user,'File Download Report.xls');
            }
        }elseif ($type=='by_user') {
            $Covertype = $request->Cover_type;
            $user = User::find($request->usersID);
            if($Covertype=="magazine"){
                $users = $user->magazine_downloads()->get();
            }else{
                $users = $user->newspaper_downloads()->get();
            }
            if($request->file_type=='pdf'){
                $mpdf = new \Mpdf\Mpdf(
                    ['tempDir' => storage_path('temp')]
                );
                $htmlData =view('admin.report.pdfViews.download_report_file', compact('users','type'))->render();
                $mpdf->WriteHTML($htmlData);
                $mpdf->Output("File Download Report.pdf", "D");
            }else{
                $user = new DownloadReportExport($users,$type);
                return \Excel::download($user,'File Download Report.xls');
            }
        }
    }
    public function download_report_info(Request $request,$userid,$type){
        $user = User::find($userid);
            $data = $user;
            if($request->has('title') && $request->title ){
                if($type == "magazine"){
                    $data = $data->magazine_downloads()->where('title','LIKE',"%".$request->title."%")->get();
                }else{
                    $data = $user->newspaper_downloads()->where('title','LIKE',"%".$request->title."%")->get();
                }
                
            }else{
                if($type == "magazine"){
                    $data = $user->magazine_downloads()->get();
                }else{
                    $data = $user->newspaper_downloads()->get();
                }
            }
            
        return view('admin.report.download_report_info',compact('data','user','type'));
        
    }

    //==========================================================================================================
    //                      For subscription report
    //==========================================================================================================
    //Plan Report
    public function subscription_report(Request $request){
        $Subscriptiondata = UserSubscription::where('pay_status',1);

        if( $starts_at = \strtotime($request->query('start_date')) ) {
            $Subscriptiondata->whereDate('subscribed_at', '>=', date('Y-m-d H:i:s', $starts_at));
        }
        if( $starts_at = \strtotime($request->query('end_date')) ) {
            $Subscriptiondata->whereDate('subscribed_at', '<=', date('Y-m-d H:i:s', $starts_at));
        }

        $data = Plan::whereIn('id',$Subscriptiondata->pluck('plan_id')->unique()->all());
        $data = $this->getsubscriptionfilters($request,$data);
        $data = $data->with([
            'publications',
            'getUserSubscriptions' => function($query) {
                $query->where('pay_status', 1);
            }
        ]);

        $data = $data->get();

        return view('admin.report.subscriptions_report',compact('data'));
    }

    public function getsubscriptionfilters($request,$query){
        if($title = $request->get('title')){
            $query = $query->where('title','LIKE','%'.$title.'%');
        }
        if($type = $request->get('type')){
            $query = $query->where('type', $type);
        }
        $status = strval($request->get('status'));
        if(in_array($status, ['1', '0'])){
            $query = $query->where('status', $status);
        }
        return $query;
    }

    public function Usersubscription_report($pid,$status){
        $usersubscription = UserSubscription::where('plan_id',$pid)->where('pay_status',1);
        if($status!=0){
            $querydata = $usersubscription->where('expires_at','>=',now());
        }else{
            $querydata = $usersubscription->where('expires_at','<',now());
        }

        if( $user_name_email = request()->get('email') ) {
            $usersubscription->whereHas('user', function($query) use($user_name_email) {
                $query->where('first_name', 'like', "%{$user_name_email}%")
                    ->orWhere('last_name', 'like', "%{$user_name_email}%" )
                    ->orWhere('email', 'like', "%{$user_name_email}%" );
            });
        }

        $data = $querydata->orderBy('subscribed_at','desc')->get();
        return view('admin.report.Users_subscriptions_report',compact('data','pid','status'));
    }

    public function download_subscription_file(Request $request) {

        // if generating for a particular pland id
        if( $pid = $request->get('planid') ) {

            $status = $request->get('status') == '1' ? 1 : 0;
            $usersubscription = UserSubscription::where('plan_id', $pid)->where('pay_status',1);
            if($status!=0){
                $querydata = $usersubscription->where('expires_at','>=',now());
            }else{
                $querydata = $usersubscription->where('expires_at','<',now());
            }

            if( $starts_at = \strtotime($request->query('start_date')) ) {
                $querydata->whereDate('subscribed_at', '>=', date('Y-m-d H:i:s', $starts_at));
            }
            if( $starts_at = \strtotime($request->query('end_date')) ) {
                $querydata->whereDate('subscribed_at', '<=', date('Y-m-d H:i:s', $starts_at));
            }

            if( $user_name_email = request()->get('email') ) {
            $usersubscription->whereHas('user', function($query) use($user_name_email) {
                $query->where('first_name', 'like', "%{$user_name_email}%")
                    ->orWhere('last_name', 'like', "%{$user_name_email}%" )
                    ->orWhere('email', 'like', "%{$user_name_email}%" );
                });
            }
            $data = $querydata->get();

            $type = 'user';
        }

        // generating all plans
        else {
            $Subscriptiondata = UserSubscription::where('pay_status',1);

            if( $starts_at = \strtotime($request->query('start_date')) ) {
                $Subscriptiondata->whereDate('subscribed_at', '>=', date('Y-m-d H:i:s', $starts_at));
            }
            if( $starts_at = \strtotime($request->query('end_date')) ) {
                $Subscriptiondata->whereDate('subscribed_at', '<=', date('Y-m-d H:i:s', $starts_at));
            }

            $planQuery = Plan::whereIn('id',$Subscriptiondata->pluck('plan_id')->unique()->all());
            // $data = $this->download_subscriptionfilters($request,$data);
            $data = $this->getsubscriptionfilters($request, $planQuery);

            if( $user_name_email = request()->get('email') ) {
            $usersubscription->whereHas('user', function($query) use($user_name_email) {
                $query->where('first_name', 'like', "%{$user_name_email}%")
                    ->orWhere('last_name', 'like', "%{$user_name_email}%" )
                    ->orWhere('email', 'like', "%{$user_name_email}%" );
                });
            }
            $data = $data->get();

            $type = 'main';
        }

        if($request->file_type == "excel"){
            $user = new DownloadSubscriptionFile($data, $type);
            return \Excel::download($user,'File Download Report.xls');
        }
        elseif($request->file_type == "pdf"){
            $mpdf = new \Mpdf\Mpdf(
                ['tempDir' => storage_path('temp')]
            );
            $htmlData =view('admin.report.pdfViews.download_subscription_file', compact('data','type'))->render();
            $mpdf->WriteHTML($htmlData);
            $mpdf->Output("File Download Report.pdf", "D");
        }

        abort(404);
    }

    public function download_subscriptionfilters(Request $request){
        if($request->has('title') && $request->title){
            $data = $data->where('title','LIKE','%'.$request->title.'%');
        }
        return $data;
    }

    public function ad_reading_views_report(Request $request)
    {
        // ads is for banner
        // reading for magazine,newspaper

        $activities = ActivityCount::query()
            ->with('user');

        if( $email = $request->query('email') ) {

            if( \filter_var($email, FILTER_VALIDATE_EMAIL) ) {

                $activities->whereHas('user',
                    function($query) use($email) {
                        $query->where('email', 'like', "%{$email}%");
                    });
            }
        }

        $activities = $activities
            ->take(20)
            ->get()
            ->map(function($activity) {

                $data = [
                    'ads' => 0,
                    'magazine' => [
                        'list' => [],
                        'count' => 0
                    ],
                    'newspaper' => [
                        'list' => [],
                        'count' => 0
                    ],
                    'user' => $activity->user ?? new \App\Models\User()
                ];

                // ads, reading
                $types = \json_decode($activity->type, true);

                // ads
                $data['ads'] = intval($types['ads'] ?? 0);

                // magazines, newspaper
                $magazine_newspapers = \json_decode($activity->file_id, true);

                foreach( $magazine_newspapers as $type => $ids ) {
                    $ids = \array_count_values($ids);

                    if( $type == 'magazine' ) {
                        $model = Magazine::query();
                    }
                    else if( $type == 'newspaper' ) {
                        $model = Newspaper::query();
                    }

                    if( $model ) {
                        $temp = [];

                        $contents = $model->find(\array_keys($ids));

                        foreach( $contents as $content ) {
                            $temp[] = [
                                'count' => intval($ids[$content->id]),
                                'item' => $content
                            ];
                        }

                        if( !empty($temp) ) {
                            $data[$type]['list'] = $temp;
                            $data[$type]['count'] = \array_sum($ids);
                        }
                    }
                }

                return $data;
            });

        return view('admin.report.ad_reading_views_report', compact('activities'));
    }
    
}
