<?php

// app/Http/Controllers/AdminPaymentDetailController.php

namespace App\Http\Controllers;

use App\Models\ManualPaymentDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminPaymentDetailController extends Controller
{
    /** Method keys the Paynow gateway can actually process. */
    private const PAYNOW_METHODS = ['paynow', 'ecocash', 'onemoney', 'innbucks', 'omari'];

    /**
     * Validation rules shared by store/update. When the method is a Paynow
     * gateway, the key must be one the gateway supports — otherwise it would
     * surface to users and error at checkout.
     */
    private function rules(Request $request): array
    {
        $methodKeyRules = ['required', 'string', 'max:50'];
        if ($request->input('gateway_type') === 'paynow') {
            $methodKeyRules[] = Rule::in(self::PAYNOW_METHODS);
        }

        return [
            'method_key' => $methodKeyRules,
            'label' => ['required', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'gateway_type' => ['nullable', 'string', 'in:paynow'],
        ];
    }

    public function index(): Response
    {
        $paymentDetails = ManualPaymentDetail::ordered()->get();

        return Inertia::render('Admin/PaymentDetails', compact('paymentDetails'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $data['method_key'] = strtolower(trim($data['method_key']));
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['gateway_type'] = $data['gateway_type'] ?? null;

        ManualPaymentDetail::create($data);

        return back()->with('success', 'Manual payment details saved.');
    }

    public function update(Request $request, ManualPaymentDetail $manualPaymentDetail): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $data['method_key'] = strtolower(trim($data['method_key']));
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['gateway_type'] = $data['gateway_type'] ?? null;

        $manualPaymentDetail->update($data);

        return back()->with('success', 'Manual payment details updated.');
    }

    public function destroy(ManualPaymentDetail $manualPaymentDetail): RedirectResponse
    {
        $manualPaymentDetail->delete();

        return back()->with('success', 'Manual payment details deleted.');
    }
}
