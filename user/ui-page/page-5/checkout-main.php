<?php
// checkout-main.php  (user/ui-page/page-5/checkout-main.php)
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-5/checkout-data.php';

// Simple products (no real color/size) are stored with a placeholder "Default"
// color + "Default" size — hide that label in the order summary.
if (!function_exists('isDefaultVariantLabel')) {
    function isDefaultVariantLabel($colorName, $sizeName) {
        return trim((string) $colorName) === 'Default' && trim((string) $sizeName) === 'Default';
    }
}
if (!function_exists('cleanCheckoutVariantLabel')) {
    function cleanCheckoutVariantLabel($colorName, $sizeName) {
        $parts = [];
        if (trim((string) $colorName) !== '' && trim((string) $colorName) !== 'Default') $parts[] = trim($colorName);
        if (trim((string) $sizeName) !== '' && trim((string) $sizeName) !== 'Default') $parts[] = trim($sizeName);
        return implode(' · ', $parts);
    }
}

$itemCount = array_sum(array_map(fn($i) => intval($i['quantity']), $cartItems));
$wasCancelled = isset($_GET['cancelled']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        /* Selectable cards (address / delivery method / payment) */
        .nh-choice {
            display: flex; align-items: flex-start; gap: .75rem; width: 100%;
            padding: 1rem; border: 1px solid #e5e7eb; border-radius: .5rem; background: #fff;
            cursor: pointer; transition: border-color .15s, box-shadow .15s, background-color .15s;
        }
        .nh-choice:hover { border-color: #9ca3af; }
        .nh-choice:focus-visible { outline: 2px solid #111827; outline-offset: 2px; }
        .nh-choice.is-selected { border-color: #111827; box-shadow: 0 0 0 1px #111827; background: #fafafa; }
        .nh-choice.is-disabled { opacity: .5; cursor: not-allowed; pointer-events: none; }

        .nh-radio {
            width: 18px; height: 18px; flex-shrink: 0; border-radius: 9999px;
            border: 1.5px solid #d1d5db; background: #fff; transition: border .15s;
        }
        .nh-choice.is-selected .nh-radio { border: 5px solid #111827; }

        /* Stepper */
        .nh-step-dot { width: 28px; height: 28px; border-radius: 9999px; display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0; border: 1.5px solid #d1d5db; color: #9ca3af; background: #fff; }
        .nh-step.is-current .nh-step-dot { background: #111827; border-color: #111827; color: #fff; }
        .nh-step.is-done .nh-step-dot { background: #f97316; border-color: #f97316; color: #fff; }
        .nh-step-label { font-size: 12px; font-weight: 500; color: #9ca3af; white-space: nowrap; }
        .nh-step.is-current .nh-step-label { color: #111827; font-weight: 700; }
        .nh-step.is-done .nh-step-label { color: #374151; font-weight: 600; }
        .nh-step.is-done { cursor: pointer; }
        .nh-step.is-done:hover .nh-step-label { text-decoration: underline; }
        .nh-step-line { flex: 1; height: 1.5px; background: #e5e7eb; min-width: 12px; transition: background .2s; }
        .nh-step-line.is-done { background: #f97316; }

        .nh-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .nh-thin-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .nh-thin-scroll::-webkit-scrollbar { width: 6px; }
        .nh-thin-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
    </style>
</head>

<body class="bg-gray-50 min-h-screen text-gray-900">
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 pb-24 md:pb-10">

        <!-- ── Page header ── -->
        <nav class="flex items-center gap-1.5 text-xs text-gray-500 mb-2" aria-label="Breadcrumb">
            <a href="<?= BASE_URL ?>/" class="hover:text-gray-900">Home</a>
            <span class="text-gray-300">/</span>
            <a href="<?= BASE_URL ?>/cartview" class="hover:text-gray-900">Cart</a>
            <span class="text-gray-300">/</span>
            <span class="text-gray-900 font-medium">Checkout</span>
        </nav>
        <div class="flex items-end justify-between gap-4 mb-5">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Checkout</h1>
            <p class="hidden sm:flex items-center gap-1.5 text-xs text-gray-500">
                <i class="fa-solid fa-lock text-[11px]"></i> Secure checkout
            </p>
        </div>

        <?php if ($wasCancelled): ?>
            <div class="flex items-start gap-2.5 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg px-4 py-3 mb-4">
                <i class="fa-solid fa-circle-info text-gray-400 mt-0.5"></i>
                <span>Your payment was cancelled. Nothing was charged — your items are still in your cart.</span>
            </div>
        <?php endif; ?>

        <?php if ($hasStockIssue): ?>
            <div id="stock-warning-banner"
                class="flex items-start gap-2.5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                <span>
                    Some items in your cart are out of stock or exceed the available quantity.
                    <a href="<?= BASE_URL ?>/cartview" class="underline font-semibold">Update your cart</a>
                    before completing checkout.
                </span>
            </div>
        <?php endif; ?>

        <!-- Mobile: order total shortcut -->
        <a href="#order-summary"
            class="lg:hidden flex items-center justify-between bg-white border border-gray-200 rounded-lg px-4 py-3 mb-4 text-sm">
            <span class="flex items-center gap-2 text-gray-700">
                <i class="fa-solid fa-bag-shopping text-gray-400"></i>
                Order summary · <?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?>
            </span>
            <span class="font-bold text-gray-900" id="mobile-summary-total">₱<?= number_format($grandTotal, 2) ?></span>
        </a>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">

            <!-- ─────────────── LEFT — Stepper + Steps ─────────────── -->
            <div class="min-w-0">

                <!-- Stepper -->
                <div class="bg-white rounded-lg border border-gray-200 px-4 sm:px-5 py-4 mb-4">
                    <ol class="flex items-center gap-2 sm:gap-3">
                        <?php foreach ([1 => 'Contact', 2 => 'Address', 3 => 'Delivery', 4 => 'Payment'] as $n => $label): ?>
                            <li class="nh-step flex items-center gap-2 <?= $n === 1 ? 'is-current' : '' ?>" data-step="<?= $n ?>"
                                onclick="if (this.classList.contains('is-done')) goToStep(<?= $n ?>)">
                                <span id="dot-<?= $n ?>" class="nh-step-dot"><?= $n ?></span>
                                <span id="label-<?= $n ?>" class="nh-step-label hidden sm:inline"><?= $label ?></span>
                            </li>
                            <?php if ($n < 4): ?>
                                <li id="line-<?= $n ?>" class="nh-step-line" aria-hidden="true"></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <!-- Step panels -->
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-contact.php'; ?>
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-address.php'; ?>
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-delivery.php'; ?>
                <?php include ROOT_PATH . '/user/ui-page/page-5/step-payment.php'; ?>
            </div>

            <!-- ─────────────── RIGHT — Order Summary ─────────────── -->
            <aside id="order-summary" class="lg:sticky lg:top-32 scroll-mt-32">
                <div class="bg-white rounded-lg border border-gray-200">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900">Order summary</h2>
                        <a href="<?= BASE_URL ?>/cartview" class="text-xs font-semibold text-gray-900 underline underline-offset-2 hover:text-orange-600">Edit cart</a>
                    </div>

                    <!-- Items -->
                    <ul class="nh-thin-scroll max-h-72 overflow-y-auto divide-y divide-gray-100 px-5">
                        <?php foreach ($cartItems as $item):
                            $price           = floatval($item['pricesize']);
                            $variantDiscount = floatval($item['discountvariant']);
                            $promoDiscount   = resolvePromoDiscount($promosByProduct, $item['product_id'], $item['color_id'], $item['sizename']);
                            $discount        = max($variantDiscount, $promoDiscount);
                            $unitPrice       = $discount > 0 ? $price * (1 - $discount / 100) : $price;
                            $qty = intval($item['quantity']);
                            $pid = $item['product_id'];

                            // Quantity tier discount on top of variant discount
                            $totalQtyForProduct = $productQtyMap[$pid] ?? $qty;
                            $tiers              = $tiersPerProduct[$pid] ?? [];
                            $tierDiscount       = resolveQtyDiscountPercent($tiers, $totalQtyForProduct);
                            $finalPrice         = $unitPrice * (1 - $tierDiscount / 100);

                            $lineTotal = $finalPrice * $qty;
                            $origTotal = $price * $qty;
                            $image = !empty($item['imagecolor']) ? $item['imagecolor'] : $item['imageproduct'];

                            $itemStock        = intval($item['variant_stock']);
                            $itemOutOfStock   = $itemStock <= 0;
                            $itemExceedsStock = !$itemOutOfStock && $qty > $itemStock;

                            $variantLabel = cleanCheckoutVariantLabel($item['colorname'], $item['sizename']);
                            ?>
                            <li class="flex items-start gap-3 py-3.5">
                                <div class="relative shrink-0">
                                    <div class="w-14 h-14 rounded-md bg-gray-50 border border-gray-200 overflow-hidden flex items-center justify-center">
                                        <?php if ($image): ?>
                                            <img src="<?= $uploadUrl . htmlspecialchars($image) ?>"
                                                alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                class="w-full h-full object-contain p-0.5" loading="lazy">
                                        <?php else: ?>
                                            <i class="fa-solid fa-image text-gray-300"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 rounded-full bg-gray-700 text-white text-[10px] font-bold flex items-center justify-center">
                                        <?= $qty ?>
                                    </span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-900 leading-snug nh-clamp-2"><?= htmlspecialchars($item['product_name']) ?></p>
                                    <?php if ($variantLabel !== ''): ?>
                                        <p class="text-[11px] text-gray-500 mt-0.5 truncate"><?= htmlspecialchars($variantLabel) ?></p>
                                    <?php endif; ?>
                                    <?php if ($discount > 0 || $tierDiscount > 0): ?>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <?php if ($discount > 0): ?>
                                                <span class="px-1.5 py-px rounded bg-red-50 text-[10px] font-semibold text-red-600">-<?= rtrim(rtrim(number_format($discount, 2), '0'), '.') ?>%</span>
                                            <?php endif; ?>
                                            <?php if ($tierDiscount > 0): ?>
                                                <span class="px-1.5 py-px rounded bg-green-50 text-[10px] font-semibold text-green-700">-<?= rtrim(rtrim(number_format($tierDiscount, 2), '0'), '.') ?>% bulk</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($itemOutOfStock): ?>
                                        <p class="text-[11px] text-red-600 font-medium mt-1"><i class="fa-solid fa-circle-exclamation mr-0.5"></i>Out of stock</p>
                                    <?php elseif ($itemExceedsStock): ?>
                                        <p class="text-[11px] text-red-600 font-medium mt-1"><i class="fa-solid fa-triangle-exclamation mr-0.5"></i>Only <?= $itemStock ?> available</p>
                                    <?php endif; ?>
                                </div>

                                <div class="text-right shrink-0">
                                    <p class="text-xs font-bold text-gray-900">₱<?= number_format($lineTotal, 2) ?></p>
                                    <?php if ($lineTotal < $origTotal - 0.004): ?>
                                        <p class="text-[10px] text-gray-400 line-through">₱<?= number_format($origTotal, 2) ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Totals -->
                    <dl class="border-t border-gray-100 px-5 py-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Subtotal</dt>
                            <dd class="font-medium text-gray-900">₱<?= number_format($subtotal, 2) ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">VAT (12%)</dt>
                            <dd class="font-medium text-gray-900">₱<?= number_format($vatAmount, 2) ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Delivery</dt>
                            <dd class="font-medium text-gray-500" id="summary-delivery-pending">Calculated at next step</dd>
                            <dd class="font-medium text-gray-900 hidden" id="summary-delivery-fee">₱0.00</dd>
                        </div>
                        <!-- kept for compatibility (toggled by JS) -->
                        <div id="summary-delivery-row" class="hidden"></div>
                        <div class="flex justify-between items-baseline pt-3 border-t border-gray-100">
                            <dt class="font-semibold text-gray-900">Total</dt>
                            <dd class="text-xl font-bold text-gray-900" id="summary-total">₱<?= number_format($grandTotal, 2) ?></dd>
                        </div>
                        <p class="text-[11px] text-gray-500 hidden" id="pickup-note">
                            <i class="fa-solid fa-store mr-1"></i>Store pickup — no delivery fee.
                        </p>
                        <p class="text-[11px] text-gray-500 hidden" id="delivery-note">
                            <i class="fa-solid fa-truck mr-1"></i>Delivery fee based on distance from our store.
                        </p>
                    </dl>
                </div>

                <!-- Trust -->
                <ul class="mt-3 bg-white rounded-lg border border-gray-200 divide-y divide-gray-100 text-xs text-gray-600">
                    <li class="flex items-center gap-3 px-4 py-3">
                        <i class="fa-solid fa-shield-halved text-gray-400 w-4 text-center"></i>
                        Secure payment via PayMongo
                    </li>
                    <li class="flex items-center gap-3 px-4 py-3">
                        <i class="fa-solid fa-qrcode text-gray-400 w-4 text-center"></i>
                        QR Ph, GCash, Maya, GrabPay &amp; cards
                    </li>
                    <li class="flex items-center gap-3 px-4 py-3">
                        <i class="fa-solid fa-headset text-gray-400 w-4 text-center"></i>
                        Need help? Mon–Fri 7AM–5PM · Sat 8AM–12PM
                    </li>
                </ul>
            </aside>
        </div>
    </main>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <script>
        // ── Constants passed from PHP ─────────────────────────────────────────
        const SUBTOTAL = <?= round($subtotal, 2) ?>;
        const VAT_AMOUNT = <?= round($vatAmount, 2) ?>;
        const GRAND_TOTAL = <?= round($grandTotal, 2) ?>;
        const HAS_STOCK_ISSUE = <?= $hasStockIssue ? 'true' : 'false' ?>;
        window._storeName = <?= json_encode($storeName) ?>;

        // ── Step state ────────────────────────────────────────────────────────
        let currentStep = 1;
        let chosenMethod = null;        // 'pickup' | 'delivery'
        let chosenPaymentMethod = null; // 'qrph' | 'paymongo'

        // ── Selection helper ──────────────────────────────────────────────────
        function markSelected(groupSelector, el) {
            document.querySelectorAll(groupSelector).forEach(c => {
                c.classList.remove('is-selected');
                c.setAttribute('aria-checked', 'false');
            });
            if (el) {
                el.classList.add('is-selected');
                el.setAttribute('aria-checked', 'true');
            }
        }

        // Keyboard: Enter / Space selects a card
        document.querySelectorAll('.nh-choice[role="radio"]').forEach(el => {
            el.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.click(); }
            });
        });

        // ── Stepper nav ───────────────────────────────────────────────────────
        function nextStep(from) {
            if (from === 1 && !validateStep1()) return;
            if (from === 2 && !validateStep2()) return;
            if (from === 3 && !validateStep3()) return;

            if (from === 2) refreshDeliveryAvailability();
            if (from === 3) {
                goToStep(4);
                populateReview();
                return;
            }
            goToStep(from + 1);
        }

        function prevStep(from) {
            goToStep(from - 1);
        }

        function goToStep(n) {
            currentStep = n;
            document.querySelectorAll('.step-panel').forEach(p => p.classList.add('hidden'));
            document.getElementById('step-' + n + '-panel').classList.remove('hidden');
            if (n === 3) refreshDeliveryAvailability();
            if (n === 4) populateReview();
            updateStepIndicator(n);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateStepIndicator(n) {
            document.querySelectorAll('.nh-step').forEach(step => {
                const i = parseInt(step.dataset.step, 10);
                const dot = document.getElementById('dot-' + i);
                step.classList.toggle('is-done', i < n);
                step.classList.toggle('is-current', i === n);
                dot.innerHTML = i < n ? '<i class="fa-solid fa-check text-[11px]"></i>' : String(i);
                const line = document.getElementById('line-' + i);
                if (line) line.classList.toggle('is-done', i < n);
            });
        }

        // ── Validations ───────────────────────────────────────────────────────
        function validateStep1() {
            const name = document.getElementById('f-name').value.trim();
            const email = document.getElementById('f-email').value.trim();
            const phone = document.getElementById('f-phone').value.trim();
            const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            const phoneOk = phone.replace(/[^\d]/g, '').length >= 10;

            toggleErr('err-name', !name);
            toggleErr('err-email', !emailOk);
            toggleErr('err-phone', !phoneOk);
            markInvalid('f-name', !name);
            markInvalid('f-email', !emailOk);
            markInvalid('f-phone', !phoneOk);

            const firstBad = !name ? 'f-name' : !emailOk ? 'f-email' : !phoneOk ? 'f-phone' : null;
            if (firstBad) document.getElementById(firstBad).focus();
            return !firstBad;
        }

        function validateStep2() {
            // No saved address → allowed (store pickup only)
            if (!window._hasSavedAddresses) { toggleErr('err-addr', false); return true; }
            if (!window._selectedAddr) { toggleErr('err-addr', true); return false; }
            toggleErr('err-addr', false);
            return true;
        }

        function validateStep3() {
            if (!chosenMethod) { toggleErr('err-method', true); return false; }
            toggleErr('err-method', false);
            return true;
        }

        function toggleErr(id, show) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', !show);
        }
        function markInvalid(id, bad) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.toggle('border-red-400', bad);
            el.classList.toggle('border-gray-300', !bad);
        }
        ['f-name', 'f-email', 'f-phone'].forEach(id => {
            document.getElementById(id).addEventListener('input', () => {
                markInvalid(id, false);
                toggleErr('err-' + id.replace('f-', ''), false);
            });
        });

        // ── Address selection ─────────────────────────────────────────────────
        function selectAddress(card) {
            markSelected('.addr-card', card);
            window._selectedAddr = {
                id: card.dataset.id,
                lat: parseFloat(card.dataset.lat),
                lng: parseFloat(card.dataset.lng),
                label: card.dataset.label || '',
            };
            toggleErr('err-addr', false);
            if (chosenMethod === 'delivery') calcDelivery();
        }

        // Delivery needs an address + an available vehicle
        function refreshDeliveryAvailability() {
            const card = document.getElementById('method-delivery');
            const note = document.getElementById('delivery-unavailable-note');
            const text = document.getElementById('delivery-unavailable-text');
            let reason = '';
            if (!window._truck) reason = 'Home delivery is not available right now. Please choose store pickup.';
            else if (!window._selectedAddr) reason = 'Add a delivery address to use home delivery.';

            card.classList.toggle('is-disabled', !!reason);
            note.classList.toggle('hidden', !reason);
            text.textContent = reason;

            if (reason && chosenMethod === 'delivery') {
                chosenMethod = null;
                markSelected('.method-card', null);
                document.getElementById('delivery-detail').classList.add('hidden');
                resetDeliverySummary();
            }
        }

        // ── Method selection ──────────────────────────────────────────────────
        function selectMethod(m) {
            const chosen = document.getElementById('method-' + m);
            if (chosen.classList.contains('is-disabled')) return;

            chosenMethod = m;
            toggleErr('err-method', false);
            markSelected('.method-card', chosen);

            const detailEl = document.getElementById('delivery-detail');
            if (m === 'delivery') {
                detailEl.classList.remove('hidden');
                calcDelivery();
                showEl('pickup-note', false);
                showEl('delivery-note', true);
            } else {
                detailEl.classList.add('hidden');
                resetDeliverySummary(true);
                showEl('pickup-note', true);
                showEl('delivery-note', false);
            }
        }

        function resetDeliverySummary(isPickup = false) {
            window._deliveryFee = 0;
            window._distKm = 0;
            const badge = document.getElementById('delivery-badge');
            badge.textContent = '—';
            badge.className = 'text-sm font-bold text-gray-400 shrink-0';
            document.getElementById('delivery-subtitle-label').textContent = 'Calculated based on distance';

            showEl('summary-delivery-pending', !isPickup);
            const fee = document.getElementById('summary-delivery-fee');
            fee.classList.toggle('hidden', !isPickup);
            fee.textContent = isPickup ? 'Free' : '₱0.00';
            setTotal(GRAND_TOTAL);
            if (!isPickup) { showEl('pickup-note', false); showEl('delivery-note', false); }
        }

        // ── Delivery fare calc (display only — the server recalculates it) ────
        function calcDelivery() {
            const truck = window._truck;
            if (!truck) return;

            const addr = window._selectedAddr;
            const lat2 = addr ? addr.lat : window._storeLat;
            const lng2 = addr ? addr.lng : window._storeLng;

            const distKm = parseFloat(haversine(window._storeLat, window._storeLng, lat2, lng2).toFixed(2));
            const estMin = Math.max(1, Math.round(distKm / 0.5));

            const baseFare = parseFloat(truck.basefare);
            const addFare = parseFloat((distKm * parseFloat(truck.perkmrate)).toFixed(2));
            const totalFare = parseFloat((baseFare + addFare).toFixed(2));

            const volMax = parseFloat(truck.maxcubicmeter) || 0;
            const wtMax = parseFloat(truck.maxweightcapacity) || 0;
            const volPct = volMax > 0 ? parseFloat(((window._cartVolCbm / volMax) * 100).toFixed(1)) : 0;
            const wtPct = wtMax > 0 ? parseFloat(((window._cartWeightKg / wtMax) * 100).toFixed(1)) : 0;

            const tName = ucFirst(truck.nametruck) + ' — ' + truck.trucktype;
            set('truck-name-display', tName);
            set('fare-title', 'Delivery fee · ' + tName);

            set('vol-pct', volPct + '%');
            set('vol-used', window._cartVolCbm.toFixed(3));
            set('vol-max', volMax.toFixed(3));
            setBar('vol-bar', volPct);

            set('wt-pct', wtPct + '%');
            set('wt-used', window._cartWeightKg.toFixed(2));
            set('wt-max', wtMax.toFixed(2));
            setBar('wt-bar', wtPct);

            set('dist-display', distKm + ' km');
            set('time-display', '~' + estMin + ' min');
            set('base-fare-display', '₱' + fmtP(baseFare));
            set('add-label', 'Distance fare (' + distKm + ' km × ₱' + parseFloat(truck.perkmrate).toFixed(2) + ')');
            set('add-fare-display', '₱' + fmtP(addFare));
            set('total-delivery-display', '₱' + fmtP(totalFare));

            const badge = document.getElementById('delivery-badge');
            badge.textContent = '₱' + fmtP(totalFare);
            badge.className = 'text-sm font-bold text-gray-900 shrink-0';
            document.getElementById('delivery-subtitle-label').textContent = distKm + ' km from our store';

            showEl('summary-delivery-pending', false);
            const fee = document.getElementById('summary-delivery-fee');
            fee.classList.remove('hidden');
            fee.textContent = '₱' + fmtP(totalFare);
            setTotal(GRAND_TOTAL + totalFare);

            window._deliveryFee = totalFare;
            window._distKm = distKm;
        }

        function setBar(id, pct) {
            const bar = document.getElementById(id);
            bar.style.width = Math.min(pct, 100) + '%';
            bar.classList.remove('bg-gray-900', 'bg-orange-500', 'bg-red-500');
            bar.classList.add(pct > 100 ? 'bg-red-500' : pct >= 85 ? 'bg-orange-500' : 'bg-gray-900');
        }

        function setTotal(amount) {
            set('summary-total', '₱' + fmtP(amount));
            set('mobile-summary-total', '₱' + fmtP(amount));
        }

        // ── Populate step 4 review panel ──────────────────────────────────────
        function populateReview() {
            set('review-name', document.getElementById('f-name').value.trim() || '—');
            set('review-email', document.getElementById('f-email').value.trim() || '—');
            set('review-phone', document.getElementById('f-phone').value.trim() || '—');

            const fee = chosenMethod === 'delivery' ? (window._deliveryFee ?? 0) : 0;
            if (chosenMethod === 'pickup') {
                set('review-address-label', 'Pickup at');
                set('review-address', window._storeName ?? 'Store');
                showEl('review-address-edit', false);
                set('review-method', 'Store pickup');
                set('review-delivery-fee', 'Free');
            } else {
                set('review-address-label', 'Deliver to');
                set('review-address', window._selectedAddr?.label || '—');
                showEl('review-address-edit', true);
                set('review-method', 'Home delivery' + (window._truck?.nametruck ? ' · ' + ucFirst(window._truck.nametruck) : ''));
                set('review-delivery-fee', '₱' + fmtP(fee) + ' · ' + (window._distKm ?? 0) + ' km');
            }

            set('pay-subtotal', fmtP(SUBTOTAL));
            set('pay-vat', fmtP(VAT_AMOUNT));
            set('pay-total', fmtP(GRAND_TOTAL + fee));

            const delivRow = document.getElementById('pay-delivery-row');
            if (fee > 0) {
                set('pay-delivery', fmtP(fee));
                delivRow.style.display = '';
            } else {
                delivRow.style.display = 'none';
            }
        }

        // ── Select payment method (QR Ph or Cards/Wallets) ────────────────────
        function selectPaymentMethod(method) {
            chosenPaymentMethod = method;
            toggleErr('err-pay-method', false);
            markSelected('.pay-method-card', document.getElementById(method === 'qrph' ? 'pm-qrph' : 'pm-paymongo'));

            showEl('redirect-note', true);
            set('redirect-note-action', method === 'qrph' ? 'scan and pay with QR Ph' : 'complete your payment');

            document.getElementById('pay-secure-note').innerHTML = '<i class="fa-solid fa-shield-halved mr-1 text-gray-400"></i>'
                + (method === 'qrph'
                    ? 'QR Ph via <span class="font-semibold text-gray-700">InstaPay</span> — BSP-regulated, instant settlement.'
                    : 'Secured by <span class="font-semibold text-gray-700">PayMongo</span> — GCash, Maya, GrabPay and cards.');
        }

        // ── Start payment — POST to the right handler then redirect to PayMongo ─
        async function startPayment() {
            const errEl = document.getElementById('err-payment');
            const errMsg = document.getElementById('err-payment-msg');
            const showError = msg => { errMsg.textContent = msg; errEl.classList.remove('hidden'); };

            if (HAS_STOCK_ISSUE) {
                showError('Some items in your cart are out of stock or exceed available stock. Please go back to your cart and adjust quantities.');
                return;
            }
            if (!chosenPaymentMethod) {
                toggleErr('err-pay-method', true);
                return;
            }

            const btn = document.getElementById('pay-btn');
            const label = document.getElementById('pay-btn-label');
            errEl.classList.add('hidden');
            btn.disabled = true;
            label.textContent = 'Redirecting to PayMongo…';

            const endpoint = chosenPaymentMethod === 'qrph' ? '/createqrph' : '/createcheckoutsession';
            const payload = {
                contact_name: document.getElementById('f-name').value.trim(),
                contact_email: document.getElementById('f-email').value.trim(),
                contact_phone: document.getElementById('f-phone').value.trim(),
                address_id: chosenMethod === 'delivery' ? (window._selectedAddr?.id ?? 0) : 0,
                method: chosenMethod,
                truck_id: chosenMethod === 'delivery' ? (window._truck?.id ?? 0) : 0,
                delivery_fee: chosenMethod === 'delivery' ? (window._deliveryFee ?? 0) : 0,
                distance_km: chosenMethod === 'delivery' ? (window._distKm ?? 0) : 0,
            };

            try {
                const res = await fetch(BASE_URL + endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.ok && data.checkout_url) {
                    window.location.href = data.checkout_url;
                    return;
                }
                showError(data.error ?? 'Something went wrong. Please try again.');
            } catch (e) {
                showError('Network error. Please check your connection and try again.');
            }
            btn.disabled = false;
            label.textContent = 'Pay Now';
        }

        // ── Helpers ───────────────────────────────────────────────────────────
        function haversine(lat1, lng1, lat2, lng2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2
                + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }
        function fmtP(n) {
            return parseFloat(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function set(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }
        function showEl(id, show) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', !show);
        }
        function ucFirst(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
        }
        function goBack() {
            window.location.href = BASE_URL + '/cartview';
        }

        // Initial state
        refreshDeliveryAvailability();
    </script>
</body>

</html>