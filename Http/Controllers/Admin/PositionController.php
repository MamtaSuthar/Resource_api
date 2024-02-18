<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;


class PositionController extends Controller
{
    //

     public function index()
     {
        $positions = Position::all();
        $filterd = $positions->sortBy('position');
        $posts = $filterd->values();
        // $posts = Position::orderBy('position','ASC')->get();

         return view('admin.position.position',compact('posts'));
     }

     // this function update all data and orders accordin put or pull to frontend

     public function update(Request $request)
     {
         $posts = Position::all();

         foreach ($posts as $post) {
             foreach ($request->pos as $position) {
                 if ($position['id'] == $post->id) {
                     $post->update(['position' => $position['position']]);
                 }
             }
         }
         
         return response()->json(["status"=>'Updated Successfully']);
     }
}
