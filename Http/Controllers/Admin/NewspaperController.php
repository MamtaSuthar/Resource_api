<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\NewspaperTrait;

class NewspaperController extends Controller
{
    use NewspaperTrait;

    protected $view_file = 'vendoruser';
    protected $route_path = 'admin';
}
