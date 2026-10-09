<?php

namespace App\Http\Controllers;

class ProfileController extends Controller
{
    // Menampilkan halaman profil.
    public function index()
    {
        return view('beranda.profil');
    }
}
