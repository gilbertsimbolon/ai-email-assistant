@extends('layouts.app')

@section('title', 'Lupa Password | Katalisdotcom')

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
                        <!-- Logo -->
                        <div class="app-brand justify-content-center">
                            <a href="index.html" class="app-brand-link gap-2">
                                <img src="{{ asset('img/logo.jpeg') }}" alt="Logo UNIMA" style="width: 40px; height: 40px;">
                                <span class="app-brand-text demo text-heading fw-bold">bkngftsl</span>
                            </a>
                        </div>
                        <!-- /Logo -->
                        <h4 class="mb-1 mt-3 text-center">Lupa Password?</h4>
                        <p class="mb-6 text-center">Masukkan email yang terdaftar, agar kami mengirimkan link reset password.</p>
                        <form id="formAuthentication" class="mb-6" action="{{ route('lupa-password.kirim') }}" method="POST">
                            @csrf
                            <div class="mb-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="text" class="form-control" id="email" name="email"
                                    placeholder="Enter your email" autofocus />
                            </div>
                            <button class="btn btn-primary d-grid w-100">Send Reset Link</button>
                        </form>
                        <div class="text-center">
                            <a href="{{ route('login.index') }}" class="d-flex justify-content-center">
                                <i class="icon-base bx bx-chevron-left me-1"></i>
                                Kembali ke halaman Login.
                            </a>
                        </div>
                    </div>
                </div>
                <!-- /Forgot Password -->
            </div>
        </div>
    </div>
@endsection
