<?php
namespace App\Vars;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SystemMails {

    private $mail_info = [
        'to_email' => '',
        'subject' => 'No Subject'
    ];

    private $data = [];

    public static function payment_success(User $user, Payment $payment)
    {
        try{
            Mail::send('mail/payment/successmail', array( 
                'name' => $user->first_name,
                'currency'=>$payment->currency,
                'amount' => $payment->amount,

            ), function($message) use ($user){ 
                $message->from('admin@magazine.com');
                $message->to($user->email, 'User')->subject("Payment Successful");
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }

    public static function payment_failed(User $user, Payment $payment)
    {
        try{
            Mail::send('mail/payment/paymentfail', array( 
                'name' => $user->first_name,
                'currency'=>$payment->currency,
                'amount' => $payment->amount,

            ), function($message) use ($user){ 
                $message->from('admin@magazine.com'); 
                $message->to($user->email, 'User')->subject("Payment Failed");
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }

    public static function payment_refund_initiated(User $user, $amount)
    {
        try{
            Mail::send('mail/customer/refund_initiated', array( 
                'name' => $user->first_name,
                'amount' => Helper::to_price($amount),

            ), function($message) use ($user){ 
                $message->from('admin@magazine.com'); 
                $message->to($user->email, 'User')->subject("Refund Processed");
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }

    public static function customer_subscription_expired(User $user, $renew_link)
    {
        try{
            Mail::send('mail/customer/subscription_expired', array( 
                'name' => $user->first_name,
                'renew_link' => $renew_link,

            ), function($message) use ($user){
                $message->from('admin@magazine.com');
                $message->to($user->email, 'User')->subject("Renew Subscription"); 
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }

    public static function customer_new_registration(User $user, $package_name = '')
    {
        try{
            Mail::send('customer.email.confirmemail',
                array(
                    'name' => $user->first_name." ".$user->last_name,
                    'email' => $user->email,
                    'package_name' => $package_name
                ), function($message) use ($user)
                {
                    $message->from("admin@magazine.com");
                    $message->to($user->email)->subject('Confirmation Email');
                });
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }

    public static function admin_new_vendor(User $vendor)
    {
        $data = [
            'name' => $vendor->first_name,
            'email' => $vendor->email,
            'phone' => $vendor->phone
        ];

        $mailFrom = config('mail.from');

        $instance = new static(
            $mailFrom['address'],
            'New Vendor Added: ' . $vendor->email,
            $data,
            'mail.admin.newvendor'
        );

        $instance->send_mail();
    }

    protected function __construct($to_email = '', $subject = '', $data = [], $view_file = '')
    {
        $this->mail_info = [
            'to_email' => $to_email,
            'subject' => $subject
        ];

        $this->data = \array_merge($this->data, $data);

        $this->view_file = $view_file;
    }

    public function setToMail($to_email)
    {
        $this->mail_info['to_email'] = $to_email;
        return $this;
    }

    public function setSubject($subject)
    {
        $this->mail_info['subject'] = $subject;
        return $this;
    }

    public function setData($data)
    {
        $this->data = \array_merge($this->data, $data);
        return $this;
    }

    public function setViewFile($view_file)
    {
        $this->view_file = $view_file;
        return $this;
    }

    public function send_mail()
    {
        try{

            $mailFrom = config('mail.from');

            $from_email = $mailFrom['address'];

            $to_email = $this->mail_info['to_email'];
            $subject = $this->mail_info['subject'];

            Mail::send($this->view_file, $this->data,
                function($message) use ($from_email,$to_email,$subject) {
                    $message->from($from_email);
                    $message->to($to_email)->subject($subject);
                });
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }

        return false;
    }
}