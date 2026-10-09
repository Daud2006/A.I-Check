<?php

namespace App\Http\Controllers;

class AuthController extends Controller
{
    // Menampilkan halaman autentikasi.
    public function index()
    {
        return view('autentikasi.index');
    }
}
