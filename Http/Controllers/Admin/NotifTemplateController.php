<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NotifTemplate;
use App\Vars\NotificationEvents;
use Illuminate\Http\Request;

class NotifTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // dd($request->all());
        $notifications = NotificationEvents::all();

        $db_notifications = NotifTemplate::query()
            ->with(['restrictions'])
            ->get();

        // for new_blogs
        $blog_categories = Category::active()->get();

        if( $request->isMethod('post') ) {

            $validated = $request->validate(
                NotificationEvents::allEventsValidationRules()
            );
            
            try {

                foreach( $notifications as  $notification) {
                    $event = $notification['event'];

                    $db_notif = $db_notifications
                        ->where('event', $event)
                        ->first();
                
                    $age_restrictions = (array)$request->get($event.'_ar');
                    $gender_restrictions = (array)$request->get($event.'_gender');
    
                    if( $db_notif ) {
                        $db_notif->title = $validated[$event.'_title'] ?? '';
                        $db_notif->content = $validated[$event.'_content'] ?? '';
                        // $db_notif->age_group = $request->get($event.'_ar') ?? 'all';
                        // $db_notif->gender = $request->get($event.'_gender') ?? 'all';
                        $db_notif->update();
                    } else {
                        $db_notif = NotifTemplate::create([
                            'title' => $validated[$event.'_title'] ?? '',
                            'content' => $validated[$event.'_content'] ?? '',
                            'event' =>  $event,
                            // 'age_group' => $request->get($event.'_ar') ?? 'all',
                            // 'gender' => $request->get($event.'_gender') ?? 'all'
                        ]);
                    }

                    $restrictions = [];
                    foreach( $age_restrictions as $key => $age_restriction ) {
                        if( $event == 'new_blogs' ) {
                            $category_id = $key;
                        } else {
                            $category_id = 0;
                        }

                        $restrictions[] = new \App\Models\NotificationRestriction([
                            'notif_template_id' => $db_notif->id,
                            'category_id' => $category_id,
                            'age_group' => $age_restriction,
                            'gender' => $gender_restrictions[$key] ?? 'all'
                        ]);
                    }

                    $db_notif->restrictions()->delete();
                    $db_notif->restrictions()->saveMany($restrictions);
                }

            } catch(\Exception $e) {
                dd($e);
                logger($e->getMessage());
                
                return back()->withError('Something went wrong. A00DB');
            }

            return back()->withSuccess('Templates Updated');
        }

        return view('admin.notif_templates.index', compact('notifications', 'db_notifications', 'blog_categories'));
    }
}
