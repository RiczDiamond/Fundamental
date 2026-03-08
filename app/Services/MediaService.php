<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MediaService
{
    public function optimizeImage($absolutePath)
    {
        $path = (string)$absolutePath;
        if ($path === '' || !is_file($path)) {
            return false;
        }

        if (!class_exists(ImageManager::class) || !class_exists(Driver::class)) {
            return false;
        }

        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($path);
            $width = $image->width();
            if ($width > 2200) {
                $image = $image->scaleDown(2200);
            }
            $image->save($path, quality: 82);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
