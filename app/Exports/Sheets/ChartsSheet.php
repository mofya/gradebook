<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class ChartsSheet implements FromArray, WithCharts, WithTitle
{
    protected const GRADES = ['NE', 'D', 'D+', 'C', 'C+', 'B', 'B+', 'A', 'A+'];

    /** @var array<int, array<int, mixed>> */
    protected array $rows = [];

    /** @var array<string, array<string, int>> */
    protected array $genderGradeCounts;

    /** @var array<string, int> */
    protected array $overallDistribution;

    /**
     * @param  array<string, array<string, int>>  $genderGradeCounts
     * @param  array<string, int>  $overallDistribution
     */
    public function __construct(
        array $genderGradeCounts,
        array $overallDistribution,
    ) {
        $this->genderGradeCounts = $genderGradeCounts;
        $this->overallDistribution = $overallDistribution;
        $this->buildRows();
    }

    public function title(): string
    {
        return 'Charts';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return Chart[]
     */
    public function charts(): array
    {
        $genderChart = $this->createGenderChart();
        $genderChart->setTopLeftPosition('A8');
        $genderChart->setBottomRightPosition('K26');

        $overallChart = $this->createOverallChart();
        $overallChart->setTopLeftPosition('A28');
        $overallChart->setBottomRightPosition('K46');

        return [$genderChart, $overallChart];
    }

    protected function buildRows(): void
    {
        $maleCounts = $this->genderGradeCounts['Male'] ?? array_fill_keys(self::GRADES, 0);
        $femaleCounts = $this->genderGradeCounts['Female'] ?? array_fill_keys(self::GRADES, 0);

        // Row 1: header
        $row1 = [''];
        foreach (self::GRADES as $grade) {
            $row1[] = $grade;
        }
        $this->rows[] = $row1;

        // Row 2: Male
        $row2 = ['Male'];
        foreach (self::GRADES as $grade) {
            $row2[] = $maleCounts[$grade] ?? 0;
        }
        $this->rows[] = $row2;

        // Row 3: Female
        $row3 = ['Female'];
        foreach (self::GRADES as $grade) {
            $row3[] = $femaleCounts[$grade] ?? 0;
        }
        $this->rows[] = $row3;

        // Row 4: blank
        $this->rows[] = [];

        // Row 5: Grade header for overall
        $row5 = ['Grade'];
        foreach (self::GRADES as $grade) {
            $row5[] = $grade;
        }
        $this->rows[] = $row5;

        // Row 6: Overall counts
        $row6 = ['Count'];
        foreach (self::GRADES as $grade) {
            $row6[] = $this->overallDistribution[$grade] ?? 0;
        }
        $this->rows[] = $row6;
    }

    protected function createGenderChart(): Chart
    {
        $sheetName = 'Charts';

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$A\$2", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$A\$3", null, 1),
        ];

        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$B\$1:\$J\$1", null, 9),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$B\$1:\$J\$1", null, 9),
        ];

        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetName}'!\$B\$2:\$J\$2", null, 9),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetName}'!\$B\$3:\$J\$3", null, 9),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STACKED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title = new Title('Grade Distribution by Gender');

        return new Chart(
            'genderChart',
            $title,
            $legend,
            $plotArea,
        );
    }

    protected function createOverallChart(): Chart
    {
        $sheetName = 'Charts';

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$A\$6", null, 1),
        ];

        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$B\$5:\$J\$5", null, 9),
        ];

        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetName}'!\$B\$6:\$J\$6", null, 9),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title = new Title('Overall Grade Distribution');

        return new Chart(
            'overallChart',
            $title,
            $legend,
            $plotArea,
        );
    }
}
