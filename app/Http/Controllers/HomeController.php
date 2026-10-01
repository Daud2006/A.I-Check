<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    // Render the home page.
    public function index()
    {
        return view('home.index');
    }
}
