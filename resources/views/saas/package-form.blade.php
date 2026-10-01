@php($duration = old('duration_months', $package->duration_months ?? 1))
@php($active = (string) old('is_active', isset($package) ? (string)$package->is_active : '1'))
@php($featuresText = old('features_text', isset($package) ? implode(PHP_EOL, $package->features_json ?? []) : ''))
@php($selectedFeatureKeys = collect(old('feature_keys', $package->features_json ?? []))->map(fn($f) => (string)$f)->all())
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
    <div><label>Name</label><input name="name" required value="{{ old('name', $package->name ?? '') }}"></div>
    <div><label>Price</label><input type="number" step="0.01" min="0" name="price" required value="{{ old('price', $package->price ?? 0) }}"></div>
    <div><label>Currency</label><input name="currency" required value="{{ old('currency', $package->currency ?? 'USD') }}"></div>
    <div>
        <label>Duration</label>
        <select name="duration_months" required>
            <option value="1" {{ (int)$duration === 1 ? 'selected' : '' }}>Monthly</option>
            <option value="3" {{ (int)$duration === 3 ? 'selected' : '' }}>3 Months</option>
            <option value="6" {{ (int)$duration === 6 ? 'selected' : '' }}>6 Months</option>
            <option value="12" {{ (int)$duration === 12 ? 'selected' : '' }}>1 Year</option>
        </select>
    </div>
    <div>
        <label>Status</label>
        <select name="is_active" required>
            <option value="1" {{ $active === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ $active === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    <div><label>Sort Order</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $package->sort_order ?? 0) }}"></div>
    <div style="grid-column:1/-1;">
        <label>Feature Selection</label>
        <div class="tabs" style="display:flex;flex-wrap:wrap;gap:8px;margin:6px 0 10px;">
            @foreach(($availableFeatures ?? collect()) as $feature)
                @php($featureKey = (string)($feature->key ?? $feature->name))
                <label class="tab">
                    <input type="checkbox" name="feature_keys[]" value="{{ $featureKey }}" {{ in_array($featureKey, $selectedFeatureKeys, true) ? 'checked' : '' }}>
                    {{ $feature->name }}
                </label>
            @endforeach
        </div>
        <label>Features (one per line)</label>
        <textarea name="features_text" rows="5">{{ $featuresText }}</textarea>
    </div>
</div>
