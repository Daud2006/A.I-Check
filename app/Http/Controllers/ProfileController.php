<?php

namespace App\Http\Controllers;

class ProfileController extends Controller
{
    // Render the profile page.
    public function index()
    {
        return view('profile.index');
    }
}
