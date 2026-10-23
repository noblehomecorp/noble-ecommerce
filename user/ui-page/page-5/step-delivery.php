<?php
// steps/step-delivery.php
// Rendered inside checkout.php — variables from checkout-data.php are in scope
?>
<section id="step-3-panel" class="step-panel hidden">

    <!-- Method selector -->
    <div class="bg-white rounded-lg border border-gray-200">
        <header class="px-5 sm:px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-bold text-gray-900">Delivery method</h2>
            <p class="text-xs text-gray-500 mt-0.5">Pick up your order at our store or have it delivered.</p>
        </header>

        <div class="p-5 sm:p-6 space-y-3">
            <!-- Pick up -->
            <div class="method-card nh-choice" id="method-pickup" role="radio" tabindex="0" aria-checked="false"
                onclick="selectMethod('pickup')">
                <span class="nh-radio mt-0.5"></span>
                <span class="w-10 h-10 shrink-0 rounded-md bg-gray-100 flex items-center justify-center">
                    <i class="fa-solid fa-store text-gray-700 text-sm"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">Store pickup</p>
                    <p class="text-xs text-gray-500 mt-0.5 truncate"><?= htmlspecialchars($storeName) ?></p>
                </div>
                <span class="text-sm font-bold text-green-700 shrink-0">Free</span>
            </div>

            <!-- Delivery -->
            <div class="method-card nh-choice" id="method-delivery" role="radio" tabindex="0" aria-checked="false"
                onclick="selectMethod('delivery')">
                <span class="nh-radio mt-0.5"></span>
                <span class="w-10 h-10 shrink-0 rounded-md bg-gray-100 flex items-center justify-center">
                    <i class="fa-solid fa-truck text-gray-700 text-sm"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">Home delivery</p>
                    <p class="text-xs text-gray-500 mt-0.5" id="delivery-subtitle-label">Calculated based on distance</p>
                </div>
                <span class="text-sm font-bold text-gray-400 shrink-0" id="delivery-badge">—</span>
            </div>

            <p class="text-xs text-gray-500 hidden" id="delivery-unavailable-note">
                <i class="fa-solid fa-circle-info mr-1 text-gray-400"></i>
                <span id="delivery-unavailable-text">Home delivery is not available.</span>
            </p>

            <p class="text-xs text-red-600 hidden" id="err-method">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>Please select a delivery method.
            </p>
        </div>

        <!-- Delivery breakdown (shown when delivery is selected) -->
        <div id="delivery-detail" class="hidden border-t border-gray-100">
            <div class="p-5 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

                <!-- Vehicle + load -->
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Assigned vehicle</p>
                    <p class="text-sm font-semibold text-gray-900 mt-1 flex items-center gap-2">
                        <i class="fa-solid fa-truck-front text-gray-400 text-xs"></i>
                        <span id="truck-name-display"><?= htmlspecialchars($assignedTruck ? ucfirst($assignedTruck['nametruck']) . ' — ' . $assignedTruck['trucktype'] : 'No vehicle available') ?></span>
                    </p>
                    <p class="text-[11px] text-gray-500 mt-0.5">From <?= htmlspecialchars($storeName) ?></p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <div class="flex justify-between text-xs text-gray-600 mb-1.5">
                                <span>Volume <span id="vol-pct" class="font-semibold text-gray-900">0%</span></span>
                                <span class="text-gray-500"><span id="vol-used">0</span> / <span id="vol-max">0</span> m³</span>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div id="vol-bar" class="h-full bg-gray-900 rounded-full transition-all duration-500" style="width:0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs text-gray-600 mb-1.5">
                                <span>Weight <span id="wt-pct" class="font-semibold text-gray-900">0%</span></span>
                                <span class="text-gray-500"><span id="wt-used">0</span> / <span id="wt-max">0</span> kg</span>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div id="wt-bar" class="h-full bg-gray-900 rounded-full transition-all duration-500" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fare breakdown -->
                <div class="rounded-md bg-gray-50 border border-gray-200 p-4">
                    <p class="text-xs font-semibold text-gray-900 mb-3" id="fare-title">Delivery fee</p>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Distance</dt>
                            <dd class="font-medium text-gray-900" id="dist-display">—</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Est. travel time</dt>
                            <dd class="font-medium text-gray-900" id="time-display">—</dd>
                        </div>
                        <div class="border-t border-gray-200" style="margin:10px 0"></div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Base fare</dt>
                            <dd class="font-medium text-gray-900" id="base-fare-display">₱0.00</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500 text-xs pt-0.5" id="add-label">Distance fare</dt>
                            <dd class="font-medium text-gray-900" id="add-fare-display">₱0.00</dd>
                        </div>
                        <div class="border-t border-gray-200 pt-2.5 flex justify-between" style="margin-top:10px">
                            <dt class="font-semibold text-gray-900">Total delivery</dt>
                            <dd class="font-bold text-gray-900" id="total-delivery-display">₱0.00</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-between gap-3 mt-4">
        <button type="button" onclick="prevStep(3)"
            class="h-11 px-5 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50 inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </button>
        <button type="button" onclick="nextStep(3)"
            class="h-11 px-6 rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold inline-flex items-center gap-2 transition-colors">
            Continue to payment <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </div>
</section>

<script>
    window._truck = <?= json_encode($assignedTruck) ?>;
    window._cartVolCbm = <?= round($totalVolumeCbm, 6) ?>;
    window._cartWeightKg = <?= round($totalWeightKg, 4) ?>;
    window._storeLat = <?= floatval($storeLat) ?>;
    window._storeLng = <?= floatval($storeLng) ?>;
</script>