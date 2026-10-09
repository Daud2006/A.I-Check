<?php

namespace App\Http\Controllers;

class AdminController extends Controller
{
    // Menampilkan halaman admin.
    public function index()
    {
        return view('admin.index');
    }
}
