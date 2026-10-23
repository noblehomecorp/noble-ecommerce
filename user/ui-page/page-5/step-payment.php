<?php
// user/ui-page/page-5/step-payment.php
// Step 4 — Review & pay: QR Ph or PayMongo Checkout (both redirect to PayMongo's hosted page)
?>
<section id="step-4-panel" class="step-panel hidden">
    <div class="bg-white rounded-lg border border-gray-200">
        <header class="px-5 sm:px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-bold text-gray-900">Review &amp; pay</h2>
            <p class="text-xs text-gray-500 mt-0.5">Check your details, then choose how you'd like to pay.</p>
        </header>

        <!-- ── Order review ── -->
        <div class="divide-y divide-gray-100">
            <div class="px-5 sm:px-6 py-3.5 flex items-start gap-4">
                <p class="w-24 shrink-0 text-xs font-semibold text-gray-500 pt-0.5">Contact</p>
                <div class="flex-1 min-w-0 text-sm">
                    <p class="font-semibold text-gray-900 truncate" id="review-name">—</p>
                    <p class="text-xs text-gray-500 truncate" id="review-email">—</p>
                    <p class="text-xs text-gray-500" id="review-phone">—</p>
                </div>
                <button type="button" onclick="goToStep(1)" class="text-xs font-semibold text-gray-900 underline underline-offset-2 hover:text-orange-600 shrink-0">Change</button>
            </div>

            <div class="px-5 sm:px-6 py-3.5 flex items-start gap-4">
                <p class="w-24 shrink-0 text-xs font-semibold text-gray-500 pt-0.5" id="review-address-label">Deliver to</p>
                <div class="flex-1 min-w-0 text-sm">
                    <p class="font-semibold text-gray-900" id="review-address">—</p>
                </div>
                <button type="button" onclick="goToStep(2)" id="review-address-edit" class="text-xs font-semibold text-gray-900 underline underline-offset-2 hover:text-orange-600 shrink-0">Change</button>
            </div>

            <div class="px-5 sm:px-6 py-3.5 flex items-start gap-4">
                <p class="w-24 shrink-0 text-xs font-semibold text-gray-500 pt-0.5">Method</p>
                <div class="flex-1 min-w-0 text-sm">
                    <p class="font-semibold text-gray-900" id="review-method">—</p>
                    <p class="text-xs text-gray-500" id="review-delivery-fee">—</p>
                </div>
                <button type="button" onclick="goToStep(3)" class="text-xs font-semibold text-gray-900 underline underline-offset-2 hover:text-orange-600 shrink-0">Change</button>
            </div>
        </div>

        <!-- ── Payment method ── -->
        <div class="px-5 sm:px-6 pt-5 pb-6 border-t border-gray-100">
            <p class="text-sm font-bold text-gray-900 mb-3">Payment method</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- QR Ph -->
                <button type="button" id="pm-qrph" onclick="selectPaymentMethod('qrph')" role="radio" aria-checked="false"
                    class="pay-method-card nh-choice text-left">
                    <span class="nh-radio mt-0.5"></span>
                    <span class="w-10 h-10 shrink-0 rounded-md bg-white border border-gray-200 flex items-center justify-center overflow-hidden">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/3/35/QR_Ph_Logo.svg" alt="QR Ph"
                            class="w-7 h-7 object-contain"
                            onerror="this.outerHTML='<i class=&quot;fa-solid fa-qrcode text-gray-800 text-base&quot;></i>'">
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-gray-900">QR Ph</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Scan with any PH bank or e-wallet app</span>
                    </span>
                </button>

                <!-- PayMongo -->
                <button type="button" id="pm-paymongo" onclick="selectPaymentMethod('paymongo')" role="radio" aria-checked="false"
                    class="pay-method-card nh-choice text-left">
                    <span class="nh-radio mt-0.5"></span>
                    <span class="w-10 h-10 shrink-0 rounded-md bg-white border border-gray-200 flex items-center justify-center">
                        <i class="fa-solid fa-credit-card text-gray-800"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-gray-900">Cards &amp; e-wallets</span>
                        <span class="block text-xs text-gray-500 mt-0.5">GCash · Maya · GrabPay · Visa / Mastercard</span>
                    </span>
                </button>
            </div>

            <p id="err-pay-method" class="hidden text-xs text-red-600 mt-3">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>Please choose a payment method.
            </p>

            <!-- Redirect note -->
            <div id="redirect-note" class="hidden mt-4 rounded-md border border-gray-200 bg-gray-50 px-3.5 py-3 flex items-start gap-2.5">
                <i class="fa-solid fa-arrow-up-right-from-square text-gray-400 text-xs mt-0.5"></i>
                <p class="text-xs text-gray-600 leading-relaxed">
                    You'll be taken to PayMongo's secure page to <span id="redirect-note-action">complete your payment</span>.
                    You'll come back here once it's done.
                </p>
            </div>

            <!-- Amount breakdown -->
            <dl class="mt-5 rounded-md border border-gray-200 p-4 space-y-2 text-sm">
                <div class="flex justify-between text-gray-600">
                    <dt>Subtotal</dt>
                    <dd class="font-medium text-gray-900">₱<span id="pay-subtotal">0.00</span></dd>
                </div>
                <div class="flex justify-between text-gray-600">
                    <dt>VAT (12%)</dt>
                    <dd class="font-medium text-gray-900">₱<span id="pay-vat">0.00</span></dd>
                </div>
                <div class="flex justify-between text-gray-600" id="pay-delivery-row" style="display:none;">
                    <dt>Delivery fee</dt>
                    <dd class="font-medium text-gray-900">₱<span id="pay-delivery">0.00</span></dd>
                </div>
                <div class="flex justify-between items-baseline border-t border-gray-200 pt-2.5">
                    <dt class="font-semibold text-gray-900">Total to pay</dt>
                    <dd class="text-lg font-bold text-gray-900">₱<span id="pay-total">0.00</span></dd>
                </div>
            </dl>

            <!-- Error -->
            <div id="err-payment" class="hidden mt-4 rounded-md border border-red-200 bg-red-50 px-3.5 py-3 text-xs text-red-700 flex items-start gap-2">
                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                <span id="err-payment-msg">Something went wrong. Please try again.</span>
            </div>

            <!-- Actions -->
            <div class="flex flex-col-reverse sm:flex-row gap-3 mt-5">
                <button type="button" onclick="prevStep(4)"
                    class="h-12 px-5 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50 inline-flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Back
                </button>
                <button type="button" onclick="startPayment()" id="pay-btn"
                    class="flex-1 h-12 px-6 rounded-md bg-orange-500 hover:bg-orange-600 disabled:opacity-60 disabled:cursor-wait text-white text-sm font-bold inline-flex items-center justify-center gap-2 transition-colors">
                    <i class="fa-solid fa-lock text-xs"></i>
                    <span id="pay-btn-label">Pay Now</span>
                </button>
            </div>

            <p class="text-[11px] text-gray-500 text-center mt-3" id="pay-secure-note">
                <i class="fa-solid fa-shield-halved mr-1 text-gray-400"></i>
                Payments are processed securely by <span class="font-semibold text-gray-700">PayMongo</span>.
            </p>
        </div>
    </div>
</section>