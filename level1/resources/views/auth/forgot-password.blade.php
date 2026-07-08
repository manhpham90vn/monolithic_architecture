@extends('layouts.app')

@section('title', 'Quên mật khẩu')

@section('content')
    <div class="card" style="max-width:420px; margin:0 auto;">
        <h1>Quên mật khẩu</h1>
        <p class="muted">Nhập email, chúng tôi sẽ gửi liên kết đặt lại mật khẩu.</p>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <button class="btn" type="submit">Gửi liên kết đặt lại</button>
        </form>
        <p class="muted" style="margin-top:16px;"><a href="{{ route('login') }}">← Quay lại đăng nhập</a></p>
    </div>
@endsection
