<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\Concerns\ValidatesTranslatableInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use Tests\TestCase;

/**
 * The trait must accept SPA locale aliases (`zh-CN`) in translatable maps,
 * canonicalize them to backend codes (`zh_CN`) in the request payload, and
 * still reject unknown locale keys.
 */
class ValidatesTranslatableInputTest extends TestCase
{
    public function test_alias_keys_are_canonicalized_in_request_payload(): void
    {
        $request = TranslatableTestRequest::create('/', 'POST', [
            'name' => [
                'en' => 'Label',
                'zh-CN' => '中文标签',
            ],
        ]);

        $validator = ValidatorFacade::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());

        $validated = $validator->validated();

        $this->assertSame('中文标签', $validated['name']['zh_CN']);
        $this->assertArrayNotHasKey('zh-CN', $validated['name']);
    }

    public function test_mixed_keys_merge_with_alias_winning_last(): void
    {
        $request = TranslatableTestRequest::create('/', 'POST', [
            'name' => [
                'en' => 'Label',
                'zh_CN' => '旧标签',
                'zh-CN' => '新标签',
            ],
        ]);

        $validator = ValidatorFacade::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());

        $validated = $validator->validated();

        $this->assertSame('新标签', $validated['name']['zh_CN']);
        $this->assertArrayNotHasKey('zh-CN', $validated['name']);
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
            $this->validateTranslatableString($validator, 'name', \App\Support\Localization\LocalePreference::supported());
        });
    }
}
