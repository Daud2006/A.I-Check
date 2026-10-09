<?php

namespace App\Http\Controllers;

class CheckerController extends Controller
{
    // Menampilkan halaman deteksi.
    public function index()
    {
        return view('deteksi.index');
    }
}
