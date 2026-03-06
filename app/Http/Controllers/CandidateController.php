<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function index(): View
    {
        $candidates = Candidate::query()
            ->with('position')
            ->orderBy('name')
            ->get();

        return view('admin.candidates.index', compact('candidates'));
    }

    public function create(): View
    {
        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.candidates.create', compact('positions'));
    }

    public function store(StoreCandidateRequest $request): RedirectResponse
    {
        Candidate::create([
            'position_id' => $request->integer('position_id'),
            'name' => $request->input('name'),
            'party' => $request->input('party'),
            'is_active' => (bool) $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('candidates.index')
            ->with('status', 'Candidate created successfully.');
    }

    public function edit(Candidate $candidate): View
    {
        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.candidates.edit', compact('candidate', 'positions'));
    }

    public function update(UpdateCandidateRequest $request, Candidate $candidate): RedirectResponse
    {
        $candidate->update([
            'position_id' => $request->integer('position_id'),
            'name' => $request->input('name'),
            'party' => $request->input('party'),
            'is_active' => (bool) $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('candidates.index')
            ->with('status', 'Candidate updated successfully.');
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        $candidate->delete();

        return redirect()
            ->route('candidates.index')
            ->with('status', 'Candidate deleted successfully.');
    }
}
