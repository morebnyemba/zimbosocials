<?php

namespace App\Http\Controllers;

use App\Services\AdvertPlanImageGenerator;
use Illuminate\Http\Response;

/**
 * Serves the sponsored-advert "plans card" PNG — a public, unauthenticated
 * URL because Meta's servers fetch it directly when the WhatsApp assistant
 * sends it as an image message (see AdvertiseFlow).
 */
class AdvertPlanImageController extends Controller
{
    public function show(AdvertPlanImageGenerator $generator): Response
    {
        return response($generator->render(), 200, [
            'Content-Type' => 'image/png',
            // Short cache: cheap to regenerate, and a price change in
            // config/adverts.php should reach WhatsApp within the hour.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
