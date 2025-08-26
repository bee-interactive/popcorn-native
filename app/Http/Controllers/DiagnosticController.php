<?php

namespace App\Http\Controllers;

use App\Services\ImageCacheService;
use Illuminate\Http\Request;

class DiagnosticController extends Controller
{
    public function imageSupport()
    {
        $imageCacheService = new ImageCacheService();
        $supportInfo = $imageCacheService->getImageSupportInfo();
        
        // Add PHP version info
        $supportInfo['php_version'] = PHP_VERSION;
        $supportInfo['php_sapi'] = PHP_SAPI;
        
        // Add all loaded extensions
        $supportInfo['loaded_extensions'] = get_loaded_extensions();
        
        return response()->json($supportInfo);
    }
}