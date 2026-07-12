<?php

namespace Tests\Support\Encryption;

use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use App\Support\Oci\Kms\LocalKmsDekClient;
use App\Support\Oci\Kms\OciKmsDekResult;

class CountingKmsDekClient implements KmsDekClientInterface
{
    public int $generateCount = 0;

    public function __construct(
        private readonly KmsDekClientInterface $inner = new LocalKmsDekClient,
    ) {}

    public function generateDek(): OciKmsDekResult
    {
        $this->generateCount++;

        return $this->inner->generateDek();
    }

    public function decryptDek(string $wrappedDek): OciKmsDekResult
    {
        return $this->inner->decryptDek($wrappedDek);
    }
}
