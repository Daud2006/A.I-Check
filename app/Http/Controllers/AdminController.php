<?php

namespace App\Http\Controllers;

class AdminController extends Controller
{
    // Render the admin page.
    public function index()
    {
        return view('admin.index');
    }
}
