<x-layouts.auth title="Lupa Kata Sandi" heading="Masukkan email Anda untuk menerima tautan reset">
    <x-form.ajax :action="route('password.email')">
        <x-form.input name="email" type="email" placeholder="Email" icon="bi-envelope" required autofocus autocomplete="email" />

        <div class="d-grid">
            <x-form.submit label="Kirim tautan reset" class="btn btn-primary" />
        </div>
    </x-form.ajax>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
</x-layouts.auth>
