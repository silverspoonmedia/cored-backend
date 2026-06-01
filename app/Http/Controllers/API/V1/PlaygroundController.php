<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class PlaygroundController extends Controller
{
    use ApiResponse;

    public function ping()
    {
        return $this->successResponse(null, 'Pong V1', 200);
    }
}
