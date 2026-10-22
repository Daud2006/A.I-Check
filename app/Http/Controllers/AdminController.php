<?php

namespace App\Http\Controllers;

use App\Models\User;

class AdminController extends Controller
{
    // Menampilkan halaman admin.
    public function index()
    {
        abort_unless(auth()->user()->role === User::ROLE_ADMIN, 403);

        return view('admin.index');
    }
}
