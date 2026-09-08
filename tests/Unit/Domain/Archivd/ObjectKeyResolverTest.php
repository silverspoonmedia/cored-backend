<?php

namespace Tests\Unit\Domain\Archivd;

use App\Domain\Archivd\Support\ObjectKeyResolver;
use Tests\TestCase;

class ObjectKeyResolverTest extends TestCase
{
    private ObjectKeyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        config(['archivd.directory' => 'archivd']);
        $this->resolver = new ObjectKeyResolver;
    }

    public function test_generate_object_id_is_40_lowercase_alnum(): void
    {
        $id = $this->resolver->generateObjectId();

        $this->assertSame(40, strlen($id));
        $this->assertMatchesRegularExpression('/^[a-z0-9]{40}$/', $id);
        $this->assertTrue($this->resolver->isValidObjectId($id));
    }

    public function test_object_key_uses_directory_user_and_object_id_only(): void
    {
        $userId = '9f3c1111-2222-3333-4444-555566667777';
        $objectId = str_repeat('a', 40);

        $this->assertSame(
            'archivd/'.$userId.'/'.$objectId,
            $this->resolver->objectKey($userId, $objectId),
        );
    }

    public function test_object_key_has_no_extension_or_folder_segment(): void
    {
        $key = $this->resolver->objectKey('user-uuid', str_repeat('b', 40));

        $this->assertSame(3, count(explode('/', $key)));
        $this->assertStringNotContainsString('.', $key);
    }

    public function test_is_valid_object_id_rejects_full_keys_and_short_ids(): void
    {
        $this->assertFalse($this->resolver->isValidObjectId('archivd/u/abc'));
        $this->assertFalse($this->resolver->isValidObjectId(str_repeat('a', 39)));
        $this->assertFalse($this->resolver->isValidObjectId(str_repeat('A', 40)));
        $this->assertFalse($this->resolver->isValidObjectId(str_repeat('a', 40).'.pdf'));
    }

    public function test_directory_config_is_trimmed(): void
    {
        config(['archivd.directory' => '/custom-prefix/']);

        $this->assertSame(
            'custom-prefix/u/'.str_repeat('c', 40),
            $this->resolver->objectKey('u', str_repeat('c', 40)),
        );
    }
}
