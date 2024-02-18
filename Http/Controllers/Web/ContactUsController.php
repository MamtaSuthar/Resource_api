<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\ContactUs;
class ContactUsController extends Controller
{
    public function storeContactForm(Request $request)
    {
        $request->validate([
            'name'    => ['nullable', 'string','min:3','max:191'],
            'email'   => ['nullable','email','max:25'],
            'phone'   => ['required', 'digits_between:8,12'],
            'message' => ['required', 'max:5000'],
        ]);

        $input = $request->all();

        ContactUs::create($input);

        try{
            Mail::send('customer/home/web/contactus/contact', array( 
                'name' => $input['name'], 

                'email' => $input['email'], 

                'phone' => $input['phone'], 

                'subject' => $input['subject'] ?  $input['subject'] : "User Enquiry", 

                'message' => $input['message'], 

            ), function($message) use ($request){ 
                $message->from($request->email); 
                $message->to('amit.tomar@unyscape.com', 'Admin')->subject($request->get('subject')); 
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }

        return redirect()->back()->with(['success' => 'Contact Form Submit Successfully']); 

    } 
}
