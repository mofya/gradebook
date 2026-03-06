<?php

namespace Tests\Unit\Enums;

use App\Enums\OfferingStatus;
use PHPUnit\Framework\TestCase;

class OfferingStatusTest extends TestCase
{
    public function test_offering_status_has_all_expected_cases(): void
    {
        $cases = OfferingStatus::cases();
        $values = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('draft', $values);
        $this->assertContains('active', $values);
        $this->assertContains('locked', $values);
        $this->assertContains('published', $values);
        $this->assertCount(4, $cases);
    }

    public function test_offering_status_labels_are_correct(): void
    {
        $this->assertSame('Draft', OfferingStatus::Draft->label());
        $this->assertSame('Active', OfferingStatus::Active->label());
        $this->assertSame('Locked', OfferingStatus::Locked->label());
        $this->assertSame('Published', OfferingStatus::Published->label());
    }
}
