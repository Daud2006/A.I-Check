<?php

namespace App\Http\Controllers;

class AuthController extends Controller
{
    // Render the auth page.
    public function index()
    {
        return view('auth.index');
    }
}
