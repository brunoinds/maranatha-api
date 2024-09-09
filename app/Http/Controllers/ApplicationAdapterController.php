<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ApplicationAdapterController extends Controller
{
    public function backup()
    {
        ini_set('max_execution_time', -1);
        Artisan::call('backup:run');

        return response()->json(['message' => 'Backup completed successfully']);
    }
}
