<?php

namespace App\Http\Controllers;

class AboutController extends Controller
{
    // Menampilkan halaman tentang.
    public function index()
    {
        return view('tentang.index');
    }
}
