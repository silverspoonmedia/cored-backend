<?php

namespace Tests\Domain\PaymentGateway\Unit\Support\Routing;

use App\Domain\PaymentGateway\Support\Routing\VaBankCatalog;
use Tests\TestCase;

class VaBankCatalogTest extends TestCase
{
    public function test_includes_override_banks(): void
    {
        $catalog = new VaBankCatalog;

        $this->assertNotNull($catalog->find('seabank'));
        $this->assertNotNull($catalog->find('artha_graha'));
        $this->assertSame('bag', $catalog->find('artha_graha')?->ipaymuCode);
        $this->assertSame('seabank', $catalog->find('seabank')?->midtransCode);
        $this->assertNull($catalog->find('seabank')?->ipaymuCode);
    }
}
