<?php

namespace App\Http\Controllers;

class HistoryController extends Controller
{
    // Render the history page.
    public function index()
    {
        return view('history.index');
    }
}
