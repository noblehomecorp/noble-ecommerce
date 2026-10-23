<?php
// steps/step-contact.php
// Rendered inside checkout.php — variables from checkout-data.php are in scope
$nhInput = 'w-full h-11 px-3.5 rounded-md border border-gray-300 bg-white text-sm text-gray-900 placeholder:text-gray-400
            focus:outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors';
?>
<section id="step-1-panel" class="step-panel">
    <div class="bg-white rounded-lg border border-gray-200">
        <header class="px-5 sm:px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-bold text-gray-900">Contact details</h2>
            <p class="text-xs text-gray-500 mt-0.5">We'll use these to send your order updates.</p>
        </header>

        <div class="p-5 sm:p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="f-name">Full name</label>
                <input type="text" id="f-name" autocomplete="name"
                    value="<?= htmlspecialchars($userAccount['name'] ?? '') ?>"
                    placeholder="e.g. Juan dela Cruz" class="<?= $nhInput ?>">
                <p class="text-xs text-red-600 mt-1.5 hidden" id="err-name">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>Please enter your name.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="f-email">Email address</label>
                    <input type="email" id="f-email" autocomplete="email"
                        value="<?= htmlspecialchars($userAccount['email'] ?? '') ?>"
                        placeholder="you@example.com" class="<?= $nhInput ?>">
                    <p class="text-xs text-red-600 mt-1.5 hidden" id="err-email">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>Please enter a valid email.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="f-phone">Mobile number</label>
                    <input type="tel" id="f-phone" autocomplete="tel" inputmode="tel" maxlength="20"
                        value="<?= htmlspecialchars(!empty($savedAddresses[0]['contact_number']) ? $savedAddresses[0]['contact_number'] : '') ?>"
                        placeholder="09XX XXX XXXX" class="<?= $nhInput ?>">
                    <p class="text-xs text-red-600 mt-1.5 hidden" id="err-phone">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>Please enter a valid mobile number.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-end mt-4">
        <button type="button" onclick="nextStep(1)"
            class="h-11 px-6 rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold inline-flex items-center gap-2 transition-colors">
            Continue to address <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </div>
</section>