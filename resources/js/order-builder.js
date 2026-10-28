/**
 * Alpine component backing the order builder form.
 *
 * IMPORTANT: every figure produced here is a PREVIEW for the user only. None of
 * it is submitted. The form posts nothing but product_id and quantity per line,
 * and the server recalculates prices, tax and totals from the product
 * catalogue. That is why the arithmetic below being approximate would be a
 * cosmetic bug, never a financial one.
 *
 * Even so, the maths mirrors the server rules exactly - integer cents with
 * half-up rounding per line - so the preview and the confirmation screen agree.
 */
export default function orderBuilder({ products, oldRows, currencySymbol }) {
    return {
        products,
        submitting: false,
        errorTextClass: 'text-red-600',

        // Each row carries a stable `key` so Alpine can track it across
        // insertions and removals without re-rendering every row.
        rows: oldRows.length > 0
            ? oldRows.map((row, index) => ({
                key: index,
                productId: row.product_id ?? '',
                quantity: row.quantity ?? 1,
            }))
            : [{ key: 0, productId: '', quantity: 1 }],

        nextKey: Math.max(oldRows.length, 1),

        /* ---------------------------------------------------------------
         | Row management
         * --------------------------------------------------------------- */

        addRow() {
            this.rows.push({ key: this.nextKey++, productId: '', quantity: 1 });
        },

        removeRow(index) {
            // Always leave one row so the form never renders empty.
            if (this.rows.length === 1) {
                this.rows[0].productId = '';
                this.rows[0].quantity = 1;
                return;
            }

            this.rows.splice(index, 1);
        },

        /* ---------------------------------------------------------------
         | Lookups
         * --------------------------------------------------------------- */

        selected(row) {
            if (!row.productId) {
                return null;
            }

            return this.products.find((p) => String(p.id) === String(row.productId)) ?? null;
        },

        productLabel(product) {
            return `${product.name} (${product.code}) - ${this.money(product.price)}`;
        },

        /**
         * Prevent the same product being picked on two lines, matching the
         * server rule that rejects duplicates.
         */
        isChosenElsewhere(productId, currentIndex) {
            return this.rows.some(
                (row, index) => index !== currentIndex && String(row.productId) === String(productId),
            );
        },

        maxFor(row) {
            const product = this.selected(row);

            return product ? product.stock_quantity : 1;
        },

        exceedsStock(row) {
            const product = this.selected(row);

            if (!product) {
                return false;
            }

            return Number(row.quantity) > Number(product.stock_quantity);
        },

        stockLabel(row) {
            const product = this.selected(row);

            return product ? `${product.stock_quantity} in stock` : '';
        },

        shortageLabel(row) {
            const product = this.selected(row);

            return product ? `Only ${product.stock_quantity} available` : '';
        },

        removeLabel(index) {
            return `Remove line ${index + 1}`;
        },

        taxLabel(row) {
            const product = this.selected(row);

            return product ? `Tax (${this.trimRate(product.tax_rate)}%)` : 'Tax';
        },

        submitLabel() {
            return this.submitting ? 'Creating order...' : 'Create order';
        },

        /* ---------------------------------------------------------------
         | Money, in integer cents to match the server
         * --------------------------------------------------------------- */

        /** Parse a decimal string such as "19.99" into 1999 cents. */
        toCents(decimal) {
            return Math.round(Number(decimal) * 100);
        },

        lineSubtotalCents(row) {
            const product = this.selected(row);
            const quantity = Number(row.quantity);

            if (!product || !Number.isInteger(quantity) || quantity < 1) {
                return 0;
            }

            return this.toCents(product.price) * quantity;
        },

        lineTaxCents(row) {
            const product = this.selected(row);

            if (!product) {
                return 0;
            }

            const subtotal = this.lineSubtotalCents(row);
            const rate = this.toCents(product.tax_rate); // 18.00% -> 1800

            // Half-up rounding, matching Money::percentage() on the server.
            return Math.floor((subtotal * rate) / 10000 + 0.5);
        },

        lineSubtotal(row) {
            return this.lineSubtotalCents(row) / 100;
        },

        lineTax(row) {
            return this.lineTaxCents(row) / 100;
        },

        subtotal() {
            return this.rows.reduce((sum, row) => sum + this.lineSubtotalCents(row), 0) / 100;
        },

        taxTotal() {
            return this.rows.reduce((sum, row) => sum + this.lineTaxCents(row), 0) / 100;
        },

        grandTotal() {
            return this.subtotal() + this.taxTotal();
        },

        itemCount() {
            return this.rows.reduce((count, row) => {
                const quantity = Number(row.quantity);

                return this.selected(row) && quantity > 0 ? count + quantity : count;
            }, 0);
        },

        /* ---------------------------------------------------------------
         | Formatting
         * --------------------------------------------------------------- */

        money(amount) {
            return currencySymbol + Number(amount).toFixed(2);
        },

        /** "18.00" -> "18", "18.50" -> "18.5" */
        trimRate(rate) {
            return String(Number(rate));
        },

        /* ---------------------------------------------------------------
         | Submission guard
         * --------------------------------------------------------------- */

        canSubmit() {
            return this.rows.some((row) => {
                const quantity = Number(row.quantity);

                return (
                    this.selected(row) !== null
                    && Number.isInteger(quantity)
                    && quantity >= 1
                    && !this.exceedsStock(row)
                );
            });
        },
    };
}
