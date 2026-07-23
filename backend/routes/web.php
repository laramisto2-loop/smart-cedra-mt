<?php

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('tenant')->get('/tenant-check', function (TenantContext $tenantContext) {
    $user = $tenantContext->user();
    $tenant = $tenantContext->tenant();

    return response()->json([
        'message' => 'Tenant access granted.',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
        'tenant' => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ],
    ]);
});
