<?php

/** Direct conformance of the published typed rendering values. @since 0.2.1 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Render\ProductionValues;
use Kumwe\Producer\Render\RenderException;
use Kumwe\Producer\Tests\TestCase;

final class ProductionValuesBoundaryTest extends TestCase
{
    public function testMoneyPreservesExactDigitsAtInclusiveBounds(): void
    {
        $amount = '-999999999999999999.999999';
        $result = ProductionValues::parseMoneyValue((object) ['amount' => $amount, 'currency' => 'USD']);
        $this->assertSame($amount, $result->amount, 'Typed money must not pass through a float.');
        $this->assertSame('USD', $result->currency, 'Currency remains an exact code.');
        foreach (['1.2345678', '1000000000000000000', '1e3', '+1', '01', "1\n", '1 ', ''] as $invalid) {
            $this->assertThrows(
                static fn () => ProductionValues::parseMoneyValue(
                    (object) ['amount' => $invalid, 'currency' => 'USD']
                ),
                RenderException::class,
                'Noncanonical money is refused by the public parser.'
            );
        }
        foreach (['usd', 'US', 'USDD', "USD\n", ' USD'] as $invalid) {
            $this->assertThrows(
                static fn () => ProductionValues::parseMoneyValue(
                    (object) ['amount' => '1', 'currency' => $invalid]
                ),
                RenderException::class,
                'Currency must match the whole input.'
            );
        }
    }

    public function testChartAndTableCountLimitsRefuseBeforeItemInspection(): void
    {
        $chart = (object) ['type' => 'bar', 'labels' => array_fill(0, 200, 'A'),
            'datasets' => [(object) ['label' => 'Series', 'values' => array_fill(0, 200, 1)]]];
        $this->assertSame(200, count(ProductionValues::parseChartSpec($chart)->labels), 'Two hundred labels fit.');
        $chart->labels[] = new \stdClass();
        $this->assertThrows(
            static fn () => ProductionValues::parseChartSpec($chart),
            RenderException::class,
            'Two hundred and one labels refuse without inspecting the excess item.'
        );
        foreach ([NAN, INF, -INF, 1.0e16, '1'] as $number) {
            $this->assertThrows(
                static fn () => ProductionValues::parseChartSpec((object) ['type' => 'bar',
                'labels' => ['A'], 'datasets' => [(object) ['label' => 'Series', 'values' => [$number]]]]),
                RenderException::class,
                'Approximate chart numbers must remain finite and bounded.'
            );
        }
        $table = (object) ['columns' => ['A'], 'rows' => array_fill(0, 1000, ['value'])];
        $this->assertSame(1000, count(ProductionValues::parseTableDocument($table)->rows), 'One thousand rows fit.');
        $table->rows[] = [];
        $this->assertThrows(
            static fn () => ProductionValues::parseTableDocument($table),
            RenderException::class,
            'One thousand and one rows refuse.'
        );
        $this->assertThrows(static fn () => ProductionValues::parseTableDocument((object) ['columns' => ['A'],
            'rows' => [['one', 'extra']]]), RenderException::class, 'Ragged rows cannot enter a table.');
    }

    public function testDrawingTokensAndCoordinatesMatchTheirWholeBoundedInput(): void
    {
        $drawing = (object) ['alt' => 'A line', 'width' => 4096, 'height' => 4096, 'strokes' => [
            (object) ['color' => 'brand/primary', 'width' => 0.25, 'points' => [(object) ['x' => 0, 'y' => 4096]]]]];
        $this->assertSame(
            4096,
            ProductionValues::parseDrawingDocument($drawing)->height,
            'Dimension bound is inclusive.',
        );
        foreach (["#abcdef\n", "brand/primary\n", 'url(javascript:alert(1))'] as $color) {
            $candidate = clone $drawing;
            $candidate->strokes = [(object) ['color' => $color, 'width' => 1,
                'points' => [(object) ['x' => 1, 'y' => 1]]]];
            $this->assertThrows(
                static fn () => ProductionValues::parseDrawingDocument($candidate),
                RenderException::class,
                'A color token must match every input byte.'
            );
        }
        foreach ([-1, 4097, NAN, INF] as $coordinate) {
            $candidate = clone $drawing;
            $candidate->strokes = [(object) ['color' => '#abcdef', 'width' => 1,
                'points' => [(object) ['x' => $coordinate, 'y' => 1]]]];
            $this->assertThrows(
                static fn () => ProductionValues::parseDrawingDocument($candidate),
                RenderException::class,
                'Coordinates stay inside the declared drawing.'
            );
        }
    }
}
