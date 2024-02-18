<?php

namespace App\Vars;

class LinkPdfFile {

    public static function process($file_path, $date = null)
    {
        if( !file_exists($file_path) ) {
            return false;
        }

        $script_v = app_path('Vars/PyPdf/env/bin/python');

        $script_path = app_path('Vars/PyPdf/linkheadline.py');

        $execute = "{$script_v} {$script_path} {$file_path}";

        if( $date && ($date = \strtotime($date)) ) {
            $execute .= ' '.date('Y-m-d', $date);
        }

        $output = trim( shell_exec(escapeshellcmd($execute).' 2>&1') );

        if( $output == '1' ) {
            $file_name = \basename($file_path);

            return 'linked-'.$file_name;
        }
        
        return 0;
    }
}