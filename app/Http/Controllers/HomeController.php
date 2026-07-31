<?php

namespace App\Http\Controllers;

use App\Services\HomepageService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(HomepageService $homepage): View
    {
        return view('frontend.home', [
            'homepage' => $homepage->data(),
        ]);
    }
}
