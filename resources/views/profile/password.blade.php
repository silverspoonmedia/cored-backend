<x-layouts.app title="Ubah Kata Sandi">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Kata Sandi</li>
    </x-slot:breadcrumb>

    <div class="row">
        <div class="col-lg-6">
            <x-card title="Perbarui kata sandi">
                <x-form.ajax :action="route('profile.password.update')" method="PUT">
                    <x-form.input name="current_password" type="password" label="Kata sandi saat ini" required autocomplete="current-password" />
                    <x-form.input name="password" type="password" label="Kata sandi baru" required autocomplete="new-password" />
                    <x-form.input name="password_confirmation" type="password" label="Konfirmasi kata sandi baru" required autocomplete="new-password" />

                    <x-form.submit label="Simpan" class="btn btn-primary" />
                </x-form.ajax>
            </x-card>
        </div>
    </div>
</x-layouts.app>
