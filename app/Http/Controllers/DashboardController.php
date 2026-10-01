<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $canViewConversions = $user->hasPermission('reports.view') || $user->role_slug === 'super_admin';
        $isScopedAgent = in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all');

        $students = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at');
        if ($isScopedAgent) {
            $this->applyStudentOwnershipScope($students, $user);
        }
        $applications = Application::query()->forTenant($user->tenant_id, $user->role_slug);
        if ($isScopedAgent) {
            $applications->whereIn('student_id', function ($sub) use ($user) {
                $sub->select('id')->from('students')->where('tenant_id', $user->tenant_id)->whereNull('deleted_at');
                if ($user->role_slug === 'agent') {
                    $sub->where('agent_id', $user->id);
                } else {
                    $sub->where('sub_agent_id', $user->id);
                }
            });
        }
        $tasks = Task::query()->forTenant($user->tenant_id, $user->role_slug);
        if ($isScopedAgent) {
            $tasks->whereIn('student_id', function ($sub) use ($user) {
                $sub->select('id')->from('students')->where('tenant_id', $user->tenant_id)->whereNull('deleted_at');
                if ($user->role_slug === 'agent') {
                    $sub->where('agent_id', $user->id);
                } else {
                    $sub->where('sub_agent_id', $user->id);
                }
            });
        }
        $requests = StudentRequest::query()->forTenant($user->tenant_id, $user->role_slug)->where('status', 'pending');

        $pipeline = [
            'lead' => (clone $students)->where('stage', 'lead')->count(),
            'applied' => (clone $students)->whereIn('stage', ['applicant', 'documents_pending', 'interview_scheduled'])->count(),
            'enrolled' => (clone $students)->where('stage', 'enrolled')->count(),
        ];

        $recentStudents = (clone $students)->latest('id')->limit(5)->get();
        $notifications = Notification::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();
        $unreadNotifications = $notifications->whereNull('read_at')->count();
        $topPrograms = Application::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->select('program', DB::raw('count(*) as total'))
            ->groupBy('program')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
        $upcomingTasks = Task::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderBy('deadline')
            ->limit(6)
            ->get();
        $overdueTasks = Task::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereIn('status', ['todo', 'in_progress'])
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();
        $monthlyRevenue = Payment::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();
        $funnelTotal = max(1, array_sum($pipeline));
        $funnelPercentages = [
            'lead' => (int) round(($pipeline['lead'] / $funnelTotal) * 100),
            'applied' => (int) round(($pipeline['applied'] / $funnelTotal) * 100),
            'enrolled' => (int) round(($pipeline['enrolled'] / $funnelTotal) * 100),
        ];

        $totalLeads = (clone $students)->where('stage', 'lead')->count();
        $totalEnrolled = (clone $students)->where('stage', 'enrolled')->count();
        $conversionRate = $canViewConversions && $totalLeads > 0 ? round(($totalEnrolled / $totalLeads) * 100, 2) : null;

        $visaInProcess = (clone $students)->where('stage', 'visa_process')->count();
        $visaSuccessRate = $canViewConversions && ($visaInProcess + $totalEnrolled) > 0
            ? round(($totalEnrolled / max(1, ($visaInProcess + $totalEnrolled))) * 100, 2)
            : null;

        $currentMonthRevenue = Payment::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
        $last3Avg = Payment::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->subMonths(3)->startOfMonth(), now()->endOfMonth()])
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total');
        $revenueForecastNextMonth = $last3Avg->count() > 0 ? round((float) $last3Avg->avg(), 2) : (float) $currentMonthRevenue;

        $leadSourceRows = $canViewConversions ? DB::table('students')
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('deleted_at')
            ->selectRaw("COALESCE(lead_source, 'unknown') as source_key, COUNT(*) as total_leads, SUM(CASE WHEN stage='enrolled' THEN 1 ELSE 0 END) as enrolled_leads")
            ->groupBy('source_key')
            ->get() : collect();
        $sourceCosts = collect();
        if (Schema::hasTable('lead_source_costs')) {
            $sourceCosts = DB::table('lead_source_costs')
                ->where('tenant_id', $user->tenant_id)
                ->pluck('monthly_cost', 'source_key');
        }
        $sourceRoi = $leadSourceRows->map(function ($row) use ($sourceCosts) {
            $cost = (float) ($sourceCosts[$row->source_key] ?? 0);
            $enrolled = (int) $row->enrolled_leads;
            $leads = (int) $row->total_leads;
            $cac = $enrolled > 0 ? round($cost / $enrolled, 2) : null;
            $roi = $cost > 0 ? round((($enrolled * 1) - $cost) / $cost * 100, 2) : null;
            return (object) [
                'source_key' => $row->source_key,
                'leads' => $leads,
                'enrolled' => $enrolled,
                'cost' => $cost,
                'cac' => $cac,
                'roi' => $roi,
            ];
        });
        $totalMarketingCost = (float) $sourceRoi->sum('cost');
        $overallCac = $totalEnrolled > 0 ? round($totalMarketingCost / $totalEnrolled, 2) : null;

        return view('dashboard.index', [
            'user' => $user,
            'stats' => [
                'students' => (clone $students)->count(),
                'active_applications' => (clone $applications)->whereIn('status', ['new_lead', 'interested', 'application_started', 'documents_pending', 'interview_scheduled', 'offer_sent', 'visa_process', 'tuition_paid'])->count(),
                'pending_tasks' => (clone $tasks)->whereIn('status', ['todo', 'in_progress'])->count(),
                'new_requests' => $requests->count(),
            ],
            'pipeline' => $pipeline,
            'recentStudents' => $recentStudents,
            'unreadNotifications' => $unreadNotifications,
            'notifications' => $notifications,
            'topPrograms' => $topPrograms,
            'upcomingTasks' => $upcomingTasks,
            'overdueTasks' => $overdueTasks,
            'monthlyRevenue' => $monthlyRevenue,
            'funnelPercentages' => $funnelPercentages,
            'conversionRate' => $conversionRate,
            'visaSuccessRate' => $visaSuccessRate,
            'overallCac' => $overallCac,
            'revenueForecastNextMonth' => $revenueForecastNextMonth,
            'sourceRoi' => $sourceRoi,
            'canViewConversions' => $canViewConversions,
        ]);
    }
}
