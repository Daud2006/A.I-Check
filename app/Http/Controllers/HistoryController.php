<?php

namespace App\Http\Controllers;

class HistoryController extends Controller
{
    // Menampilkan halaman riwayat.
    public function index()
    {
        return view('riwayat.index');
    }
}
