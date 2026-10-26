<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class LegacyAppController extends Controller
{
    public function index()
    {
        $apps = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'url',
                'icon',
                'description',
            ]);

        return response()->json(['data' => $apps]);
    }
}