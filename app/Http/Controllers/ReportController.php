<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->rows($request);

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student', 'Email', 'Stage', 'Country', 'Agent', 'Sub-Agent', 'Created At']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->full_name,
                    $row->email,
                    $row->stage,
                    $row->target_country,
                    $row->agent?->name,
                    $row->subAgent?->name,
                    (string) $row->created_at,
                ]);
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="advanced-report.csv"');
        return $response;
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $rows = $this->rows($request);
        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student', 'Email', 'Stage', 'Country', 'Agent', 'Sub-Agent', 'Created At'], "\t");
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->full_name,
                    $row->email,
                    $row->stage,
                    $row->target_country,
                    $row->agent?->name,
                    $row->subAgent?->name,
                    (string) $row->created_at,
                ], "\t");
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment; filename="advanced-report.xls"');
        return $response;
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
        return Response::make($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="advanced-report-fallback.html"',
        ]);
    }

    private function rows(Request $request)
    {
        $auth = $this->authUser($request);
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $agentId = (string) $request->query('agent_id', '');
        $country = (string) $request->query('country', '');

        return Student::query()
            ->forTenant($auth->tenant_id, $auth->role_slug)
            ->whereNull('deleted_at')
            ->with(['agent:id,name', 'subAgent:id,name'])
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($agentId !== '', fn ($q) => $q->where('agent_id', (int) $agentId))
            ->when($country !== '', fn ($q) => $q->where('target_country', 'like', "%{$country}%"))
            ->latest('id')
            ->get();
    }
}
