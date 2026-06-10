<x-layouts.auth title="Masuk" heading="Masuk untuk memulai sesi Anda">
    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <x-form.input name="email" type="email" placeholder="Email" icon="bi-envelope" required autofocus autocomplete="email" />
        <x-form.input name="password" type="password" placeholder="Kata sandi" icon="bi-lock-fill" required autocomplete="current-password" />

        <div class="row align-items-center">
            <div class="col-7">
                <x-form.checkbox name="remember_me" label="Ingat saya" />
            </div>
            <div class="col-5">
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Masuk</button>
                </div>
            </div>
        </div>
    </form>

    <p class="mt-3 mb-1">
        <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
    </p>
    <p class="mb-0">
        Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a>
    </p>
</x-layouts.auth>
