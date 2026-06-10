<x-layouts.auth title="Lupa Kata Sandi" heading="Masukkan email Anda untuk menerima tautan reset">
    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-form.input name="email" type="email" placeholder="Email" icon="bi-envelope" required autofocus autocomplete="email" />

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Kirim tautan reset</button>
        </div>
    </form>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
</x-layouts.auth>
