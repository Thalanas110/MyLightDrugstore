<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Security;

use App\Modules\Security\Infrastructure\Crypto\LookupDigestKeyFactory;
use InvalidArgumentException;
use Tests\TestCase;

final class LookupDigestKeyFactoryTest extends TestCase
{
    public function test_it_decodes_a_canonical_base64_key(): void
    {
        $key = str_repeat('l', 32);

        $lookupKey = (new LookupDigestKeyFactory)->fromBase64(base64_encode($key));

        $this->assertSame($key, $lookupKey->key);
    }

    public function test_it_rejects_missing_noncanonical_and_wrong_length_keys(): void
    {
        $factory = new LookupDigestKeyFactory;

        foreach (['', '***', base64_encode(str_repeat('l', 32))."\n", base64_encode(str_repeat('l', 31))] as $encodedKey) {
            try {
                $factory->fromBase64($encodedKey);
                $this->fail('Invalid lookup digest key was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
