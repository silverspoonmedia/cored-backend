<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class PlaygroundController extends Controller
{
    use ApiResponse;

    public function ping()
    {
        return $this->successResponse(null, 'Pong Global', 200);
    }
}
