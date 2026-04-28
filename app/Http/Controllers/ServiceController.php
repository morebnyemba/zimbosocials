<?php
// app/Http/Controllers/ServiceController.php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Service::active()->orderBy('category')->orderBy('display_order');

        if ($cat = $request->query('category')) {
            $query->byCategory($cat);
        }

        $services   = $query->get();
        $categories = Service::active()->distinct()->orderBy('category')->pluck('category');

        return Inertia::render('Services', [
            'services'   => $services,
            'categories' => $categories,
        ]);
    }
}
