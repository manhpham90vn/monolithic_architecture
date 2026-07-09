@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
    <div class="card" style="max-width:420px; margin:0 auto;">
        <h1>Đăng nhập</h1>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">Mật khẩu</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label style="font-weight:400;">
                    <input type="checkbox" name="remember" style="width:auto;"> Ghi nhớ đăng nhập
                </label>
            </div>
            <button class="btn" type="submit">Đăng nhập</button>
            <a class="muted" style="margin-left:12px;" href="{{ route('password.request') }}">Quên mật khẩu?</a>
        </form>
        <p class="muted" style="margin-top:16px;">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></p>
    </div>
@endsection
