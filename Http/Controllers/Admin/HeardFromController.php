<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeardFrom;
use Illuminate\Http\Request;
use App\Models\User;

class HeardFromController extends Controller
{
    public function index(Request $request)
    {
        if( $request->isMethod('post') ) {

            $content = intval($request->get('content'));
            $action = $request->post('action');

            if( $content && ($content = HeardFrom::find($content)) ) {
                
                $_message = '';

                if( $action === 'status_change' ) {
                    $content->status = boolval($content->status) ? 0 : 1;
                    $content->update();

                    $_message = 'Status Updated';
                }

                else if( $action === 'delete' ) {
                    $content->delete();

                    $_message = 'Deleted';
                }

                else {
                    return back();
                }

                return back()->withInfo($_message);
            }
        }

        $collection = HeardFrom::all();
        foreach($collection as $value){
          $user_counts =   User::where('referred_from',$value->title)->get()->count();
          $value->user_counts = $user_counts;
        }

        return view('admin.heard_from.index', compact('collection'));
    }

    public function update(Request $request, HeardFrom $heard_from)
    {
        $request->validate([
            'title' => ['required', 'max:1000']
        ]);

        $heard_from->title = $request->get('title');
        $heard_from->update();

        return back()->withSuccess('Updated Successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => ['required', 'max:1000']
        ]);

        $heard_from = new HeardFrom([
            'title' => $request->get('title')
        ]);

        $heard_from->save();

        return back()->withSuccess('Created Successfully');
    }
}
