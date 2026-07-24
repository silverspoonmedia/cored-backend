<?php

namespace Tests\Unit\Support\Presentation;

use App\Support\Presentation\BytePresenter;
use Tests\TestCase;

class BytePresenterTest extends TestCase
{
    public function test_null_is_null(): void
    {
        $this->assertNull(BytePresenter::human(null));
    }

    public function test_zero_is_zero_b(): void
    {
        $this->assertSame('0 B', BytePresenter::human(0));
    }

    public function test_bytes_under_kilobyte(): void
    {
        $this->assertSame('512 B', BytePresenter::human(512));
    }

    public function test_kilobytes(): void
    {
        $this->assertSame('1.5 KB', BytePresenter::human(1536));
    }

    public function test_megabytes(): void
    {
        $this->assertSame('1.5 MB', BytePresenter::human((int) (1.5 * 1024 * 1024)));
    }

    public function test_gigabytes(): void
    {
        $this->assertSame('2.0 GB', BytePresenter::human(2 * 1024 * 1024 * 1024));
    }

    public function test_negative_clamped_to_zero(): void
    {
        $this->assertSame('0 B', BytePresenter::human(-10));
    }

    public function test_non_numeric_is_null(): void
    {
        $this->assertNull(BytePresenter::human('abc'));
    }
}
