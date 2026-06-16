<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Controllers\Web\Concerns\RendersWebViews;

abstract class Controller extends BaseController
{
    use RendersWebViews;
}
