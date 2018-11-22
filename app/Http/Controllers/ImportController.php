<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function showFormUpload()
    {
        return view('imports.form_upload');
    }
}
