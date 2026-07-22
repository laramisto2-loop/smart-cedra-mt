<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('tenant')->get('/tenant-check', function (Request $request) {
    return response()->json([
        'message' => 'Tenant access granted.',
        'user' => [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ],
        'tenant' => [
            'id' => $request->user()->tenant->id,
            'name' => $request->user()->tenant->name,
            'slug' => $request->user()->tenant->slug,
        ],
    ]);
});