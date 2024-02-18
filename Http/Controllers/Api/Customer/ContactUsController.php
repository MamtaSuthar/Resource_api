<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use App\Models\ContactUs;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class ContactUsController extends ApiController
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name'         => ['required'],
            'email'         => ['required'],
            'feedback'         => ['required'],
        ], []);

        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }

        $user = null;
        if( $userid = intval($request->get('user_id')) ) {
            $user = User::where('id', $userid)->first();
        }

        $phone = ($user && $user->phone) ? $user->phone : $request->get('phone');
        $email = ($user && $user->email) ? $user->email : $request->get('email');

        try {
            $now = date('Y-m-d H:i');

            $contact = new ContactUs();
            $contact->user_id       = $user ? $user->id : null;
            $contact->full_name     = $request->get('full_name');
            $contact->phone_number  = $phone;
            $contact->email         = $email;
            $contact->subject       = $request->get('subject');
            $contact->feedback      = $request->get('feedback');
            $contact->created_at    = $now;
            $contact->updated_at    = $now;
            $contact->save();

            if ($contact) {

                try {
                    Mail::send(
                        'contactus/contact_mail',
                        array(
                            'name' => $request->get('full_name') ?? '--no name--',
                            'email' => $email,
                            'subject' => $request->get('subject') ?? '--no-subject--',
                            'phone_number' => $phone,
                            'user_message' => $request->get('feedback') ?? '--no-feedback--',
                        ),
                        function ($message) use ($request, $email) {
                            $message->from($email);
                            $message->to('support@graphicnewsplus.com')->subject($request->subject);
                        }
                    );
                } catch(\Exception $e) {
                    logger('api contactus mail - ' .$e->getMessage());
                }

                return response()->json([
                    "STATUS" => 1,
                    "MESSAGE" => "Thank you for contacting us"
                ]);
            }
        } catch (\Exception $e) {
            logger('api - contact us: ' . $e->getMessage());
        }

        return response()->json([
            "STATUS" => 0,
            "MESSAGE" => "Something went wrong"
        ]);
    }
}
