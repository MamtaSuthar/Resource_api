<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UserReportExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use PDF;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

class PDFController extends Controller
{
    //
    public function preview()
    {
        return view('preview');
    }

    public function generatePDF()
    {
        $pdf = PDF::loadView('preview');    
        return $pdf->download('demo.pdf');
    }

    public function getPdfByType($type,$filetype){
        $query = User::whereHas('roles', function($q){
            $q->where('name', '<>', User::SUPERADMIN);
        });
        if(in_array($type, [User::VENDOR, User::CUSTOMER])){
            $query->whereHas('roles', function($q) use ($type){
                $q->where(['name' => $type]);
            });
        }
        # Get Users Collection
        $users = $query->orderBy('id','DESC')->get();
        if($filetype=='pdf'){
            $mpdf = new \Mpdf\Mpdf(
                ['tempDir' => storage_path('temp')]
            );
            $htmlData =view('admin.users.userPDF', compact('users','type'))->render();
            $mpdf->WriteHTML($htmlData);
            $mpdf->Output("test.pdf", "D");
        }else{
            $user = new UserReportExport($users,$type);
            return \Excel::download($user,'test.xls');
        }
    }
}
