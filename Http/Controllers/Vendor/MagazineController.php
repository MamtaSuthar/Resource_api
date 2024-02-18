<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Vendor\PremiumContentController as Controller;
use App\Traits\MagazinesTrait;

class MagazineController extends Controller
{
    use MagazinesTrait;

    protected $view_file = 'vendoruser';
    protected $route_path = 'vendor';

}
