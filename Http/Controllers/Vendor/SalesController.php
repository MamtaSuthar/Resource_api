<?php

namespace App\Http\Controllers\Vendor;

use App\Exports\VendorSalesExport;
use App\Http\Controllers\Controller;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        if( in_array($request->get('export_filetype'), ['pdf', 'excel']) ) {
            return $this->export_listing($request);
        }

        $user = $this->user();

        $user_id = $user->id;

        // get direct purchase of thise vendor's magazines
        $magazines = $this->bought_magazines($request);

        $magazines_sold = $magazines->reduce(function($acc, $magazine) {
            return $acc += $magazine->users_who_bought_count;
        }, 0);

        // get direct purchase of thise vendor's newspapers
        $newspapers = $this->bought_newspaper($request);

        $newspapers_sold = $newspapers->reduce(function($acc, $newspaper) {
            return $acc += $newspaper->users_who_bought_count;
        }, 0);

        return view('vendoruser.sales.direct_sales_index', compact('magazines', 'magazines_sold', 'newspapers', 'newspapers_sold'));
    }

    public function export_listing($request)
    {
        /** @var \App\Models\User $user */
        $user = $this->user();

        $filetype = $request->get('export_filetype');

        $collection = $this->bought_magazines($request)
            ->concat($this->bought_newspaper($request));

        if( $filetype === 'pdf' ){

            $mpdf = new \Mpdf\Mpdf(
                [
                    'tempDir' => storage_path('temp'),
                    'mode' => 'utf-8',
                    'format' => 'A4-L',
                    'orientation' => 'L'
                ]
            );

            $mpdf->WriteHTML(
                view('vendoruser.sales.export_pdf', compact('collection'))->render()
            );

            return $mpdf->Output('ContentListing.pdf', 'D');
        }

        else if( $filetype === 'excel' ) {
            return Excel::download(
                new VendorSalesExport($collection),
                'ContentListing.xls'
            );
        }

        return back();
    }

    protected function bought_magazines(Request $request)
    {
        $user = $this->user();

        $from = strtotime($request->get('date_from'));
        $to = strtotime($request->get('date_to'));

        $content = $user->magazines()
            ->whereHas('users_who_bought', function($query) use($from,$to) {
                $query->where('pay_status', '1');
                if( $from ) {
                    $query->whereDate('bought_at', '>=', date('Y-m-d', $from));
                }
                if( $to ) {
                    $query->whereDate('bought_at', '<=', date('Y-m-d', $to));
                }
            })
            ->withCount('users_who_bought')
            ->with(['category', 'publication', 'users_who_bought']);

        return $content->get();
    }

    protected function bought_newspaper(Request $request)
    {
        $user = $this->user();

        $from = strtotime($request->get('date_from'));
        $to = strtotime($request->get('date_to'));

        return $user->newspapers()
            ->whereHas('users_who_bought', function($query) use($from,$to) {
                $query->where('pay_status', '1');
                if( $from ) {
                    $query->where('bought_at', '>=', date('Y-m-d', $from));
                }
                if( $to ) {
                    $query->where('bought_at', '<=', date('Y-m-d', $to));
                }
            })
            ->withCount('users_who_bought')
            ->with(['category', 'publication', 'users_who_bought'])
            ->get();
    }
}
