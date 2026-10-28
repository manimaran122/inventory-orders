import Alpine from 'alpinejs';
import orderBuilder from './order-builder.js';

/*
 * Alpine drives the only genuinely interactive screen in this application: the
 * order builder, which adds and removes line items and previews totals as the
 * user types.
 *
 * It is deliberately a small sprinkle of JavaScript rather than a full SPA.
 * Every other screen is server-rendered Blade, which keeps the stack simple and
 * means the UI needs no build step beyond `npm run build`.
 */
window.Alpine = Alpine;

// Registered as an Alpine "data" component so the Blade template can call
// x-data="orderBuilder(...)" without inlining this logic into the markup.
Alpine.data('orderBuilder', orderBuilder);

Alpine.start();
