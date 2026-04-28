<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppTemplateController extends Controller
{
    public function index(WhatsAppService $whatsapp): Response
    {
        $remote = $whatsapp->listTemplates();
        
        $templates = [];
        if ($remote['ok']) {
            $templates = $remote['templates'];
        }

        $localConfig = config('whatsapp-templates.templates', []);

        return Inertia::render('Admin/WhatsApp/Templates', [
            'remoteTemplates' => $templates,
            'localConfig'     => $localConfig,
            'error'           => $remote['error'] ?? null,
            'provider'        => config('services.whatsapp.provider'),
        ]);
    }

    public function sync(): RedirectResponse
    {
        // Run the sync command and capture output
        Artisan::call('whatsapp:sync-templates');
        
        $output = Artisan::output();

        if (str_contains($output, 'Failed')) {
            return back()->with('error', 'Sync completed with errors. See logs or output.');
        }

        return back()->with('success', 'WhatsApp templates synced successfully with Meta.');
    }

    public function delete(string $name, WhatsAppService $whatsapp): RedirectResponse
    {
        $result = $whatsapp->deleteTemplate($name);

        if ($result['ok']) {
            return back()->with('success', "Template '{$name}' deleted from Meta.");
        }

        return back()->with('error', "Failed to delete template: {$result['error']}");
    }
}
