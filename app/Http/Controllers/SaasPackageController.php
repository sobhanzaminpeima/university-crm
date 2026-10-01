<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\SaasPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasPackageController extends Controller
{
    public function index(Request $request): View
    {
        $auth = $this->authUser($request);
        if ($auth->role_slug !== 'super_admin') {
            abort(403);
        }

        $packages = SaasPackage::query()->orderBy('sort_order')->orderBy('duration_months')->paginate(20);
        $availableFeatures = Feature::query()->orderBy('name')->get();
        return view('saas.packages', compact('packages', 'availableFeatures'));
    }

    public function store(Request $request): RedirectResponse
    {
        $auth = $this->authUser($request);
        if ($auth->role_slug !== 'super_admin') {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'duration_months' => ['required', Rule::in([1, 3, 6, 12])],
            'features_text' => 'nullable|string|max:4000',
            'feature_keys' => 'nullable|array',
            'feature_keys.*' => 'string|max:120',
            'is_active' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        SaasPackage::query()->create([
            'name' => $data['name'],
            'slug' => Str::slug((string) $data['name']).'-'.Str::lower(Str::random(4)),
            'price' => $data['price'],
            'currency' => $data['currency'],
            'duration_months' => (int) $data['duration_months'],
            'features_json' => $this->mergeFeatures((string) ($data['features_text'] ?? ''), $data['feature_keys'] ?? []),
            'is_active' => (int) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Package created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        if ($auth->role_slug !== 'super_admin') {
            abort(403);
        }

        $package = SaasPackage::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'duration_months' => ['required', Rule::in([1, 3, 6, 12])],
            'features_text' => 'nullable|string|max:4000',
            'feature_keys' => 'nullable|array',
            'feature_keys.*' => 'string|max:120',
            'is_active' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $package->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'currency' => $data['currency'],
            'duration_months' => (int) $data['duration_months'],
            'features_json' => $this->mergeFeatures((string) ($data['features_text'] ?? ''), $data['feature_keys'] ?? []),
            'is_active' => (int) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Package updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        if ($auth->role_slug !== 'super_admin') {
            abort(403);
        }
        SaasPackage::query()->findOrFail($id)->delete();
        return back()->with('success', 'Package deleted.');
    }

    private function parseFeatures(string $features): array
    {
        return collect(preg_split('/\r\n|\r|\n|,/', $features))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function mergeFeatures(string $featuresText, array $featureKeys): array
    {
        return collect(array_merge($this->parseFeatures($featuresText), array_map('trim', $featureKeys)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
