@extends('layouts.app')

@section('title', 'Reset Password | Katalisdotcom')

@php
    $hideLayout = true;
@endphp

@section('content')
    <div class="min-vh-100 container d-flex justify-content-center align-items-center">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <!-- Forgot Password -->
                <div class="card px-sm-6 px-0">
                    <div class="card-body">
                        <div class="app-brand justify-content-center">
                            <a href="#" class="app-brand-link gap-2">
                                <img src="{{ asset('img/logo.jpeg') }}" alt="Logo UNIMA" style="width: 40px; height: 40px;">
                                <span class="app-brand-text demo text-heading fw-bold">bkngftsl</span>
                            </a>
                        </div>
                        <h4 class="mb-1 mt-3 text-center">Buat Password Baru</h4>
                        <p class="mb-6 text-center">Silakan masukkan password baru Anda untuk mengamankan akun.</p>

                        @if ($errors->any())
                            <div class="alert alert-danger mb-4">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form id="formAuthentication" class="mb-6" action="{{ route('reset-password.reset') }}" method="POST">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}">
                            <input type="hidden" name="email" value="{{ $email }}">

                            <div class="mb-4 form-password-toggle">
                                <label class="form-label" for="password">Password Baru</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="password" class="form-control" name="password"
                                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                        autofocus required />
                                </div>
                            </div>

                            <div class="mb-6 form-password-toggle">
                                <label class="form-label" for="password_confirmation">Konfirmasi Password Baru</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="password_confirmation" class="form-control"
                                        name="password_confirmation"
                                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                        required />
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary d-grid w-100">Update Password</button>
                        </form>

                        <div class="text-center">
                            <a href="{{ route('login.index') }}" class="d-flex justify-content-center">
                                <i class="icon-base bx bx-chevron-left me-1"></i>
                                Batal dan kembali ke Login
                            </a>
                        </div>
                    </div>
                </div>
                <!-- /Forgot Password -->
            </div>
        </div>
    </div>
@endsection
