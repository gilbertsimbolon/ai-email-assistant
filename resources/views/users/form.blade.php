@extends('layouts.app')

@section('title', ($user->exists ? 'Edit' : 'Tambah') . ' User | Administration')

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bx bx-user me-1 text-primary"></i> {{ $user->exists ? 'Edit' : 'Tambah' }} User
                </h5>
            </div>

            <div class="card-body">
                <form
                    action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
                    method="POST">

                    @csrf
                    @if ($user->exists)
                        @method('PUT')
                    @endif

                    <div class="mb-4">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password {{ $user->exists ? '(kosongkan jika tidak diubah)' : '' }}</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               {{ $user->exists ? '' : 'required' }}>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Role</label>
                        @php
                            $currentRole = old('role', $user->roles->first()->name ?? 'Agent');
                        @endphp
                        <select name="role" class="form-select @error('role') is-invalid @enderror">
                            @foreach (['Admin', 'Agent'] as $role)
                                <option value="{{ $role }}" @selected($currentRole === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
