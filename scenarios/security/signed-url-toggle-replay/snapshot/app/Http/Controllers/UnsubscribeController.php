<?php

namespace App\Http\Controllers;

use App\Mail\UnsubscribeConfirmed;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        $user->update([
            'marketing_opt_in' => ! $user->marketing_opt_in,
        ]);

        Mail::to($user->email)->send(new UnsubscribeConfirmed);

        return response()->view('unsubscribe-done', ['user' => $user]);
    }
}
