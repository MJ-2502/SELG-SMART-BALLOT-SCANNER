@extends('layouts.app')

@section('content')
<div class="card">
    <h1>Add Position</h1>

    <form action="{{ route('positions.store') }}" method="POST">
        @csrf

        <div class="mb-12">
            <label for="name">Name</label><br>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required>
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-12">
            <label for="display_order">Display Order</label><br>
            <input id="display_order" type="number" min="0" name="display_order" value="{{ old('display_order', 0) }}">
            @error('display_order') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a class="btn btn-muted" href="{{ route('positions.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
