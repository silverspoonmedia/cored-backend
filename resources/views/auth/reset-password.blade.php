<x-layouts.auth title="Atur Ulang Kata Sandi" heading="Pilih kata sandi baru Anda">
    <x-form.ajax :action="route('password.update')">
        <input type="hidden" name="token" value="{{ $token }}" />

        <x-form.input name="email" type="email" placeholder="Email" icon="bi-envelope" :value="$email" required autocomplete="email" />
        <x-form.input name="password" type="password" placeholder="Kata sandi baru" icon="bi-lock-fill" required autocomplete="new-password" />
        <x-form.input name="password_confirmation" type="password" placeholder="Konfirmasi kata sandi" icon="bi-lock-fill" required autocomplete="new-password" />

        <div class="d-grid">
            <x-form.submit label="Atur ulang kata sandi" class="btn btn-primary" />
        </div>
    </x-form.ajax>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
</x-layouts.auth>
