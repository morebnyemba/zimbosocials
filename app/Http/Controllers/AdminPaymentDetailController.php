<?php
// app/Http/Controllers/AdminPaymentDetailController.php

namespace App\Http\Controllers;

use App\Models\ManualPaymentDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPaymentDetailController extends Controller
{
    public function index(): Response
    {
        $paymentDetails = ManualPaymentDetail::ordered()->get();

        return Inertia::render('Admin/PaymentDetails', compact('paymentDetails'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'method_key' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'gateway_type' => ['nullable', 'string', 'in:paynow'],
        ]);

        $data['method_key'] = strtolower(trim($data['method_key']));
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['gateway_type'] = $data['gateway_type'] ?? null;

        ManualPaymentDetail::create($data);

        return back()->with('success', 'Manual payment details saved.');
    }

    public function update(Request $request, ManualPaymentDetail $manualPaymentDetail): RedirectResponse
    {
        $data = $request->validate([
            'method_key' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'gateway_type' => ['nullable', 'string', 'in:paynow'],
        ]);

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
