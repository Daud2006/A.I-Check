<?php

namespace App\Http\Controllers;

use App\Services\AnalysisQuotaService;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // Menampilkan halaman profil.
    public function index(Request $request, AnalysisQuotaService $quota): View
    {
        $account = $quota->accountSummary($request->user());
        $nextQuotaAt = $account['quota']['resets_at'];

        return view('beranda.profil', [
            'account' => $account,
            'nextQuotaAt' => $nextQuotaAt
                ? Carbon::parse($nextQuotaAt)->utc()->locale('id')
                : null,
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
