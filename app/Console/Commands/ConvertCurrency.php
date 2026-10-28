<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-denominates every stored money value by a fixed exchange rate.
 *
 * Why this is a command and not a one-off script:
 *
 * Multiplying every column by the rate would be wrong. An order row stores
 * subtotal, tax_total and grand_total, and each of its items stores
 * line_subtotal, line_tax and line_total. Those figures are related by the
 * rounding rules in OrderCalculator - tax is rounded half-up once per line and
 * the rounded lines are then summed. Scaling each column independently would
 * round each one separately and the invoice would stop reconciling: the grand
 * total would disagree with the sum of the printed line totals by a few paise.
 *
 * So this command converts only the two INPUT values - product price and the
 * historic unit_price snapshot on each line - and then recomputes every derived
 * figure through exactly the same rules the application uses when an order is
 * placed. Tax rates are percentages and are deliberately left untouched.
 */
final class ConvertCurrency extends Command
{
    protected $signature = 'inventory:convert-currency
        {rate : Units of the new currency per unit of the old (e.g. 96.00)}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Convert all stored money values by an exchange rate, recomputing derived totals';

    public function handle(): int
    {
        $rate = (string) $this->argument('rate');

        if (! is_numeric($rate) || (float) $rate <= 0) {
            $this->error('The rate must be a positive number.');

            return self::FAILURE;
        }

        // Money parses decimals at 2dp. A rate such as "96.515" would be
        // silently rounded to 96.52 and every converted price would be subtly
        // wrong, so reject it rather than losing the precision quietly.
        if (preg_match('/\.(\d{3,})$/', $rate) === 1) {
            $this->error('The rate may have at most 2 decimal places; '.$rate.' would be rounded and silently distort every price.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Converting all money values at a rate of %s%s',
            $rate,
            $dryRun ? '  [DRY RUN - nothing will be written]' : '',
        ));

        // One transaction so a failure halfway through cannot leave the
        // catalogue converted and the order history not.
        try {
            DB::transaction(function () use ($rate, $dryRun): void {
                $products = $this->convertProducts($rate);
                $orders = $this->convertOrders($rate);

                $this->newLine();
                $this->line("  Products converted: {$products}");
                $this->line("  Orders recomputed:  {$orders}");

                if ($dryRun) {
                    // Abort the transaction so the database rolls back the
                    // writes we just exercised. Caught immediately below - it
                    // signals a deliberate rollback, not a failure.
                    throw new DryRunRollback;
                }
            });
        } catch (DryRunRollback) {
            // Expected during a dry run.
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete - no changes written.' : 'Conversion complete.');

        return self::SUCCESS;
    }

    /**
     * Convert the catalogue price. Tax rate and stock are left alone.
     */
    private function convertProducts(string $rate): int
    {
        $count = 0;

        Product::query()->orderBy('id')->chunkById(200, function ($products) use ($rate, &$count): void {
            foreach ($products as $product) {
                $converted = Money::fromDecimal($product->price)
                    ->convertedBy($rate)
                    ->toDecimalString();

                if ($this->output->isVerbose()) {
                    $this->line("    product #{$product->id}  {$product->price} -> {$converted}");
                }

                // Always written - a dry run relies on the transaction being
                // rolled back, so that it genuinely exercises the write path
                // and surfaces problems such as a decimal column overflow.
                $product->forceFill(['price' => $converted])->save();

                $count++;
            }
        });

        return $count;
    }

    /**
     * Convert each historic unit_price snapshot, then rebuild every derived
     * figure with the same half-up-per-line rules the calculator uses.
     */
    private function convertOrders(string $rate): int
    {
        $count = 0;

        Order::query()->with('items')->orderBy('id')->chunkById(100, function ($orders) use ($rate, &$count): void {
            foreach ($orders as $order) {
                $subtotal = Money::zero();
                $taxTotal = Money::zero();

                foreach ($order->items as $item) {
                    $unitPrice = Money::fromDecimal($item->unit_price)->convertedBy($rate);

                    // Recompute exactly as OrderCalculator does.
                    $lineSubtotal = $unitPrice->multipliedBy((int) $item->quantity);
                    $lineTax = $lineSubtotal->percentage((string) $item->tax_rate);
                    $lineTotal = $lineSubtotal->plus($lineTax);

                    $subtotal = $subtotal->plus($lineSubtotal);
                    $taxTotal = $taxTotal->plus($lineTax);

                    $item->forceFill([
                        'unit_price' => $unitPrice->toDecimalString(),
                        'line_subtotal' => $lineSubtotal->toDecimalString(),
                        'line_tax' => $lineTax->toDecimalString(),
                        'line_total' => $lineTotal->toDecimalString(),
                    ])->save();
                }

                $grandTotal = $subtotal->plus($taxTotal);

                if ($this->output->isVerbose()) {
                    $this->line("    order #{$order->id}  {$order->grand_total} -> {$grandTotal->toDecimalString()}");
                }

                $order->forceFill([
                    'subtotal' => $subtotal->toDecimalString(),
                    'tax_total' => $taxTotal->toDecimalString(),
                    'grand_total' => $grandTotal->toDecimalString(),
                ])->save();

                $count++;
            }
        });

        return $count;
    }
}
