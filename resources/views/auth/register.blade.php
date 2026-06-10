<x-layouts.auth title="Daftar" heading="Daftar akun baru">
    <form method="POST" action="{{ route('register.store') }}">
        @csrf

        <x-form.input name="name" placeholder="Nama lengkap" icon="bi-person" required autofocus autocomplete="name" />
        <x-form.input name="email" type="email" placeholder="Email" icon="bi-envelope" required autocomplete="email" />
        <x-form.input name="password" type="password" placeholder="Kata sandi" icon="bi-lock-fill" required autocomplete="new-password" />
        <x-form.input name="password_confirmation" type="password" placeholder="Konfirmasi kata sandi" icon="bi-lock-fill" required autocomplete="new-password" />

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Daftar</button>
        </div>
    </form>

    <p class="mt-3 mb-0">
        Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
    </p>
</x-layouts.auth>
