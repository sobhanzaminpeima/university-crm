<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IntakeCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $range = (string) $request->query('range', 'upcoming');

        $applications = Application::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('applications.view_all'), function ($query) use ($user) {
                $query->whereIn('applications.student_id', function ($sub) use ($user) {
                    $sub->select('id')
                        ->from('students')
                        ->where('tenant_id', $user->tenant_id)
                        ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                        ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
                });
            })
            ->whereNotNull('applications.deadline')
            ->whereNotIn('applications.status', ['enrolled', 'rejected'])
            ->leftJoin('students', 'students.id', '=', 'applications.student_id')
            ->leftJoin('universities', 'universities.id', '=', 'applications.university_id')
            ->select([
                'applications.*',
                DB::raw('students.full_name as student_name'),
                DB::raw('universities.name as university_name'),
            ])
            ->when($range === 'upcoming', fn ($query) => $query->where('applications.deadline', '>=', now()->toDateString()))
            ->when($range === 'overdue', fn ($query) => $query->where('applications.deadline', '<', now()->toDateString()))
            ->orderBy('applications.deadline')
            ->get();

        $today = Carbon::today();
        $grouped = $applications->groupBy(function ($app) {
            return Carbon::parse($app->deadline)->format('Y-m');
        })->map(function ($items) use ($today) {
            return $items->map(function ($app) use ($today) {
                $deadline = Carbon::parse($app->deadline);
                $daysLeft = $today->diffInDays($deadline, false);
                $app->urgency = match (true) {
                    $daysLeft < 0 => 'overdue',
                    $daysLeft <= 7 => 'critical',
                    $daysLeft <= 14 => 'soon',
                    default => 'upcoming',
                };
                $app->days_left = $daysLeft;
                return $app;
            });
        });

        $overdueCount = $applications->filter(fn ($app) => Carbon::parse($app->deadline)->isPast())->count();
        $dueSoonCount = $applications->filter(function ($app) use ($today) {
            $days = $today->diffInDays(Carbon::parse($app->deadline), false);
            return $days >= 0 && $days <= 14;
        })->count();

        return view('calendar.index', [
            'grouped' => $grouped,
            'range' => $range,
            'overdueCount' => $overdueCount,
            'dueSoonCount' => $dueSoonCount,
            'totalCount' => $applications->count(),
        ]);
    }
}
