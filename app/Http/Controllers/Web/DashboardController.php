<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function index(): View
    {
        // Same identity payload the API exposes at GET /api/v1/auth/me.
        return view('dashboard', [
            'profile' => $this->authService->me(),
        ]);
    }
}
