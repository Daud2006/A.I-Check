@php
    $currentPlan = $plan ?? null;
@endphp

<section class="admin-data-panel admin-form-panel">
    <form method="POST" action="{{ $currentPlan?->exists ? route('admin.plans.update', $currentPlan) : route('admin.plans.store') }}" class="admin-plan-form">
        @csrf
        @if ($currentPlan?->exists)
            @method('PUT')
        @endif

        <label class="admin-filter-field">
            <span class="admin-filter-label">Nama paket</span>
            <input name="name" value="{{ old('name', $currentPlan?->name) }}" class="admin-filter-control" maxlength="100" required>
            @error('name') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>
        <label class="admin-filter-field">
            <span class="admin-filter-label">Slug</span>
            <input name="slug" value="{{ old('slug', $currentPlan?->slug) }}" class="admin-filter-control" maxlength="100" pattern="[A-Za-z0-9_-]+" required>
            @error('slug') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>
        <label class="admin-filter-field admin-plan-description">
            <span class="admin-filter-label">Deskripsi</span>
            <textarea name="description" class="admin-filter-control" rows="4" maxlength="2000">{{ old('description', $currentPlan?->description) }}</textarea>
            @error('description') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>
        <label class="admin-filter-field">
            <span class="admin-filter-label">Harga</span>
            <input type="number" name="price" value="{{ old('price', $currentPlan?->price ?? '0.00') }}" class="admin-filter-control" min="0" max="9999999999.99" step="0.01" required>
            @error('price') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>
        <label class="admin-filter-field">
            <span class="admin-filter-label">Mata uang (kode ISO 3 huruf)</span>
            <input name="currency" value="{{ old('currency', $currentPlan?->currency ?? 'IDR') }}" class="admin-filter-control" minlength="3" maxlength="3" pattern="[A-Z]{3}" required>
            @error('currency') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>
        <label class="admin-filter-field">
            <span class="admin-filter-label">Periode berlangganan</span>
            <select name="billing_interval" class="admin-filter-control" required>
                @foreach (\App\Models\Plan::BILLING_INTERVALS as $interval)
                    <option value="{{ $interval }}" @selected(old('billing_interval', $currentPlan?->billing_interval ?? 'none') === $interval)>
                        {{ match ($interval) {'monthly' => 'Bulanan', 'yearly' => 'Tahunan', 'none' => 'Tidak berulang'} }}
                    </option>
                @endforeach
            </select>
            @error('billing_interval') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>
        <label class="admin-filter-field">
            <span class="admin-filter-label">Batas analisis (kosong = tidak diatur)</span>
            <input type="number" name="analysis_limit" value="{{ old('analysis_limit', $currentPlan?->analysis_limit) }}" class="admin-filter-control" min="0" max="4294967295" step="1">
            @error('analysis_limit') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>
        <label class="admin-filter-field">
            <span class="admin-filter-label">Status paket</span>
            <select name="is_active" class="admin-filter-control" required>
                <option value="1" @selected((string) old('is_active', (int) ($currentPlan?->is_active ?? true)) === '1')>Aktif</option>
                <option value="0" @selected((string) old('is_active', (int) ($currentPlan?->is_active ?? true)) === '0')>Nonaktif</option>
            </select>
            @error('is_active') <span class="admin-form-error">{{ $message }}</span> @enderror
        </label>

        <div class="admin-filter-actions admin-plan-form-actions">
            <button type="submit" class="btn btn-primary">{{ $currentPlan?->exists ? 'Simpan perubahan' : 'Buat paket' }}</button>
            <a href="{{ $currentPlan?->exists ? route('admin.plans.show', $currentPlan) : route('admin.plans.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</section>
