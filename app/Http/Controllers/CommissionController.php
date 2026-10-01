<?php

namespace App\Http\Controllers;

use App\Models\CommissionPayout;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $auth = $this->authUser($request);

        $agents = User::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereIn('role_slug', ['agent', 'sub_agent'])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'role_slug']);

        $studentAgentMap = Student::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->get(['id', 'agent_id', 'sub_agent_id']);

        $earnedByAgentCurrency = [];
        Payment::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->where('status', 'paid')
            ->where('commission_amount', '>', 0)
            ->get(['student_id', 'currency', 'commission_amount'])
            ->each(function ($payment) use (&$earnedByAgentCurrency, $studentAgentMap) {
                $student = $studentAgentMap->firstWhere('id', $payment->student_id);
                if (!$student) {
                    return;
                }
                foreach (array_filter([$student->agent_id, $student->sub_agent_id]) as $agentId) {
                    $key = $agentId.'|'.$payment->currency;
                    $earnedByAgentCurrency[$key] = ($earnedByAgentCurrency[$key] ?? 0) + (float) $payment->commission_amount;
                }
            });

        $paidOutByAgentCurrency = CommissionPayout::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->get(['agent_id', 'currency', 'amount'])
            ->reduce(function ($carry, $payout) {
                $key = $payout->agent_id.'|'.$payout->currency;
                $carry[$key] = ($carry[$key] ?? 0) + (float) $payout->amount;
                return $carry;
            }, []);

        $rows = collect();
        foreach ($agents as $agent) {
            $currencies = collect(array_keys($earnedByAgentCurrency))
                ->filter(fn ($key) => str_starts_with($key, $agent->id.'|'))
                ->map(fn ($key) => explode('|', $key)[1])
                ->merge(
                    collect(array_keys($paidOutByAgentCurrency))
                        ->filter(fn ($key) => str_starts_with($key, $agent->id.'|'))
                        ->map(fn ($key) => explode('|', $key)[1])
                )
                ->unique();
            foreach ($currencies as $currency) {
                $key = $agent->id.'|'.$currency;
                $earned = round($earnedByAgentCurrency[$key] ?? 0, 2);
                $paidOut = round($paidOutByAgentCurrency[$key] ?? 0, 2);
                if ($earned <= 0 && $paidOut <= 0) {
                    continue;
                }
                $rows->push([
                    'agent' => $agent,
                    'currency' => $currency,
                    'earned' => $earned,
                    'paid_out' => $paidOut,
                    'balance' => round($earned - $paidOut, 2),
                ]);
            }
        }

        $payoutHistory = CommissionPayout::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->with('agent:id,name')
            ->latest('id')
            ->limit(30)
            ->get();

        return view('finance.commissions', [
            'rows' => $rows->sortByDesc('balance')->values(),
            'agents' => $agents,
            'payoutHistory' => $payoutHistory,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $auth = $this->authUser($request);
        $data = $request->validate([
            'agent_id' => 'required|integer|exists:users,id',
            'currency' => 'required|string|in:USD,EUR,GBP,TRY',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
        ]);

        $agent = User::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereIn('role_slug', ['agent', 'sub_agent'])
            ->findOrFail($data['agent_id']);

        $payout = CommissionPayout::query()->create([
            'tenant_id' => $auth->tenant_id,
            'agent_id' => $agent->id,
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'created_by_user_id' => $auth->id,
            'paid_at' => now(),
        ]);

        $this->audit($request, 'commission_payout.create', 'commission_payout', $payout->id, $data);

        return back()->with('success', 'Payout recorded for '.$agent->name.'.');
    }
}
