<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Simulates sending an order confirmation email.
 *
 * No SMTP server is involved: the job writes a safe confirmation line to the
 * application log. Swapping in a real Mailable later means changing only the
 * handle() body.
 *
 * Two deliberate design points
 * ----------------------------
 * 1. The job receives an order ID, not an Order object. Serialising the whole
 *    model into the queue payload would freeze a snapshot of the row, and
 *    would also write customer data into the jobs table. An ID keeps the
 *    payload minimal and forces a fresh read at execution time.
 *
 * 2. afterCommit() means the job is only released to the worker once the
 *    surrounding database transaction commits. Without it, a worker could pick
 *    the job up while the order row was still uncommitted (or had been rolled
 *    back) and find nothing there. The property itself is declared by the
 *    Queueable trait, so it is set through the trait method rather than
 *    redeclared here.
 */
class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    /**
     * Retry a transient failure a couple of times, then stop.
     */
    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with(['customer', 'items'])
            ->find($this->orderId);

        if ($order === null) {
            // Defensive: if the order is not there the transaction was rolled
            // back, so there is nothing to confirm. Warn rather than throw, so
            // the job is not retried pointlessly.
            Log::warning('Order confirmation skipped: order not found.', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        // Deliberately minimal, non-sensitive payload. No full address, no
        // payment details, no tokens - anything written here ends up in log
        // files that are often shipped to third-party aggregators.
        Log::info('Order confirmation email queued for delivery.', [
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'customer_name' => $order->customer->name,
            'customer_email' => $order->customer->email,
            'item_count' => $order->items->count(),
            'grand_total' => $order->grand_total,
        ]);
    }
}
