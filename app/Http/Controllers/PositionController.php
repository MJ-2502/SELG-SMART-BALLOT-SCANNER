<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function index(): Response
    {
        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Positions/Index', compact('positions'));
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Positions/Create');
    }

    public function store(StorePositionRequest $request): RedirectResponse
    {
        Position::create([
            'name' => $request->input('name'),
            'display_order' => $request->integer('display_order', 0),
            'votes_allowed' => $request->integer('votes_allowed', 1),
        ]);

        return redirect()
            ->route('positions.index')
            ->with('status', 'Position created successfully.');
    }

    public function edit(Position $position): Response
    {
        return Inertia::render('Admin/Positions/Edit', compact('position'));
    }

    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $position->update([
            'name' => $request->input('name'),
            'display_order' => $request->integer('display_order', 0),
            'votes_allowed' => $request->integer('votes_allowed', 1),
        ]);

        return redirect()
            ->route('positions.index')
            ->with('status', 'Position updated successfully.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        $position->delete();

        return redirect()
            ->route('positions.index')
            ->with('status', 'Position deleted successfully.');
    }
}
