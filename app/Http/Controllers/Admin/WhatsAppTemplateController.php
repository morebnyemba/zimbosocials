<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppTemplateController extends Controller
{
    public function index(WhatsAppService $whatsapp): Response
    {
        $remote = $whatsapp->listTemplates();

        return Inertia::render('Admin/WhatsApp/Templates', [
            'remoteTemplates' => $remote['ok'] ? $remote['templates'] : [],
            'localTemplates' => WhatsAppTemplate::orderBy('name')->get(),
            'error' => $remote['error'] ?? null,
            'provider' => config('services.whatsapp.provider'),
            'language' => config('whatsapp-templates.language', 'en'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        WhatsAppTemplate::create($data);
        Cache::forget('wa:templates:config');

        return back()->with('success', "Template '{$data['name']}' saved. Push it to Meta when you're ready.");
    }

    public function update(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        // The name is the template's identity on Meta — never editable.
        $data = collect($this->validated($request, $template))->except('name')->all();

        $template->update($data);
        Cache::forget('wa:templates:config');

        $note = $template->wasChanged(['body', 'header', 'footer'])
            ? ' Content changed — push to Meta again (delete the old remote version first) for template sends; the plain-text fallback uses your new wording immediately.'
            : '';

        return back()->with('success', "Template '{$template->name}' updated.{$note}");
    }

    /**
     * Push one local template to Meta. New templates are created; REJECTED or
     * PAUSED remote versions are updated in place (which resubmits them for
     * review — deleting instead would block the name for 30 days).
     */
    public function push(WhatsAppTemplate $template, WhatsAppService $whatsapp): RedirectResponse
    {
        $payload = WhatsAppTemplate::metaPayload(
            $template->name,
            $template->toConfigShape(),
            (string) config('whatsapp-templates.language', 'en')
        );

        $remote = $whatsapp->listTemplates();
        $existing = $remote['ok'] ? collect($remote['templates'])->firstWhere('name', $template->name) : null;

        if ($existing) {
            $status = strtoupper((string) ($existing['status'] ?? ''));

            if (! in_array($status, ['REJECTED', 'PAUSED'], true)) {
                return back()->with('error', "'{$template->name}' is already on Meta ({$status}). To change its wording, delete the remote version first — but note a deleted name is blocked for 30 days.");
            }

            $result = $whatsapp->updateTemplate((string) $existing['id'], $payload);

            return $result['ok']
                ? back()->with('success', "'{$template->name}' was {$status} — resubmitted to Meta with the current content for a fresh review.")
                : back()->with('error', "Meta rejected the update: {$result['error']}");
        }

        $result = $whatsapp->createTemplate($payload);

        return $result['ok']
            ? back()->with('success', "'{$template->name}' submitted to Meta — approval usually takes a few minutes to a few hours.")
            : back()->with('error', "Meta rejected the submission: {$result['error']}");
    }

    /** Remove the local (DB) template. The remote Meta copy is untouched. */
    public function destroyLocal(WhatsAppTemplate $template): RedirectResponse
    {
        $template->delete();
        Cache::forget('wa:templates:config');

        return back()->with('success', "Local template '{$template->name}' deleted. The Meta copy (if any) still exists.");
    }

    public function sync(): RedirectResponse
    {
        Artisan::call('whatsapp:sync-templates');
        $output = Artisan::output();

        if (str_contains($output, 'Failed')) {
            // Surface Meta's actual per-template reasons, not a generic notice.
            preg_match_all('/✗ Failed: (.+)/u', $output, $m);
            $reasons = array_slice(array_unique(array_map('trim', $m[1] ?? [])), 0, 3);

            return back()->with('error', 'Sync completed with errors'.($reasons ? ': '.implode(' | ', $reasons) : '. See logs.'));
        }

        return back()->with('success', 'Templates synced — new ones created and rejected ones resubmitted for Meta review.');
    }

    public function delete(string $name, WhatsAppService $whatsapp): RedirectResponse
    {
        $result = $whatsapp->deleteTemplate($name);

        if ($result['ok']) {
            return back()->with('success', "Template '{$name}' deleted from Meta.");
        }

        return back()->with('error', "Failed to delete template: {$result['error']}");
    }

    private function validated(Request $request, ?WhatsAppTemplate $existing = null): array
    {
        $data = $request->validate([
            // The name is fixed after creation (it's the template's Meta identity).
            'name' => [
                $existing ? 'sometimes' : 'required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('whatsapp_templates', 'name')->ignore($existing?->id),
            ],
            'category' => ['required', Rule::in(WhatsAppTemplate::CATEGORIES)],
            'body' => ['required', 'string', 'max:1024'],
            'header' => ['nullable', 'string', 'max:60'],
            'footer' => ['nullable', 'string', 'max:60'],
            'params' => ['nullable', 'array', 'max:10'],
            'params.*' => ['string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.regex' => 'Template names must be lowercase letters, numbers and underscores only (Meta requirement).',
        ]);

        // Meta rejects ("Invalid parameter") bodies that START or END with a
        // variable — there must be literal text around the placeholders.
        $trimmedBody = trim($data['body']);
        if (preg_match('/^\**\{\{\d+\}\}|\{\{\d+\}\}\**$/', $trimmedBody)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'Meta rejects templates whose body starts or ends with a {{variable}} — add some text before/after it (e.g. a closing "Thank you!").',
            ]);
        }

        // Meta rejects bodies whose {{n}} placeholders aren't 1..N — catch it here.
        preg_match_all('/\{\{(\d+)\}\}/', $data['body'], $m);
        $found = array_map('intval', array_unique($m[1]));
        sort($found);
        if ($found !== range(1, count($found)) && $found !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'Placeholders must be sequential starting at {{1}} (found: '.implode(', ', array_map(fn ($n) => '{{'.$n.'}}', $found)).').',
            ]);
        }

        $data['params'] = array_values($data['params'] ?? []);
        if (count($data['params']) < count($found)) {
            // Pad labels so the placeholder count is always documented.
            for ($i = count($data['params']); $i < count($found); $i++) {
                $data['params'][] = 'param_'.($i + 1);
            }
        }

        $data['is_active'] = $request->boolean('is_active', true);
        $data['buttons'] = $existing?->buttons ?? [];

        return $data;
    }
}
