<?php

namespace App\Traits;

use App\Http\Controllers\FileController;

trait HasMaskImage
{

    private function generateMaskedImageAppUrl($path)
    {
        $filePath = FileController::encryptDecrypt($path) . '_masked.png';
        return route('file.getFile', ['type' => 'image', 'path' => $filePath]);
    }

}
