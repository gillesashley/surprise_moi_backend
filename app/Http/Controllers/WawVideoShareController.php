<?php

namespace App\Http\Controllers;

use App\Models\WawVideo;
use Illuminate\Http\RedirectResponse;

class WawVideoShareController extends Controller
{
    public function __invoke(WawVideo $wawVideo): RedirectResponse
    {
        return redirect()->away('https://surprisemoi.com');
    }
}
