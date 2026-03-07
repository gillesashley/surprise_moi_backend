<?php

namespace App\Http\Controllers;

use App\Models\WawVideo;

class WawVideoShareController extends Controller
{
    public function __invoke(WawVideo $wawVideo)
    {
        return redirect()->away('https://surprisemoi.com');
    }
}
