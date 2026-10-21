<?php
// bottom.php — E-commerce site footer (desktop/tablet). Mobile uses the bottom nav in top.php.

// ⚠️ I-edit ito ayon sa mga payment method na TALAGANG tinatanggap ng NobleHome.
// [label, text color, border color]
$nhPaymentMethods = [
    ['QR Ph', 'text-blue-700', 'border-blue-100', 'fa-qrcode'],
    ['PayMongo', 'text-emerald-600', 'border-emerald-100', 'fa-shield-halved'],
];
?>
<footer class="hidden md:block bg-white border-t border-gray-200 mt-auto">

    <!-- Help bar -->
    <div class="bg-gray-50 border-b border-gray-200">
        <div class="max-w-screen-xl mx-auto px-4 py-4 flex items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-headset text-white"></i>
                </span>
                <div>
                    <p class="text-sm font-bold text-gray-900">Need help with your order?</p>
                    <p class="text-xs text-gray-500">Mon–Fri 7:00 AM – 5:00 PM · Sat 8:00 AM – 12:00 PM</p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="<?= BASE_URL ?>/contact"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 text-sm font-semibold text-white hover:bg-orange-500 transition-colors">
                    <i class="fa-solid fa-message text-xs"></i> Contact Us
                </a>
            </div>
        </div>
    </div>

    <!-- Main footer -->
    <div class="max-w-screen-xl mx-auto px-4 py-10">
        <div class="grid grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1.4fr] gap-x-8 gap-y-10">

            <!-- Brand + contact -->
            <div class="col-span-2 lg:col-span-1">
                <a href="<?= BASE_URL ?>" class="inline-flex items-center gap-2">
                    <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome" class="w-9 h-9 object-contain">
                    <span class="text-lg font-extrabold tracking-tight leading-none">
                        <span class="text-orange-500">Noble</span><span class="text-gray-900">HomeDepot</span>
                    </span>
                </a>
                <p class="text-sm text-gray-500 leading-relaxed mt-3 max-w-xs">
                    Quality home products crafted for every lifestyle.
                </p>

                <ul class="mt-5 space-y-2.5 text-sm text-gray-600">
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-location-dot text-gray-400 w-4 text-center mt-1"></i>
                        <span class="leading-relaxed">2nd Floor MC Premier Building, 1181 EDSA Balintawak, Quezon City</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-phone text-gray-400 w-4 text-center"></i>
                        <span>
                            <a href="tel:+639922394563" class="hover:text-orange-600 transition-colors">0992 239 4563</a>
                            <span class="text-gray-300 mx-1">/</span>
                            <a href="tel:+63288221295" class="hover:text-orange-600 transition-colors">(02) 8822-1295</a>
                        </span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-envelope text-gray-400 w-4 text-center"></i>
                        <a href="mailto:noblehomeconst.ph@gmail.com" class="hover:text-orange-600 transition-colors">
                            noblehomeconst.ph@gmail.com
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Customer Care -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-900 mb-4">Customer Care</h4>
                <ul class="space-y-2.5 text-sm text-gray-500">
                    <li><a href="<?= BASE_URL ?>/faq" class="hover:text-orange-600 transition-colors">Help Center / FAQ</a></li>
                    <li><a href="<?= BASE_URL ?>/track-order" class="hover:text-orange-600 transition-colors">Track My Order</a></li>
                    <li><a href="<?= BASE_URL ?>/orders" class="hover:text-orange-600 transition-colors">My Orders</a></li>
                    <li><a href="<?= BASE_URL ?>/returns" class="hover:text-orange-600 transition-colors">Returns &amp; Exchanges</a></li>
                    <li><a href="<?= BASE_URL ?>/contact" class="hover:text-orange-600 transition-colors">Contact Us</a></li>
                </ul>
            </div>

            <!-- Shop -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-900 mb-4">Shop</h4>
                <ul class="space-y-2.5 text-sm text-gray-500">
                    <li><a href="<?= BASE_URL ?>/shop" class="hover:text-orange-600 transition-colors">All Products</a></li>
                    <li><a href="<?= BASE_URL ?>/products?category=new" class="hover:text-orange-600 transition-colors">New Arrivals</a></li>
                    <li><a href="<?= BASE_URL ?>/products?category=sale" class="hover:text-orange-600 transition-colors">On Sale</a></li>
                    <li><a href="<?= BASE_URL ?>/products?category=bestseller" class="hover:text-orange-600 transition-colors">Best Sellers</a></li>
                    <li><a href="<?= BASE_URL ?>/inspiration" class="hover:text-orange-600 transition-colors">Inspiration</a></li>
                    <li><a href="<?= BASE_URL ?>/find-professional" class="hover:text-orange-600 transition-colors">Find a Professional</a></li>
                </ul>
            </div>

            <!-- Payment + Follow -->
            <div class="col-span-2 lg:col-span-1">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-900 mb-4">Payment Methods</h4>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($nhPaymentMethods as [$label, $color, $border, $icon]): ?>
                        <span class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-white border <?= $border ?> shadow-sm
                                     text-xs font-extrabold tracking-tight <?= $color ?>">
                            <i class="fa-solid <?= $icon ?> text-sm"></i>
                            <?= htmlspecialchars($label) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="flex items-center gap-1.5 text-[11px] text-gray-400 mt-2.5">
                    <i class="fa-solid fa-lock text-[10px]"></i> Secure checkout powered by PayMongo
                </p>

                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-900 mt-7 mb-3">Follow Us</h4>
                <div class="flex gap-2.5">
                    <a href="https://www.facebook.com/noblehomedepotph" target="_blank" rel="noopener" aria-label="Facebook"
                        class="w-9 h-9 rounded-full bg-[#1877F2] text-white flex items-center justify-center hover:opacity-85 transition-opacity">
                        <i class="fa-brands fa-facebook-f text-sm"></i>
                    </a>
                    <a href="https://www.instagram.com/noblehome_depot" target="_blank" rel="noopener" aria-label="Instagram"
                        class="w-9 h-9 rounded-full text-white flex items-center justify-center hover:opacity-85 transition-opacity"
                        style="background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285AEB 90%);">
                        <i class="fa-brands fa-instagram text-sm"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Bottom bar -->
    <div class="border-t border-gray-200">
        <div class="max-w-screen-xl mx-auto px-4 py-4 flex items-center justify-between gap-4 text-xs text-gray-500">
            <p>&copy; <?= date('Y') ?> NobleHome Construction Corporation. All rights reserved.</p>
            <div class="flex items-center gap-5">
                <a href="<?= BASE_URL ?>/privacy" class="hover:text-orange-600 transition-colors">Privacy Policy</a>
                <a href="<?= BASE_URL ?>/terms" class="hover:text-orange-600 transition-colors">Terms of Service</a>
            </div>
        </div>
    </div>

</footer>