@extends('layouts.app')

@section('title', 'Đặt lại mật khẩu')

@section('content')
    <div class="card" style="max-width:420px; margin:0 auto;">
        <h1>Đặt lại mật khẩu</h1>
        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">Mật khẩu mới (tối thiểu 8 ký tự)</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Xác nhận mật khẩu</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
            <button class="btn" type="submit">Đặt lại mật khẩu</button>
        </form>
    </div>
@endsection
