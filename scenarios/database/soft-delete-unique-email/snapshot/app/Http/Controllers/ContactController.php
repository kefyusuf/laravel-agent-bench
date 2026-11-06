<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateContactRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Contact::query()->orderBy('id')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(CreateContactRequest $request): JsonResponse
    {
        $contact = Contact::create($request->validated());

        return response()->json(['contact' => ['id' => $contact->id, 'email' => $contact->email]], 201);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json(['message' => 'deleted']);
    }
}
