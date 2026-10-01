<?php

namespace App\Http\Controllers;

use App\Models\Scholarship;
use App\Models\University;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScholarshipController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        $q = (string) $request->query('q', '');
        $country = trim((string) $request->query('country', ''));
        $universityId = (int) $request->query('university_id', 0);
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $sort = (string) $request->query('sort', 'created_desc');
        $perPage = $this->perPage($request);

        $scholarships = Scholarship::query()
            ->select('scholarships.*')
            ->forTenant($user->tenant_id, $user->role_slug)
            ->leftJoin('universities', function ($join) use ($user) {
                $join->on('universities.id', '=', 'scholarships.university_id')
                    ->where('universities.tenant_id', '=', $user->tenant_id);
            })
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('scholarships.title', 'like', "%{$q}%")
                    ->orWhere('scholarships.description', 'like', "%{$q}%")
                    ->orWhere('universities.name', 'like', "%{$q}%");
            }))
            ->when($country !== '', fn ($query) => $query->where('universities.country', $country))
            ->when($universityId > 0, fn ($query) => $query->where('scholarships.university_id', $universityId))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('scholarships.created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('scholarships.created_at', '<=', $dateTo))
            ->when($sort === 'title_asc', fn ($query) => $query->orderBy('scholarships.title')->orderBy('scholarships.id'))
            ->when($sort === 'title_desc', fn ($query) => $query->orderByDesc('scholarships.title')->orderByDesc('scholarships.id'))
            ->when($sort === 'university_asc', fn ($query) => $query->orderBy('universities.name')->orderBy('scholarships.title'))
            ->when($sort === 'created_asc', fn ($query) => $query->orderBy('scholarships.created_at')->orderBy('scholarships.id'))
            ->when(!in_array($sort, ['title_asc', 'title_desc', 'university_asc', 'created_asc'], true), fn ($query) => $query->orderByDesc('scholarships.created_at')->orderByDesc('scholarships.id'))
            ->paginate($perPage)
            ->withQueryString();

        $universities = University::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->orderBy('name')
            ->get();
        $countryOptions = $universities->pluck('country')->filter()->unique()->sort()->values()->all();

        $uniMap = $universities->keyBy('id');

        return view('scholarships.index', compact('scholarships', 'universities', 'uniMap', 'countryOptions', 'q', 'country', 'universityId', 'dateFrom', 'dateTo', 'sort', 'perPage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'university_id' => 'required|integer',
            'title' => 'required|string|max:190',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string|max:2000',
        ]);
        $data['tenant_id'] = $user->tenant_id;

        $scholarship = Scholarship::query()->create($data);
        $this->audit($request, 'scholarship.create', 'scholarship', $scholarship->id, $data);

        return back()->with('success', 'Scholarship created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $scholarship = Scholarship::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->findOrFail($id);

        $data = $request->validate([
            'university_id' => 'required|integer',
            'title' => 'required|string|max:190',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string|max:2000',
        ]);
        $scholarship->update($data);
        $this->audit($request, 'scholarship.update', 'scholarship', $scholarship->id, $data);

        return back()->with('success', 'Scholarship updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $this->authUser($request);
        $scholarship = Scholarship::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->findOrFail($id);
        $scholarship->delete();
        $this->audit($request, 'scholarship.delete', 'scholarship', $id);

        return back()->with('success', 'Scholarship deleted.');
    }
}
