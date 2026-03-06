@extends('layouts.app')

@section('content')
<div class="card">
    <h1>Edit Position</h1>

    <form action="{{ route('positions.update', $position) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-12">
            <label for="name">Name</label><br>
            <input id="name" type="text" name="name" value="{{ old('name', $position->name) }}" required>
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-12">
            <label for="display_order">Display Order</label><br>
            <input id="display_order" type="number" min="0" name="display_order" value="{{ old('display_order', $position->display_order) }}">
            @error('display_order') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Update</button>
            <a class="btn btn-muted" href="{{ route('positions.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
