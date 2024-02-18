<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Vendor\PremiumContentController as Controller;
// use Illuminate\Http\Request;
use App\Traits\NewspaperTrait;


class NewspaperController extends Controller
{

    use NewspaperTrait;

    protected $view_file = 'vendoruser';
    protected $route_path = 'vendor';
    
}
