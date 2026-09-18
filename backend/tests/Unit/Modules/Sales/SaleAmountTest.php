<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Sales;

use App\Modules\Sales\Domain\SaleAmount;
use InvalidArgumentException;
use Tests\TestCase;

final class SaleAmountTest extends TestCase
{
    public function test_large_unit_prices_multiply_and_add_exactly_as_integer_cents(): void
    {
        $lineTotal = SaleAmount::fromDecimal('99999999.99')->multiply(99999);
        $saleTotal = $lineTotal->add($lineTotal);

        $this->assertSame('9999899999000.01', $lineTotal->toDecimal());
        $this->assertSame('19999799998000.02', $saleTotal->toDecimal());
    }

    public function test_zero_sale_total_has_two_fractional_digits(): void
    {
        $this->assertSame('0.00', SaleAmount::zero()->toDecimal());
    }

    public function test_it_rejects_malformed_decimal_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SaleAmount::fromDecimal('12.5');
    }

    public function test_it_rejects_zero_or_negative_multipliers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SaleAmount::fromDecimal('1.00')->multiply(0);
    }
}
