<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class PlaygroundController extends Controller
{
    use ApiResponse;

    public function ping()
    {
        return $this->successResponse(null, __('api/playground.pong_v1'), 200);
    }
}
