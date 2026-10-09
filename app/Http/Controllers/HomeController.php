<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    // Menampilkan halaman beranda.
    public function index()
    {
        return view('beranda.index');
    }
}
