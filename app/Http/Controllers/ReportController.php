<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Http\Request;
use App\Support\Export\SpreadsheetExport;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $auth = $this->authUser($request);
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $agentId = (string) $request->query('agent_id', '');
        $country = (string) $request->query('country', '');

        $query = Student::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->tap(fn ($q) => $this->applyOwnership($q, $auth))
            ->with(['agent:id,name', 'subAgent:id,name'])
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($agentId !== '', fn ($q) => $q->where('agent_id', (int) $agentId))
            ->when($country !== '', fn ($q) => $q->where('target_country', 'like', "%{$country}%"));

        $students = (clone $query)->latest('id')->paginate(25)->withQueryString();
        $agents = \App\Models\User::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->where('role_slug', 'agent')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.index', compact('students', 'agents', 'from', 'to', 'agentId', 'country'));
    }

    public function exportCsv(Request $request): HttpResponse
    {
        return $this->spreadsheet($request)->csv('advanced-report');
    }

    public function exportExcel(Request $request): HttpResponse
    {
        return $this->spreadsheet($request)->download('advanced-report');
    }

    private function spreadsheet(Request $request): SpreadsheetExport
    {
        $auth = $this->authUser($request);
        $agentName = '';
        if ((string) $request->query('agent_id', '') !== '') {
            $agentName = (string) \App\Models\User::query()
                ->forTenant($auth->tenant_id, $auth->role_slug)
                ->whereKey((int) $request->query('agent_id'))
                ->value('name');
        }

        $columns = [
            ['label' => 'Student', 'type' => 'string'],
            ['label' => 'Email', 'type' => 'string'],
            ['label' => 'Phone', 'type' => 'string'],
            ['label' => 'Stage', 'type' => 'string'],
            ['label' => 'Nationality', 'type' => 'string'],
            ['label' => 'Target Country', 'type' => 'string'],
            ['label' => 'Field of Study', 'type' => 'string'],
            ['label' => 'Lead Source', 'type' => 'string'],
            ['label' => 'Agent', 'type' => 'string'],
            ['label' => 'Sub-Agent', 'type' => 'string'],
            ['label' => 'Created At', 'type' => 'datetime'],
        ];

        $rows = $this->query($request)->lazy(500)->map(fn (Student $row) => [
            $row->full_name,
            $row->email,
            $row->phone,
            SpreadsheetExport::human($row->stage),
            $row->nationality,
            $row->target_country,
            $row->field_of_study,
            SpreadsheetExport::human($row->lead_source),
            $row->agent?->name,
            $row->subAgent?->name,
            $row->created_at,
        ]);

        return SpreadsheetExport::make('Advanced Student Report', $columns, $rows, [
            'From' => (string) $request->query('from', ''),
            'To' => (string) $request->query('to', ''),
            'Agent' => $agentName,
            'Country' => (string) $request->query('country', ''),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $rows = $this->rows($request);
        $html = ViewFacade::make('reports.pdf', ['rows' => $rows])->render();
        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="advanced-report.pdf"',
            ]);
        }
        // No PDF engine on this host: open a print-ready page instead of downloading an .html file.
        $printable = ViewFacade::make('reports.pdf', ['rows' => $rows, 'printable' => true])->render();

        return Response::make($printable, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function rows(Request $request)
    {
        return $this->query($request)->get();
    }

    private function query(Request $request)
    {
        $auth = $this->authUser($request);
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $agentId = (string) $request->query('agent_id', '');
        $country = (string) $request->query('country', '');

        return Student::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->tap(fn ($q) => $this->applyOwnership($q, $auth))
            ->with(['agent:id,name', 'subAgent:id,name'])
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($agentId !== '', fn ($q) => $q->where('agent_id', (int) $agentId))
            ->when($country !== '', fn ($q) => $q->where('target_country', 'like', "%{$country}%"))
            ->latest('id');
    }

    /** Agents and sub-agents only see their own students unless granted students.view_all. */
    private function applyOwnership($query, $auth)
    {
        return $query->when(in_array($auth->role_slug, ['agent', 'sub_agent'], true) && ! $auth->hasPermission('students.view_all'), function ($q) use ($auth) {
            $q->where($auth->role_slug === 'agent' ? 'agent_id' : 'sub_agent_id', $auth->id);
        });
    }
}
