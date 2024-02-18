<?php

namespace App\Vars;

use Intervention\Image\Facades\Image as ImageManager;

// Image manipulation service

// sudo apt install php7.4-common gcc
// sudo apt install imagemagick
// sudo apt install php7.4-imagick
class Image {

    const THUMBNAIL = [400, 527];

    // cover 1000 x 1400

    public static function thumbnail($path, $newPath = null) {

        if( !file_exists($path) || !is_readable($path) ) {
            return false;
        }

        [$width, $height] = self::THUMBNAIL;

        try {
            $image = ImageManager::make($path);

            $image->resize($width, $height);
            
            $image->save($newPath ?? $path);

            return basename($newPath ?? $path);
        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return false;
    }
}