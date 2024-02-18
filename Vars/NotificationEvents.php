<?php

namespace App\Vars;

class NotificationEvents {
    const NEW_MAGAZINE = [
        'event' => 'new_magazine',
        'label' => 'New Magazine Added',
        'desc' => 'vars to use',
        'required' => 1
    ];

    const NEW_NEWSPAPER = [
        'event' => 'new_newspaper',
        'label' => 'New Newspaper Added',
        'desc' => 'vars to use',
        'required' => 1
    ];

    const NEW_BLOGS = [
        'event' => 'new_blogs',
        'label' => 'New Blogs Added',
        'desc' => 'vars to use',
        'required' => 1
    ];

    public static function all()
    {
        return [
            self::NEW_MAGAZINE,
            self::NEW_NEWSPAPER,
            self::NEW_BLOGS
        ];
    }

    public static function allEvents()
    {
        return \array_map(function($event) {
            return $event['event'];
        }, self::all());
    }

    public static function allEventsValidationRules()
    {
        $rules = [];
        foreach( self::all() as $event ) {
            $rule_title = ['max:191'];
            $rule_content = ['max:5000'];
            
            if( $event['required'] ) {
                $rule_title[] = 'required';
                $rule_content[] = 'required';
            }

            $rules[ $event['event'] . '_title' ] = $rule_title;
            $rules[ $event['event'] . '_content' ] = $rule_content;
        }

        return $rules;
    }
}