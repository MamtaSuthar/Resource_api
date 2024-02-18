<?php
namespace App\Traits;

use App\Exports\MagazineNewspaperExport;
use App\Exports\CouponExport;
use App\Exports\CouponUserExport;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Models\CouponCode;
use App\Models\UserUsedCoupon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

trait FileProcessingTrait
{
    public function export_listing(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();

        $request->validate([
            'content_type' => ['required', 'in:magazine,newspaper'],
            'filetype' => ['required', 'in:pdf,excel']
        ]);

        $filetype = $request->query('filetype');

        $content_type = $request->get('content_type');

        $content = $content_type == 'magazine'
            ? Magazine::query()
            : Newspaper::query();

        if( $user->isVendor() ) {
            $content->where('user_id', $user->id);
        }
        
        $collection = $content
            ->with(['vendor', 'publication', 'category'])
            ->active()->latest()->get();

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
                view('vendoruser.magazines.pdf', compact('collection','content_type'))->render()
            );

            return $mpdf->Output('ContentListing.pdf', 'D');
        }

        else if( $filetype === 'excel' ) {
            return Excel::download(
                new MagazineNewspaperExport($collection, $content_type),
                'ContentListing.xls'
            );
        }

        return back();
    }

    // sudo -v && wget -nv -O- https://download.calibre-ebook.com/linux-installer.sh | sudo sh /dev/stdin
    protected function epub_to_pdf($ebook_file_path, $pdf_file_path)
    {
        if( is_readable($ebook_file_path) ) {
            $output = [];
            exec("ebook-convert $ebook_file_path $pdf_file_path", $output);

            if( file_exists($pdf_file_path) ) {
                return $pdf_file_path;
            }
        }

        return false;
    }

    protected function update_pdf($pdf_file_path, $output_file_path = null, $add_watermark = true, $page_count_percent = 0)
    {
        $watermark = public_path('assets/frontend/img/logo_big_wr.png');

        if( file_exists($pdf_file_path) ) {
            try {

                if( !file_exists($watermark) && $add_watermark ) {
                    throw new \Exception('watermark logo does not exist');
                }

                $pdf = new \Mpdf\Mpdf(
                    ['tempDir' => storage_path('temp')]
                );
    
                $pdf->SetAutoPageBreak(false);

                $page_count = 1;
                $totalPages = $pdf->setSourceFile($pdf_file_path);

                if( $page_count_percent > 0 ) {
                    // $page_count = intval(floor($totalPages * ($page_count_percent/100)));
                    // $page_count = $page_count > 0 ? $page_count : 1;
                    $page_count = $totalPages > 2 ? 2 : 1;
                } else {
                    $page_count = $totalPages;
                }

                for( $i =1; $i<=$page_count; $i++ ) {
                    $pdf->AddPage();
                    $pdf->useTemplate($pdf->importPage($i));

                    if( $add_watermark ) {
                        $pdf->SetWatermarkImage($watermark, 0.1);
                        $pdf->showWatermarkImage = true;
                    }

                    if( $page_count_percent > 0 ) {
                        $pdf->SetWatermarkText('PREVIEW');
                        $pdf->showWatermarkText = true;
                    }
                }

                // new file path or just replace the old
                $finalPath = $output_file_path ?? $pdf_file_path;
    
                $pdf->Output(
                    $finalPath,
                    \Mpdf\Output\Destination::FILE
                );

                return basename($finalPath);
            } catch(\Exception $e) {
                logger($e->getMessage());
            }
        }

        return false;
    }
    
    public function export_listing_coupon(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();

        $request->validate([
            'content_type' => ['required', 'in:magazine,newspaper,coupon'],
            'filetype' => ['required', 'in:pdf,excel']
        ]);

        $filetype = $request->query('filetype');

        $content_type = $request->get('content_type');

        $content = CouponCode::query();
            
        // dd($collection);
        $collection = $content->orderby('id','DESC')->get();

        
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
                view('vendoruser.magazines.couponpdf', compact('collection','content_type'))->render()
            );

            return $mpdf->Output('ContentListing.pdf', 'D');
        }

        else if( $filetype === 'excel' ) {
            return Excel::download(
                new CouponExport($collection, $content_type),
                'ContentListing.xls'
            );
        }

        return back();
    }

    public function export_listing_user_coupon(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();

        $request->validate([
            'content_type' => ['required', 'in:usercoupons'],
            'filetype' => ['required', 'in:pdf,excel'],
            'coupon_code' => ['required']
        ]);
        // dd($request->all());

        $filetype = $request->query('filetype');

        $content_type = $request->get('content_type');
        
        $coupon_code = $request->get('coupon_code');

        $content = UserUsedCoupon::query();
        $content->where('code',$coupon_code)->with(['user']);
        $collection = $content->orderby('id','DESC')->get();
        
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
                view('vendoruser.coupons.couponuserpdf', compact('collection','coupon_code'))->render()
            );

            return $mpdf->Output('CouponUserListing.pdf', 'D');
        }

        else if( $filetype === 'excel' ) {
            return Excel::download(
                new CouponUserExport($collection, $content_type),
                'CouponUserListing.xls'
            );
        }

        return back();
    }
}