<?php

namespace Tests\Unit;

use App\Enums\ShippingMethod;
use App\Rules\ContainerNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContainerNumberTest extends TestCase
{
    #[DataProvider('isoNumbers')]
    public function test_iso_6346_check_digit(string $number, bool $valid): void
    {
        $this->assertSame($valid, ContainerNumber::isValidIso6346($number));
    }

    public static function isoNumbers(): array
    {
        return [
            'from the sheet' => ['HLBU8324720', true],
            'from the sheet, MSC' => ['MEDU4711735', true],
            'from the invoice example' => ['QNNU1239571', true],
            'lower case is accepted' => ['hlbu8324720', true],
            'wrong check digit' => ['HLBU8324721', false],
            'too short' => ['HLBU832472', false],
            'wrong category letter' => ['HLBA8324720', false],
            'air waybill is not a container' => ['501-20079076', false],
        ];
    }

    #[DataProvider('awbNumbers')]
    public function test_air_waybill_check_digit(string $number, bool $valid): void
    {
        $this->assertSame($valid, ContainerNumber::isValidAirWaybill($number));
    }

    public static function awbNumbers(): array
    {
        return [
            'from the sheet' => ['501-20079076', true],
            'second from the sheet' => ['501-20079065', true],
            'without dash' => ['50120079076', true],
            'wrong check digit' => ['501-20079077', false],
            'container is not an awb' => ['HLBU8324720', false],
        ];
    }

    public function test_rule_depends_on_shipping_method(): void
    {
        $failed = null;
        $fail = function (string $message) use (&$failed) {
            $failed = $message;
        };

        (new ContainerNumber(ShippingMethod::Sea))->validate('container_no', 'HLBU8324720', $fail);
        $this->assertNull($failed);

        (new ContainerNumber(ShippingMethod::Air))->validate('container_no', 'HLBU8324720', $fail);
        $this->assertStringContainsString('air waybill', $failed);

        $failed = null;
        (new ContainerNumber(ShippingMethod::Air))->validate('container_no', '501-20079076', $fail);
        $this->assertNull($failed);
    }

    public function test_normalize_formats_air_waybills_with_a_dash(): void
    {
        $this->assertSame('501-20079076', ContainerNumber::normalize('50120079076', ShippingMethod::Air));
        $this->assertSame('HLBU8324720', ContainerNumber::normalize(' hlbu8324720 ', ShippingMethod::Sea));
    }
}
