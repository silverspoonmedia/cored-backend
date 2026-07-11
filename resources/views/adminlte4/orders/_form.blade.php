{{--
    Shared order create/edit form (master + dynamic detail lines).
    Expected variables:
      $action      route URL
      $method      'POST' | 'PUT'
      $order       array|null  the API order payload (edit only)
      $submitLabel string

    Subtotal / grand total are computed server-side from the lines; this form
    only collects the inputs and posts `details[]` as an array to the web
    controller, which forwards the whole payload to the API.
--}}
@php($order = $order ?? [])
@php($details = old('details', data_get($order, 'details', [[]])))
@php($isCreate = empty(data_get($order, 'id')))
@php($statuses = ['pending', 'processing', 'completed', 'cancelled'])
@php($maxBytes = (int) config('attachments.max_file_size_bytes'))
@php($attachmentMaxSizeLabel = $maxBytes >= 1073741824
    ? round($maxBytes / 1073741824, 1).' GB'
    : round($maxBytes / 1048576, 0).' MB')

<x-adminlte4::form.ajax :action="$action" :method="$method">
    <h5 class="mb-3">{{ __('web/orders.form.customer_section') }}</h5>

    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.input
                name="customer_name"
                :label="__('web/orders.form.customer_name')"
                :value="data_get($order, 'customer_name')"
                required
            />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.input
                name="customer_email"
                type="email"
                :label="__('web/orders.form.customer_email')"
                :value="data_get($order, 'customer_email')"
                required
            />
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.input
                name="customer_phone"
                :label="__('web/orders.form.customer_phone')"
                :value="data_get($order, 'customer_phone')"
            />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.select
                name="status"
                :label="__('web/orders.form.status')"
                :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => __('web/orders.status.'.$s)])"
                :selected="data_get($order, 'status', 'pending')"
            />
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.input
                name="discount"
                type="number"
                step="0.01"
                min="0"
                :label="__('web/orders.form.discount')"
                :value="data_get($order, 'discount', 0)"
            />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.input
                name="tax"
                type="number"
                step="0.01"
                min="0"
                :label="__('web/orders.form.tax')"
                :value="data_get($order, 'tax', 0)"
            />
        </div>
    </div>

    <div class="mb-3" data-form-field="notes">
        <label for="notes" class="form-label">{{ __('web/orders.form.notes') }}</label>
        <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', data_get($order, 'notes')) }}</textarea>
        <x-adminlte4::form.error name="notes" />
    </div>

    <hr>

    <div class="d-flex align-items-center mb-3">
        <h5 class="mb-0 flex-grow-1">{{ __('web/orders.form.details_section') }}</h5>
        <button type="button" class="btn btn-outline-primary btn-sm" data-add-line>
            <i class="bi bi-plus-lg me-1"></i>{{ __('web/orders.form.add_line') }}
        </button>
    </div>

    <p class="text-muted small">{{ __('web/orders.form.totals_hint') }}</p>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th style="width: 32%">{{ __('web/orders.form.product_name') }}</th>
                    <th style="width: 12%">{{ __('web/orders.form.quantity') }}</th>
                    <th style="width: 18%">{{ __('web/orders.form.unit_price') }}</th>
                    <th style="width: 18%">{{ __('web/orders.form.line_discount') }}</th>
                    <th style="width: 15%">{{ __('web/orders.form.line_notes') }}</th>
                    <th style="width: 5%"></th>
                </tr>
            </thead>
            <tbody data-line-rows>
                @foreach ($details as $i => $line)
                    <tr data-line-row>
                        <td>
                            {{-- Preserve the existing line id so the API updates the row in place (keeps its UUID). --}}
                            @if (data_get($line, 'id'))
                                <input type="hidden" name="details[{{ $i }}][id]" value="{{ data_get($line, 'id') }}">
                            @endif
                            <input type="text" name="details[{{ $i }}][product_name]" class="form-control form-control-sm"
                                   value="{{ data_get($line, 'product_name') }}" required>
                        </td>
                        <td>
                            <input type="number" min="1" step="1" name="details[{{ $i }}][quantity]" class="form-control form-control-sm"
                                   value="{{ data_get($line, 'quantity', 1) }}" required>
                        </td>
                        <td>
                            <input type="number" min="0" step="0.01" name="details[{{ $i }}][unit_price]" class="form-control form-control-sm"
                                   value="{{ data_get($line, 'unit_price', 0) }}" required>
                        </td>
                        <td>
                            <input type="number" min="0" step="0.01" name="details[{{ $i }}][discount]" class="form-control form-control-sm"
                                   value="{{ data_get($line, 'discount', 0) }}">
                        </td>
                        <td>
                            <input type="text" name="details[{{ $i }}][notes]" class="form-control form-control-sm"
                                   value="{{ data_get($line, 'notes') }}">
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-line title="{{ __('web/orders.form.remove_line') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-adminlte4::form.error name="details" />

    <hr>

    <h5 class="mb-3">{{ __('web/orders.form.attachments_section') }}</h5>

    @php($existingAttachments = data_get($order, 'attachment', []))
    @if (! empty($existingAttachments))
        {{--
            Marker so the server can tell "this form manages attachments" apart
            from a plain API call. Unchecked checkboxes are NOT submitted, so if
            the user unticks every file `keep_attachments` would be absent and
            the API would (correctly, for API callers) keep all. This hidden
            field lets the web controller send an explicit empty keep-list in
            that case, so unticking all really removes all.
        --}}
        <input type="hidden" name="attachments_managed" value="1">

        {{-- Existing files: unchecking removes the file on save (keep_attachments[] carries the kept paths). --}}
        <div class="mb-3">
            <label class="form-label">{{ __('web/orders.form.current_attachments') }}</label>
            <ul class="list-group">
                @foreach ($existingAttachments as $file)
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <div class="form-check flex-grow-1 mb-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="keep_attachments[]"
                                value="{{ data_get($file, 'path') }}"
                                id="keep-{{ $loop->index }}"
                                checked
                            >
                            <label class="form-check-label" for="keep-{{ $loop->index }}">
                                @if (data_get($file, 'url'))
                                    <a href="{{ data_get($file, 'url') }}" target="_blank" rel="noopener">{{ data_get($file, 'name') }}</a>
                                @else
                                    {{ data_get($file, 'name') }}
                                @endif
                                <small class="text-muted">({{ number_format((int) data_get($file, 'size', 0) / 1024, 1) }} KB)</small>
                            </label>
                        </div>
                        <span class="badge text-bg-light border">{{ __('web/orders.form.keep_hint') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div
        class="mb-3"
        data-form-field="staged_attachments"
        data-direct-upload
        data-init-url="{{ route('orders.attachments.uploads.store') }}"
        data-status-url="{{ route('orders.attachments.uploads.show', ['upload' => '__UPLOAD__']) }}"
        data-complete-url="{{ route('orders.attachments.uploads.complete', ['upload' => '__UPLOAD__']) }}"
        data-abort-url="{{ route('orders.attachments.uploads.destroy', ['upload' => '__UPLOAD__']) }}"
        data-sign-parts-url="{{ route('orders.attachments.uploads.sign-parts', ['upload' => '__UPLOAD__']) }}"
        data-max-files="{{ (int) config('attachments.max_files_per_order') }}"
        data-max-concurrent="{{ (int) config('attachments.max_concurrent_uploads') }}"
        data-messages="{{ json_encode([
            'uploading' => __('web/orders.form.upload_uploading'),
            'pending' => __('web/orders.form.upload_pending'),
            'waiting' => __('web/orders.form.upload_waiting'),
            'done' => __('web/orders.form.upload_done'),
            'uploadFailed' => __('web/orders.form.upload_failed'),
            'tooManyFiles' => __('web/orders.form.upload_too_many'),
            'waitForUploads' => __('web/orders.form.upload_wait'),
            'remove' => __('web/orders.form.upload_remove'),
        ]) }}"
    >
        <label class="form-label">{{ __('web/orders.form.add_attachments') }}</label>
        <input
            type="file"
            class="form-control"
            data-direct-upload-input
            multiple
        >
        <small class="form-text text-muted">{{ __('web/orders.form.attachments_hint', [
            'count' => (int) config('attachments.max_files_per_order'),
            'size' => $attachmentMaxSizeLabel ?? '5 GB',
        ]) }}</small>
        <small class="form-text text-muted d-block">{{ __('web/orders.form.upload_concurrency_hint', [
            'count' => (int) config('attachments.max_concurrent_uploads'),
        ]) }}</small>
        <div class="mt-2" data-direct-upload-queue></div>
        <button
            type="button"
            class="btn btn-outline-primary btn-sm mt-2"
            data-direct-upload-start
            disabled
        >{{ __('web/orders.form.upload_start') }}</button>
        <div class="mt-2" data-direct-upload-staged></div>
        <x-adminlte4::form.error name="staged_attachments" />
        <x-adminlte4::form.error name="staged_attachments.0" />
    </div>

    <div class="d-flex gap-2 mt-3">
        <x-adminlte4::form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">{{ __('ui.actions.cancel') }}</a>
    </div>
</x-adminlte4::form.ajax>

<script>
    (function () {
        const tbody = document.querySelector('[data-line-rows]');
        if (!tbody) {
            return;
        }

        const removeTitle = @json(__('web/orders.form.remove_line'));

        function rowTemplate(index) {
            return '<tr data-line-row>'
                + '<td><input type="text" name="details[' + index + '][product_name]" class="form-control form-control-sm" required></td>'
                + '<td><input type="number" min="1" step="1" name="details[' + index + '][quantity]" class="form-control form-control-sm" value="1" required></td>'
                + '<td><input type="number" min="0" step="0.01" name="details[' + index + '][unit_price]" class="form-control form-control-sm" value="0" required></td>'
                + '<td><input type="number" min="0" step="0.01" name="details[' + index + '][discount]" class="form-control form-control-sm" value="0"></td>'
                + '<td><input type="text" name="details[' + index + '][notes]" class="form-control form-control-sm"></td>'
                + '<td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-line title="' + removeTitle + '"><i class="bi bi-trash"></i></button></td>'
                + '</tr>';
        }

        // Monotonic index so removing then adding never collides on a name.
        let nextIndex = tbody.querySelectorAll('[data-line-row]').length;

        document.querySelector('[data-add-line]')?.addEventListener('click', function () {
            tbody.insertAdjacentHTML('beforeend', rowTemplate(nextIndex++));
        });

        tbody.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-line]');
            if (!button) {
                return;
            }

            // Keep at least one line — the API rejects an empty order anyway.
            if (tbody.querySelectorAll('[data-line-row]').length > 1) {
                button.closest('[data-line-row]').remove();
            }
        });

        // Ensure there is always a starting row.
        if (tbody.querySelectorAll('[data-line-row]').length === 0) {
            tbody.insertAdjacentHTML('beforeend', rowTemplate(nextIndex++));
        }
    })();
</script>

@vite('resources/js/orders/direct-upload.js')
