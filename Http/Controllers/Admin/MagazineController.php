<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\MagazinesTrait;

class MagazineController extends Controller
{
    use MagazinesTrait;

    protected $view_file = 'vendoruser';
    protected $route_path = 'admin';
}
