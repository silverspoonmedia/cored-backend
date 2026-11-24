<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\Concerns\ValidatesTranslatableInput;
use App\Support\Localization\LocalePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use Tests\TestCase;

/**
 * The trait must tell a supported locale from an unknown key in a translatable
 * map: `en` and `id` are accepted, a locale the application no longer supports
 * (`zh_CN`) or one it never had (`fr`) is rejected rather than silently stored.
 */
class ValidatesTranslatableInputTest extends TestCase
{
    public function test_supported_locale_keys_are_accepted(): void
    {
        $request = TranslatableTestRequest::create('/', 'POST', [
            'name' => [
                'en' => 'Label',
                'id' => 'Label Indonesia',
            ],
        ]);

        $validator = ValidatorFacade::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());

        $validated = $validator->validated();

        $this->assertSame(['en' => 'Label', 'id' => 'Label Indonesia'], $validated['name']);
    }

    public function test_a_retired_locale_is_rejected_like_any_unknown_key(): void
    {
        $request = TranslatableTestRequest::create('/', 'POST', [
            'name' => [
                'en' => 'Label',
                'zh_CN' => '中文标签',
            ],
        ]);

        $validator = ValidatorFacade::make($request->all(), $request->rules());
        $request->withValidator($validator);

        // `zh_CN` left supported_locales; the route must not persist a translation
        // nobody can read back.
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('zh_CN', (string) data_get($validator->errors()->toArray(), 'name.0'));
    }

    public function test_unknown_locale_keys_are_rejected(): void
    {
        $request = TranslatableTestRequest::create('/', 'POST', [
            'name' => [
                'en' => 'Label',
                'fr' => 'Clé inconnue',
            ],
        ]);

        $validator = ValidatorFacade::make($request->all(), $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertStringContainsString('fr', (string) data_get($validator->errors()->toArray(), 'name.0'));
    }
}

/**
 * Minimal concrete FormRequest exposing the trait's behavior.
 */
class TranslatableTestRequest extends FormRequest
{
    use ValidatesTranslatableInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateTranslatableString($validator, 'name', LocalePreference::supported());
        });
    }
}
