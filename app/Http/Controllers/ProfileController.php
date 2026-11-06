<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // Menampilkan halaman profil.
    public function index(Request $request): View
    {
        $user = $request->user();
        $now = now();

        $hasPremiumAccess = $user->subscriptions()
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->whereHas('plan', function (Builder $query): void {
                $query->where('is_active', true)->where('price', '>', 0);
            })
            ->exists();

        return view('beranda.profil', [
            'accountType' => $hasPremiumAccess ? 'Premium' : 'Free',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['prohibited'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'email.prohibited' => 'Email tidak dapat diubah melalui pengaturan profil.',
        ]);

        $user->name = $validated['name'];
        $user->save();

        return redirect()->route('profile')->with('status', 'Profil berhasil diperbarui.');
    }
}
