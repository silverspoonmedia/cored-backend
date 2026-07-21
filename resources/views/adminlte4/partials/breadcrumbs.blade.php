{{--
    Breadcrumb renderer for the authenticated AdminLTE shell.
    Fed by routes/breadcrumbs.php via Breadcrumbs::view() in adminlte4/components/layouts/app.
    Markup mirrors the previous hardcoded <ol class="breadcrumb float-sm-end">.
--}}
@unless ($breadcrumbs->isEmpty())
    <nav aria-label="{{ __('ui.labels.breadcrumb') }}">
        <ol class="breadcrumb float-sm-end">
        @foreach ($breadcrumbs as $breadcrumb)
            @if ($breadcrumb->url && ! $loop->last)
                <li class="breadcrumb-item"><a href="{{ $breadcrumb->url }}">{{ $breadcrumb->title }}</a></li>
            @else
                <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb->title }}</li>
            @endif
        @endforeach
        </ol>
    </nav>
@endunless
