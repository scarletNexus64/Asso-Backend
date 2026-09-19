<?php

namespace Tests\Unit;

use App\Services\StripeService;
use PHPUnit\Framework\TestCase;

class StripeAmountTest extends TestCase
{
    public function test_amount_is_converted_to_stripe_minor_units(): void
    {
        $this->assertSame(21283, StripeService::toMinorUnits(212.83, 'USD'));
        $this->assertSame(19500, StripeService::toMinorUnits(195, 'eur'));
        // Devises sans décimales : pas de ×100 (sinon 100 fois le prix).
        $this->assertSame(127500, StripeService::toMinorUnits(127500, 'XAF'));
        $this->assertSame(5000, StripeService::toMinorUnits(5000, 'xof'));
    }
}
