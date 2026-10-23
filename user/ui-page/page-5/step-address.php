<?php
// steps/step-address.php
// Rendered inside checkout.php — variables from checkout-data.php are in scope
?>
<section id="step-2-panel" class="step-panel hidden">
    <div class="bg-white rounded-lg border border-gray-200">
        <header class="px-5 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-gray-900">Delivery address</h2>
                <p class="text-xs text-gray-500 mt-0.5">Choose where we'll deliver your order.</p>
            </div>
            <a href="<?= BASE_URL ?>/profilemap"
                class="shrink-0 h-9 px-3 rounded-md border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1.5">
                <i class="fa-solid fa-plus text-[10px]"></i> Add address
            </a>
        </header>

        <div class="p-5 sm:p-6">
            <div class="space-y-3" id="addr-list">
                <?php if (empty($savedAddresses)): ?>
                    <div class="py-8 text-center border border-dashed border-gray-300 rounded-lg">
                        <span class="mx-auto w-11 h-11 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fa-solid fa-location-dot text-gray-400"></i>
                        </span>
                        <p class="text-sm font-semibold text-gray-900 mt-3">No saved address yet</p>
                        <p class="text-xs text-gray-500 mt-1 max-w-xs mx-auto">
                            Add an address for home delivery, or continue and choose store pickup.
                        </p>
                        <a href="<?= BASE_URL ?>/profilemap"
                            class="inline-flex mt-4 h-9 px-4 items-center rounded-md bg-gray-900 hover:bg-black text-white text-xs font-semibold">
                            Add an address
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($savedAddresses as $i => $addr):
                        $line2 = trim(implode(', ', array_filter([$addr['barangay'] ?? '', $addr['city'] ?? ''])));
                        if (!empty($addr['postalcode'])) $line2 .= ' ' . $addr['postalcode'];
                        $label = trim(implode(', ', array_filter([$addr['address'] ?? '', $addr['barangay'] ?? '', $addr['city'] ?? ''])));
                        ?>
                        <div class="addr-card nh-choice <?= $i === 0 ? 'is-selected' : '' ?>"
                            id="addr-card-<?= (int) $addr['id'] ?>"
                            data-id="<?= (int) $addr['id'] ?>"
                            data-lat="<?= floatval($addr['latitude']) ?>"
                            data-lng="<?= floatval($addr['longitude']) ?>"
                            data-label="<?= htmlspecialchars($label) ?>"
                            role="radio" tabindex="0" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"
                            onclick="selectAddress(this)">

                            <span class="nh-radio mt-0.5"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($addr['address']) ?></p>
                                    <?php if ($i === 0): ?>
                                        <span class="shrink-0 px-1.5 py-0.5 rounded bg-gray-100 text-[10px] font-semibold text-gray-600">Recent</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($line2) ?></p>
                                <?php if (!empty($addr['contact_number'])): ?>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        <i class="fa-solid fa-phone text-[10px] mr-1 text-gray-400"></i><?= htmlspecialchars($addr['contact_number']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <p class="text-xs text-red-600 mt-3 hidden" id="err-addr">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>Please select an address.
            </p>
        </div>
    </div>

    <div class="flex justify-between gap-3 mt-4">
        <button type="button" onclick="prevStep(2)"
            class="h-11 px-5 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50 inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </button>
        <button type="button" onclick="nextStep(2)"
            class="h-11 px-6 rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold inline-flex items-center gap-2 transition-colors">
            Continue to delivery <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </div>
</section>

<?php
// Pre-select first address in JS
$firstAddr = !empty($savedAddresses) ? $savedAddresses[0] : null;
?>
<script>
    window._hasSavedAddresses = <?= empty($savedAddresses) ? 'false' : 'true' ?>;
    <?php if ($firstAddr): ?>
    window._selectedAddr = {
        id: <?= intval($firstAddr['id']) ?>,
        lat: <?= floatval($firstAddr['latitude']) ?>,
        lng: <?= floatval($firstAddr['longitude']) ?>,
        label: <?= json_encode(trim(implode(', ', array_filter([$firstAddr['address'] ?? '', $firstAddr['barangay'] ?? '', $firstAddr['city'] ?? ''])))) ?>
    };
    <?php else: ?>
    window._selectedAddr = null;
    <?php endif; ?>
</script>