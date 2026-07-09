@extends('layouts.app')

@section('title', 'Đăng ký')

@section('content')
    <div class="card" style="max-width:420px; margin:0 auto;">
        <h1>Đăng ký</h1>
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label for="name">Họ tên</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label for="password">Mật khẩu (tối thiểu 8 ký tự)</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Xác nhận mật khẩu</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
            <button class="btn" type="submit">Đăng ký</button>
        </form>
        <p class="muted" style="margin-top:16px;">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
    </div>
@endsection
