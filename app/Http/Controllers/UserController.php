<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->where('role', User::ROLE_FACILITATOR)
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Users/Index', compact('users'));
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'grade_level' => $request->input('grade_level'),
            'email' => $this->generatePlaceholderEmail($request->input('username')),
            'role' => User::ROLE_FACILITATOR,
            'password' => $request->input('password'),
        ]);

        return redirect()
            ->route('facilitators.index')
            ->with('status', 'Facilitator account created successfully.');
    }

    public function edit(User $facilitator): Response
    {
        abort_if($facilitator->role !== User::ROLE_FACILITATOR, 404);

        return Inertia::render('Admin/Users/Edit', ['user' => $facilitator]);
    }

    public function update(UpdateUserRequest $request, User $facilitator): RedirectResponse
    {
        abort_if($facilitator->role !== User::ROLE_FACILITATOR, 404);

        $payload = [
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'grade_level' => $request->input('grade_level'),
            'role' => User::ROLE_FACILITATOR,
        ];

        if ($request->filled('password')) {
            $payload['password'] = $request->input('password');
        }

        $facilitator->update($payload);

        return redirect()
            ->route('facilitators.index')
            ->with('status', 'Facilitator account updated successfully.');
    }

    public function destroy(User $facilitator): RedirectResponse
    {
        abort_if($facilitator->role !== User::ROLE_FACILITATOR, 404);

        if (auth()->id() === $facilitator->id) {
            return redirect()
                ->route('facilitators.index')
                ->with('status', 'You cannot delete your own active account.');
        }

        $facilitator->delete();

        return redirect()
            ->route('facilitators.index')
            ->with('status', 'Facilitator account deleted successfully.');
    }

    private function generatePlaceholderEmail(string $username): string
    {
        $base = Str::lower($username);
        $candidate = "{$base}@facilitator.local";
        $suffix = 1;

        while (User::query()->where('email', $candidate)->exists()) {
            $candidate = "{$base}.{$suffix}@facilitator.local";
            $suffix++;
        }

        return $candidate;
    }
}
