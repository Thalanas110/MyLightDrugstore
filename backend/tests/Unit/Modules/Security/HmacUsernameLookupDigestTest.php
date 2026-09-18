<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Security;

use App\Modules\Security\Domain\Crypto\LookupDigestKey;
use App\Modules\Security\Infrastructure\Crypto\HmacUsernameLookupDigest;
use InvalidArgumentException;
use Tests\TestCase;

final class HmacUsernameLookupDigestTest extends TestCase
{
    public function test_it_returns_a_stable_sha_256_hmac_for_a_normalized_username(): void
    {
        $key = str_repeat('l', 32);
        $lookup = new HmacUsernameLookupDigest(new LookupDigestKey($key));

        $digest = $lookup->digest('pharmacy.staff');

        $this->assertSame(hash_hmac('sha256', 'my-light-drugstore|username-lookup|v1|pharmacy.staff', $key), $digest);
        $this->assertSame(64, strlen($digest));
        $this->assertSame($digest, $lookup->digest('pharmacy.staff'));
    }

    public function test_different_usernames_have_different_lookup_digests(): void
    {
        $lookup = new HmacUsernameLookupDigest(new LookupDigestKey(str_repeat('l', 32)));

        $this->assertNotSame($lookup->digest('staff.one'), $lookup->digest('staff.two'));
    }

    public function test_lookup_digest_keys_must_be_exactly_32_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LookupDigestKey(str_repeat('l', 31));
    }

    public function test_empty_normalized_usernames_are_rejected(): void
    {
        $lookup = new HmacUsernameLookupDigest(new LookupDigestKey(str_repeat('l', 32)));

        $this->expectException(InvalidArgumentException::class);

        $lookup->digest('');
    }
}
