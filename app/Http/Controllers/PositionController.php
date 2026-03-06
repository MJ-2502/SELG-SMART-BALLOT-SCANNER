<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.positions.index', compact('positions'));
    }

    public function create(): View
    {
        return view('admin.positions.create');
    }

    public function store(StorePositionRequest $request): RedirectResponse
    {
        Position::create([
            'name' => $request->input('name'),
            'display_order' => $request->integer('display_order', 0),
        ]);

        return redirect()
            ->route('positions.index')
            ->with('status', 'Position created successfully.');
    }

    public function edit(Position $position): View
    {
        return view('admin.positions.edit', compact('position'));
    }

    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $position->update([
            'name' => $request->input('name'),
            'display_order' => $request->integer('display_order', 0),
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
