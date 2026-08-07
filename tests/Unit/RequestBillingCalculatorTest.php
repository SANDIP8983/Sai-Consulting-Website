<?php

namespace Tests\Unit;

use App\Models\RequestService;
use App\Services\RequestBillingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestBillingCalculatorTest extends TestCase
{
    private RequestBillingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RequestBillingCalculator;
    }

    public function test_only_accepted_service_snapshots_are_totalled(): void
    {
        $result = $this->calculator->calculate([
            $this->service('approved', 1000),
            $this->service('approved', 2500),
            $this->service('rejected', 9000),
            $this->service('under_review', 8000),
        ], 'none', 0, 18);

        $this->assertSame(3500.0, $result->totalProfessionalFee);
        $this->assertSame(3500.0, $result->netProfessionalFee);
        $this->assertSame(630.0, $result->gstAmount);
        $this->assertSame(4130.0, $result->grandTotal);
        $this->assertTrue($result->paymentRequired);
        $this->assertSame('pending', $result->paymentStatus);
    }

    #[DataProvider('discounts')]
    public function test_one_request_discount_is_applied_before_gst(string $type, float $value, float $discount, float $net, float $gst): void
    {
        $result = $this->calculator->calculate([$this->service('approved', 3000)], $type, $value, 18);

        $this->assertSame($discount, $result->discountAmount);
        $this->assertSame($net, $result->netProfessionalFee);
        $this->assertSame($gst, $result->gstAmount);
    }

    public static function discounts(): array
    {
        return [
            'fixed' => ['fixed', 300, 300.0, 2700.0, 486.0],
            'percentage' => ['percentage', 10, 300.0, 2700.0, 486.0],
        ];
    }

    public function test_government_charges_are_excluded_from_gst(): void
    {
        $result = $this->calculator->calculate(
            [$this->service('approved', 1000)],
            'none',
            0,
            18,
            [['name' => 'Stamp Duty', 'amount' => 500]],
        );

        $this->assertSame(180.0, $result->gstAmount);
        $this->assertSame(500.0, $result->governmentChargesTotal);
        $this->assertSame(1680.0, $result->grandTotal);
    }

    #[DataProvider('paymentStates')]
    public function test_payment_state_is_derived_from_the_frozen_total(float $fee, float $paid, bool $exempt, string $status, float $balance): void
    {
        $result = $this->calculator->calculate([$this->service('approved', $fee)], 'none', 0, 0, [], $paid, $exempt);

        $this->assertSame($status, $result->paymentStatus);
        $this->assertSame($balance, $result->balanceDue);
    }

    public static function paymentStates(): array
    {
        return [
            'genuine zero total' => [0, 0, false, 'not_required', 0.0],
            'explicit exemption' => [1000, 0, true, 'not_required', 0.0],
            'payable and unpaid' => [1000, 0, false, 'pending', 1000.0],
            'partially paid' => [1000, 400, false, 'partial', 600.0],
            'fully paid' => [1000, 1000, false, 'paid', 0.0],
        ];
    }

    public function test_invalid_discount_cannot_create_a_negative_net_fee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount cannot exceed the total professional fee.');

        $this->calculator->calculate([$this->service('approved', 1000)], 'fixed', 1000.01, 18);
    }

    private function service(string $status, float $fee): RequestService
    {
        return new RequestService([
            'status' => $status,
            'professional_fee' => $fee,
            'original_professional_fee' => $fee,
        ]);
    }
}
