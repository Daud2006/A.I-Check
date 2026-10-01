<?php

namespace App\Http\Controllers;

class AboutController extends Controller
{
    // Render the about page.
    public function index()
    {
        return view('about.index');
    }
}
