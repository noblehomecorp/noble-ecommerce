<?php
// hero-section.php  (user/ui-page/page-1/hero-section.php)


$promotions = $promotions ?? [];
$promoCount = count($promotions);
?>

<div class="mb-4 md:mb-6">

    <!-- ================= HERO ROW ================= -->
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-3 md:gap-4">

        <!-- Promo slider -->
        <div id="promoSlider"
            class="group relative w-full aspect-[2/1] md:aspect-[2.7/1] rounded-xl overflow-hidden bg-gray-900">

            <?php if ($promoCount > 0): ?>
                <div id="promoTrack" class="flex h-full"
                    style="transition: transform 550ms cubic-bezier(.4,0,.2,1);">
                    <?php foreach ($promotions as $i => $promo): ?>
                        <a href="<?= BASE_URL ?>/productcategory?id=<?= (int) $promo['category_id'] ?>"
                            class="relative shrink-0 w-full h-full block" data-slide-link="<?= $i ?>"
                            aria-label="<?= htmlspecialchars($promo['title']) ?>">
                            <?php if (!empty($promo['banner_image'])): ?>
                                <img src="<?= BASE_URL ?>/uploads/promotions/<?= htmlspecialchars($promo['banner_image']) ?>"
                                    alt="<?= htmlspecialchars($promo['title']) ?>"
                                    class="w-full h-full object-cover" <?= $i === 0 ? '' : 'loading="lazy"' ?>>
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gray-800 text-white text-xl font-bold">
                                    <?= htmlspecialchars($promo['title']) ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($promoCount > 1): ?>
                    <!-- Arrows (lumalabas pag hover sa desktop) -->
                    <button type="button" onclick="sliderMove(-1)" aria-label="Previous"
                        class="hidden md:flex absolute left-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full
                               bg-white/90 text-gray-800 shadow items-center justify-center
                               opacity-0 group-hover:opacity-100 hover:bg-white transition-opacity duration-200">
                        <i class="fa-solid fa-chevron-left text-sm"></i>
                    </button>
                    <button type="button" onclick="sliderMove(1)" aria-label="Next"
                        class="hidden md:flex absolute right-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full
                               bg-white/90 text-gray-800 shadow items-center justify-center
                               opacity-0 group-hover:opacity-100 hover:bg-white transition-opacity duration-200">
                        <i class="fa-solid fa-chevron-right text-sm"></i>
                    </button>

                    <!-- Pill dots -->
                    <div class="absolute bottom-2.5 md:bottom-4 left-1/2 -translate-x-1/2 z-10 flex items-center gap-1.5
                                px-2 py-1.5 rounded-full bg-black/25 backdrop-blur-sm">
                        <?php foreach ($promotions as $i => $_): ?>
                            <button type="button" onclick="sliderGo(<?= $i ?>)" data-dot="<?= $i ?>"
                                aria-label="Slide <?= $i + 1 ?>"
                                class="h-1.5 rounded-full transition-all duration-300 <?= $i === 0 ? 'w-6 bg-white' : 'w-1.5 bg-white/60' ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Fallback hero kapag walang active promotion -->
                <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900"></div>
                <div class="absolute top-0 left-0 right-0 h-1.5"
                    style="background: repeating-linear-gradient(-45deg,#f97316 0 10px,#111827 10px 20px);"></div>
                <div class="relative h-full flex flex-col justify-center px-6 md:px-12 max-w-xl">
                    <p class="text-[11px] md:text-xs font-bold uppercase tracking-widest text-orange-400">NobleHomeDepot</p>
                    <h1 class="text-xl md:text-4xl font-extrabold text-white leading-tight mt-1 md:mt-2">
                        Build better.<br>Shop smarter.
                    </h1>
                    <p class="text-xs md:text-sm text-gray-300 mt-2 md:mt-3">
                        Building materials &amp; home essentials for every project.
                    </p>
                    <a href="<?= BASE_URL ?>/shop"
                        class="mt-4 md:mt-6 w-fit px-5 py-2.5 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-xs md:text-sm font-semibold transition-colors">
                        Shop now →
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Side promo cards -->
        <div class="grid grid-cols-2 lg:grid-cols-1 lg:grid-rows-2 gap-3 md:gap-4">

            <!-- Find a Professional (wala pang page — "Coming soon" modal muna) -->
            <button type="button" data-coming-soon
                class="group relative overflow-hidden rounded-xl bg-gray-900 p-4 md:p-5 flex flex-col justify-between min-h-[130px] text-left w-full">
                <div class="absolute top-0 left-0 right-0 h-1"
                    style="background: repeating-linear-gradient(-45deg,#f97316 0 8px,#111827 8px 16px);"></div>
                <i class="fa-solid fa-helmet-safety absolute -right-3 -bottom-3 text-6xl md:text-7xl text-white/5
                          group-hover:text-orange-500/20 group-hover:scale-110 transition-all duration-300"></i>
                <div class="relative">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-orange-400">Need help?</p>
                    <h3 class="text-sm md:text-base font-extrabold text-white leading-tight mt-1">Find a Professional</h3>
                    <p class="hidden md:block text-xs text-gray-400 mt-1">Contractors, installers &amp; designers.</p>
                </div>
                <span class="relative mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-white">
                    Find now
                    <i class="fa-solid fa-arrow-right text-[10px] text-orange-400 group-hover:translate-x-1 transition-transform"></i>
                </span>
            </button>

            <!-- Today's Deals -->
            <a href="#deals"
                class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 p-4 md:p-5 flex flex-col justify-between min-h-[130px]">
                <i class="fa-solid fa-tags absolute -right-3 -bottom-3 text-6xl md:text-7xl text-white/15
                          group-hover:scale-110 transition-transform duration-300"></i>
                <div class="relative">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-orange-100">Save more</p>
                    <h3 class="text-sm md:text-base font-extrabold text-white leading-tight mt-1">Today's Deals</h3>
                    <p class="hidden md:block text-xs text-orange-100 mt-1">Discounted materials &amp; fixtures.</p>
                </div>
                <span class="relative mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-white">
                    Shop deals
                    <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                </span>
            </a>
        </div>
    </div>

    <!-- ================= TRUST STRIP ================= -->
    <div class="hidden md:grid grid-cols-4 mt-4 bg-white border border-gray-200 rounded-xl divide-x divide-gray-100">
        <?php
        $trust = [
            ['fa-circle-check', 'Quality Products', 'Carefully inspected items'],
            ['fa-headset', 'Support Services', 'Mon–Fri 7AM–5PM · Sat 8AM–12PM'],
            ['fa-shield-halved', 'Secured Payment', 'Safe & encrypted checkout'],
            ['fa-tag', 'Exclusive Deals', 'Special offers & discounts'],
        ];
        foreach ($trust as [$icon, $title, $text]): ?>
            <div class="flex items-center gap-3 px-4 py-3">
                <span class="w-9 h-9 shrink-0 rounded-full bg-orange-50 flex items-center justify-center">
                    <i class="fa-solid <?= $icon ?> text-sm text-orange-500"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-gray-900"><?= $title ?></p>
                    <p class="text-[11px] text-gray-500 truncate"><?= $text ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($promoCount > 1): ?>
    <script>
        (function () {
            const slider = document.getElementById('promoSlider');
            const track = document.getElementById('promoTrack');
            if (!slider || !track) return;

            const total = <?= $promoCount ?>;
            const dots = slider.querySelectorAll('[data-dot]');
            let current = 0;
            let timer = null;

            function go(idx) {
                current = (idx + total) % total;
                track.style.transform = `translateX(-${current * 100}%)`;
                dots.forEach((d, i) => {
                    const on = i === current;
                    d.classList.toggle('w-6', on);
                    d.classList.toggle('bg-white', on);
                    d.classList.toggle('w-1.5', !on);
                    d.classList.toggle('bg-white/60', !on);
                });
            }

            function startAuto() { timer = setInterval(() => go(current + 1), 5000); }
            function resetAuto() { clearInterval(timer); startAuto(); }

            window.sliderMove = (dir) => { go(current + dir); resetAuto(); };
            window.sliderGo = (idx) => { go(idx); resetAuto(); };

            // Pause habang naka-hover (desktop)
            slider.addEventListener('mouseenter', () => clearInterval(timer));
            slider.addEventListener('mouseleave', resetAuto);

            // Swipe (mobile) — hindi mag-navigate ang link kapag swipe lang
            let startX = 0, dragDistance = 0;
            slider.addEventListener('touchstart', e => { startX = e.touches[0].clientX; dragDistance = 0; }, { passive: true });
            slider.addEventListener('touchmove', e => { dragDistance = e.touches[0].clientX - startX; }, { passive: true });
            slider.addEventListener('touchend', e => {
                const diff = startX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 40) { go(current + (diff > 0 ? 1 : -1)); resetAuto(); }
            });
            slider.querySelectorAll('[data-slide-link]').forEach(link => {
                link.addEventListener('click', e => { if (Math.abs(dragDistance) > 10) e.preventDefault(); });
            });

            startAuto();
        })();
    </script>
<?php endif; ?>

<!-- ================= COMING SOON MODAL (Find a Professional) ================= -->
<div id="comingSoonModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog"
    aria-modal="true" aria-labelledby="comingSoonTitle">
    <div data-cs-close class="absolute inset-0 bg-gray-900/50"></div>

    <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl overflow-hidden">
        <button type="button" data-cs-close aria-label="Close"
            class="absolute top-3 right-3 w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="px-6 pt-8 pb-6 text-center">
            <span class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                <i class="fa-solid fa-helmet-safety text-2xl text-gray-700"></i>
            </span>
            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-orange-600">Coming soon</p>
            <h3 id="comingSoonTitle" class="mt-1 text-xl font-bold text-gray-900">Find a Professional</h3>
            <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                We're preparing a directory of trusted contractors, installers and designers.
                This feature will be available soon.
            </p>
        </div>

        <div class="px-6 pb-6 flex flex-col sm:flex-row gap-2">
            <a href="<?= BASE_URL ?>/shop"
                class="flex-1 h-11 inline-flex items-center justify-center rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold transition-colors">
                Continue shopping
            </a>
            <button type="button" data-cs-close
                class="flex-1 h-11 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('comingSoonModal');
        if (!modal) return;

        function openModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-coming-soon]').forEach(el => {
            el.addEventListener('click', e => { e.preventDefault(); openModal(); });
        });
        modal.querySelectorAll('[data-cs-close]').forEach(el => el.addEventListener('click', closeModal));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    })();
</script>