<?php

namespace Tests\Unit\Enums;

use App\Enums\ExamStatus;
use PHPUnit\Framework\TestCase;

class ExamStatusTest extends TestCase
{
    public function test_exam_status_has_all_expected_cases(): void
    {
        $cases = ExamStatus::cases();
        $values = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('NE', $values);
        $this->assertContains('SP', $values);
        $this->assertContains('DV', $values);
        $this->assertContains('EX', $values);
        $this->assertContains('ABS', $values);
        $this->assertContains('WH', $values);
        $this->assertCount(6, $cases);
    }

    public function test_exam_status_labels_are_correct(): void
    {
        $this->assertSame('Not Entered', ExamStatus::NotEntered->label());
        $this->assertSame('Supplementary', ExamStatus::Supplementary->label());
        $this->assertSame('Deferred', ExamStatus::Deferred->label());
        $this->assertSame('Exempt', ExamStatus::Exempt->label());
        $this->assertSame('Absent', ExamStatus::Absent->label());
        $this->assertSame('Withheld', ExamStatus::Withheld->label());
    }

    public function test_exam_status_can_be_created_from_value(): void
    {
        $this->assertSame(ExamStatus::NotEntered, ExamStatus::from('NE'));
        $this->assertSame(ExamStatus::Supplementary, ExamStatus::from('SP'));
        $this->assertSame(ExamStatus::Absent, ExamStatus::from('ABS'));
        $this->assertSame(ExamStatus::Withheld, ExamStatus::from('WH'));
    }
}
