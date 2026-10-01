<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Student;
use App\Models\TenantSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $auth = $this->authUser($request);
        $status = (string) $request->query('status', '');
        $currency = (string) $request->query('currency', '');
        $perPage = $this->perPage($request);

        $payments = Payment::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($currency !== '', fn ($query) => $query->where('currency', $currency))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $students = Student::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->when(in_array($auth->role_slug, ['agent', 'sub_agent'], true), fn ($query) => $this->applyStudentOwnershipScope($query, $auth))
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        $summary = [
            'total_amount' => (float) Payment::query()->forTenant($auth->tenant_id, $auth->role_slug)->sum('amount'),
            'paid_amount' => (float) Payment::query()->forTenant($auth->tenant_id, $auth->role_slug)->where('status', 'paid')->sum('amount'),
            'commission' => (float) Payment::query()->forTenant($auth->tenant_id, $auth->role_slug)->sum('commission_amount'),
        ];

        $saasSales = null;
        $recentSaasSubscriptions = collect();
        if ($auth->role_slug === 'super_admin') {
            $saasSales = [
                'total' => (float) TenantSubscription::query()->sum('amount'),
                'active' => (float) TenantSubscription::query()->where('status', 'active')->sum('amount'),
                'expired_count' => (int) TenantSubscription::query()->where('status', 'expired')->count(),
                'active_count' => (int) TenantSubscription::query()->where('status', 'active')->count(),
            ];
            $recentSaasSubscriptions = TenantSubscription::query()
                ->with(['tenant:id,name'])
                ->latest('id')
                ->limit(12)
                ->get();
        }

        return view('finance.index', compact('payments', 'students', 'summary', 'status', 'currency', 'perPage', 'saasSales', 'recentSaasSubscriptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $auth = $this->authUser($request);
        $data = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'type' => 'required|string|max:60',
            'currency' => 'required|string|in:USD,EUR,GBP,TRY',
            'amount' => 'required|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|string|in:pending,paid,failed,refunded',
            'paid_at' => 'nullable|date',
        ]);
        $data['tenant_id'] = $auth->tenant_id;
        $this->enforceStudentOwnershipOrFail($auth, (int) $data['student_id'], 'finance.store');
        $rate = (float) ($data['commission_rate'] ?? 0);
        $data['commission_amount'] = round(((float) $data['amount']) * ($rate / 100), 2);
        if ($data['status'] === 'paid' && empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        $payment = Payment::query()->create($data);
        $this->audit($request, 'payment.create', 'payment', $payment->id, $data);

        return back()->with('success', 'Payment recorded.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $payment = Payment::query()->forTenant($auth->tenant_id, $auth->role_slug)->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($auth, (int) $payment->student_id, 'finance.update');
        $data = $request->validate([
            'type' => 'required|string|max:60',
            'currency' => 'required|string|in:USD,EUR,GBP,TRY',
            'amount' => 'required|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|string|in:pending,paid,failed,refunded',
            'paid_at' => 'nullable|date',
        ]);
        $rate = (float) ($data['commission_rate'] ?? 0);
        $data['commission_amount'] = round(((float) $data['amount']) * ($rate / 100), 2);
        if ($data['status'] === 'paid' && empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }
        $payment->update($data);
        $this->audit($request, 'payment.update', 'payment', $payment->id, $data);

        return back()->with('success', 'Payment updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $payment = Payment::query()->forTenant($auth->tenant_id, $auth->role_slug)->findOrFail($id);
        $this->enforceStudentOwnershipOrFail($auth, (int) $payment->student_id, 'finance.destroy');
        $payment->delete();
        $this->audit($request, 'payment.delete', 'payment', $id);

        return back()->with('success', 'Payment deleted.');
    }
}
