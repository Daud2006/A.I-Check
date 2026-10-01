<?php

namespace App\Http\Controllers;

class CheckerController extends Controller
{
    // Render the checker page.
    public function index()
    {
        return view('checker.index');
    }
}
