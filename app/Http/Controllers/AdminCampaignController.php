<?php

namespace App\Http\Controllers;

use App\Jobs\SendMarketingBroadcastJob;
use App\Models\MarketingCampaign;
use App\Services\AI\MarketingCopyGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCampaignController extends Controller
{
    public function index(): Response
    {
        $campaigns = MarketingCampaign::query()
            ->with('creator:id,name,email')
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject_en' => ['required', 'string', 'max:180'],
            'body_en' => ['required', 'string', 'max:10000'],
            'subject_sn' => ['nullable', 'string', 'max:180'],
            'body_sn' => ['nullable', 'string', 'max:10000'],
            'subject_nd' => ['nullable', 'string', 'max:180'],
            'body_nd' => ['nullable', 'string', 'max:10000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:email,whatsapp,in_app'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:all,user,marketer,reseller,admin'],
            'account_types' => ['nullable', 'array'],
            'account_types.*' => ['string', 'in:all,individual,business,marketer'],
        ]);

        $campaign = MarketingCampaign::query()->create([
            'created_by' => (int) $request->user()->id,
            'name' => $data['name'],
            'subjects' => [
                'en' => $data['subject_en'],
                'sn' => $data['subject_sn'] ?: $data['subject_en'],
                'nd' => $data['subject_nd'] ?: $data['subject_en'],
            ],
            'bodies' => [
                'en' => $data['body_en'],
                'sn' => $data['body_sn'] ?: $data['body_en'],
                'nd' => $data['body_nd'] ?: $data['body_en'],
            ],
            'channels' => array_values(array_unique($data['channels'])),
            'filters' => [
                'roles' => $data['roles'] ?? ['all'],
                'account_types' => $data['account_types'] ?? ['all'],
            ],
            'status' => 'queued',
        ]);

        SendMarketingBroadcastJob::dispatch((int) $campaign->id)->onQueue('notifications');

        return back()->with('success', __('messages.broadcast_queued'));
    }

    public function generateCopy(Request $request, MarketingCopyGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'brief' => ['required', 'string', 'max:500'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:email,whatsapp,in_app'],
            'tone' => ['nullable', 'string', 'max:200'],
        ]);

        $result = $generator->generate([
            'brief' => $data['brief'],
            'channels' => $data['channels'] ?? ['email'],
            'tone' => $data['tone'] ?? null,
        ]);

        if ($result === null) {
            return response()->json(['message' => 'AI copywriter is not available.'], 503);
        }

        return response()->json($result);
    }
}
