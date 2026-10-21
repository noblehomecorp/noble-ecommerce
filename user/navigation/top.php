<?php
// top.php - Main Navigation Bar (2-tier e-commerce header)
// NOTE: Login logic is handled in /user/auth/google.php
$isLoggedIn = !empty($_SESSION['user_id']);

// Determine active nav link based on current path
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$isFindProfessionalActive = $currentPath === rtrim(parse_url(BASE_URL . '/find-professional', PHP_URL_PATH), '/');
$isInspirationActive = $currentPath === rtrim(parse_url(BASE_URL . '/inspiration', PHP_URL_PATH), '/');
$isShopActive = $currentPath === rtrim(parse_url(BASE_URL . '/shop', PHP_URL_PATH), '/');
$isHomeActive = $currentPath === rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/');
$isCartPageActive = $currentPath === rtrim(parse_url(BASE_URL . '/cartview', PHP_URL_PATH), '/');
$isCheckoutPage = $currentPath === rtrim(parse_url(BASE_URL . '/checkout', PHP_URL_PATH), '/');

$userFirstName = $isLoggedIn ? explode(' ', trim($_SESSION['user_name'] ?? ''))[0] : '';

// Active indicator (caret) under category-bar links
if (!function_exists('squigglyUnderline')) {
    function squigglyUnderline()
    {
        return '<i class="fa-solid fa-caret-up absolute left-1/2 -translate-x-1/2 -bottom-3 text-orange-500 pointer-events-none"></i>';
    }
}

// Helper: category-bar link classes
if (!function_exists('nhCatLinkClass')) {
    function nhCatLinkClass($active)
    {
        return 'relative flex items-center gap-1.5 h-full text-sm font-medium transition-colors duration-150 '
            . ($active ? 'text-orange-500' : 'text-gray-700 hover:text-orange-500');
    }
}
?>

<style>
    /* ---------- Page loader ---------- */
    #page-loader-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(2px);
        align-items: center;
        justify-content: center;
    }

    .loader-circle-wrap {
        position: relative;
        width: 100px;
        height: 100px;
    }

    .loader-circle-wrap .circle {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 0px;
        height: 0px;
        border-radius: 100%;
        opacity: 0;
        animation: pulse_4923 2s infinite linear;
        border: 0.5px solid #f97316;
        box-shadow: 0px 0px 5px #fdba74;
    }

    .loader-circle-wrap .circle:nth-child(1) { animation-delay: .2s; }
    .loader-circle-wrap .circle:nth-child(2) { animation-delay: .4s; }
    .loader-circle-wrap .circle:nth-child(3) { animation-delay: .8s; }
    .loader-circle-wrap .circle:nth-child(4) { animation-delay: 1s; }

    @keyframes pulse_4923 {
        0% { opacity: 0.0; width: 0px; height: 0px; transform: translate(-50%, -50%) scale(1); }
        10% { opacity: 0.5; transform: translate(-50%, -50%) scale(2); }
        100% { opacity: 0.0; width: 100px; height: 100px; transform: translate(-50%, -50%) scale(1); }
    }

    /* ---------- Construction hazard stripe ---------- */
    .nh-hazard {
        background: repeating-linear-gradient(-45deg,
                #f97316 0 10px,
                #111827 10px 20px);
    }

    /* ---------- Sticky header ----------
       Naka-sticky sa top: -36px (= taas ng utility strip), kaya natural na
       nagsi-scroll palayo ang utility strip at dumidikit ang hazard stripe +
       main bar + category bar. HINDI nagbabago ang taas ng header, kaya walang
       layout shift = walang pangingig habang nag-i-scroll. */
    #site-header {
        top: -36px;
        transition: box-shadow .2s ease;
    }

    #site-header .utility-strip {
        height: 36px;
    }

    #site-header .main-bar-inner {
        height: 72px;
    }

    /* ---------- Dim overlay habang bukas ang Account / Cart dropdown ----------
       Nasa ilalim ng header (z-30 < z-40), kaya kita pa rin ang header at dropdown.
       pointer-events:none para kusang magsara ang dropdown pag inalis ang mouse. */
    #nh-dropdown-backdrop {
        position: fixed;
        inset: 0;
        z-index: 30;
        background: rgba(17, 24, 39, 0.45);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .2s ease, visibility .2s ease;
    }

    /* Bawat selector ay hiwalay na rule: kapag may isang hindi suportado
       (hal. nested :has), hindi madadamay ang iba. JS fallback sa ibaba. */
    #nh-dropdown-backdrop.is-open {
        opacity: 1;
        visibility: visible;
    }

    /* Shadow lang ang nagbabago on scroll (walang epekto sa layout) */
    #site-header.is-scrolled {
        box-shadow: 0 4px 16px rgba(17, 24, 39, 0.08);
    }
</style>

<div id="page-loader-overlay" class="hidden">
    <div class="loader-circle-wrap">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
</div>

<!-- Dim overlay para sa Account / Cart dropdown -->
<div id="nh-dropdown-backdrop" aria-hidden="true"></div>

<!-- ===================== DESKTOP HEADER (md and up) ===================== -->
<header id="site-header" class="hidden md:block sticky z-40 bg-white">

    <!-- Tier 1: Utility strip (nagsi-scroll palayo; ang natitira ay dumidikit) -->
    <div class="utility-strip bg-gray-900 text-gray-300 text-xs overflow-hidden">
        <div class="max-w-screen-xl mx-auto px-4 h-full flex items-center justify-between">
            <p class="flex items-center gap-2 truncate">
                <i class="fa-solid fa-helmet-safety text-orange-500"></i>
                Building materials &amp; home essentials by
                <span class="text-white font-semibold">NobleHomeDepot</span>
            </p>
            <div class="flex items-center gap-5 shrink-0">
                <a href="<?= BASE_URL ?>/find-professional"
                    class="flex items-center gap-1.5 hover:text-orange-400 transition-colors <?= $isFindProfessionalActive ? 'text-orange-400' : '' ?>">
                    <i class="ci ci-fp"></i> Find a Professional
                </a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= BASE_URL ?>/orders" class="flex items-center gap-1.5 hover:text-orange-400 transition-colors">
                        <i class="fa-solid fa-truck-fast"></i> Track Orders
                    </a>
                    <a href="<?= BASE_URL ?>/system-notifications"
                        class="flex items-center gap-1.5 hover:text-orange-400 transition-colors">
                        <i class="fa-sharp fa-solid fa-bell"></i> Notifications
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hazard stripe accent (nananatiling nakikita kapag naka-stick) -->
    <div class="nh-hazard h-1"></div>

    <!-- Tier 2: Main bar (logo + search + actions) -->
    <nav id="desktop-top-nav" class="border-b border-gray-200">
        <div class="main-bar-inner max-w-screen-xl mx-auto px-4 flex items-center gap-6">

            <!-- Logo -->
            <a href="<?= BASE_URL ?>" class="flex items-center gap-2 shrink-0" aria-label="NobleHomeDepot Home">
                <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="w-10 h-10 object-contain">
                <span class="hidden lg:block text-lg font-extrabold tracking-tight leading-none">
                    <span class="text-orange-500">Noble</span><span class="text-gray-900">HomeDepot</span>
                </span>
            </a>

            <!-- Search (always visible on desktop/tablet) -->
            <form action="<?= BASE_URL ?>/shop" method="GET" class="relative flex-1 max-w-2xl"
                id="desktop-search-form">
                <div
                    class="flex items-stretch h-11 rounded-lg overflow-hidden border-2 border-orange-500 bg-white focus-within:ring-4 focus-within:ring-orange-100 transition-shadow">
                    <div class="flex items-center pl-3 text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" />
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                        </svg>
                    </div>

                    <div class="relative flex-1">
                        <input type="text" name="search" id="desktop-search-input" autocomplete="off"
                            aria-label="Search products"
                            class="text-sm text-gray-700 px-3 w-full h-full outline-none bg-transparent relative z-10" />

                        <div id="desktop-placeholder-wrap"
                            class="absolute inset-0 flex items-center px-3 pointer-events-none overflow-hidden z-0">
                            <span class="text-sm text-gray-400 whitespace-nowrap">Search for&nbsp;</span>
                            <span id="desktop-placeholder-text"
                                class="text-sm text-gray-400 whitespace-nowrap placeholder-slide">
                                products...
                            </span>
                        </div>
                    </div>

                    <button type="submit"
                        class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-5 transition-colors duration-150 whitespace-nowrap">
                        Search
                    </button>
                </div>

                <!-- Suggestions dropdown -->
                <div id="desktop-search-suggestions"
                    class="hidden absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-100 z-50 max-h-80 overflow-y-auto">
                </div>

                <!-- Error tooltip -->
                <div id="desktop-search-error" class="hidden absolute top-full left-4 mt-2 z-50">
                    <div
                        class="relative bg-gray-800 text-white text-xs px-3 py-1.5 rounded-md shadow-lg whitespace-nowrap">
                        Please fill in the blank.
                        <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-800 rotate-45"></div>
                    </div>
                </div>
            </form>

            <!-- Actions -->
            <div class="flex items-center gap-1 shrink-0">

                <!-- Saved -->
                <a href="<?= BASE_URL ?>/saved"
                    class="hidden lg:flex flex-col items-center justify-center px-3 py-1 rounded-lg text-gray-600 hover:text-orange-500 hover:bg-orange-50 transition-colors">
                    <i class="fa-sharp fa-solid fa-bookmark text-lg"></i>
                    <span class="text-[11px] font-semibold mt-0.5">Saved</span>
                </a>

                <!-- Account -->
                <?php if ($isLoggedIn): ?>
                    <div class="relative group" id="account-menu">
                        <button type="button"
                            class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-orange-50 transition-colors focus:outline-none">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>"
                                    alt="<?= htmlspecialchars($_SESSION['user_name']) ?>" referrerpolicy="no-referrer"
                                    onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs font-bold',textContent:'<?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>'}))"
                                    class="w-8 h-8 rounded-full object-cover ring-2 ring-orange-200" />
                            <?php else: ?>
                                <div
                                    class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs font-bold">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <span class="hidden lg:flex flex-col items-start leading-tight text-left">
                                <span class="text-[11px] text-gray-500">Hi, <?= htmlspecialchars($userFirstName) ?></span>
                                <span class="text-sm font-bold text-gray-900 flex items-center gap-1">
                                    My Account <i class="fa-solid fa-chevron-down text-[9px] text-gray-400"></i>
                                </span>
                            </span>
                        </button>

                        <!-- Dropdown Panel (hover + keyboard focus) -->
                        <div class="absolute top-full right-0 pt-2 w-60 opacity-0 invisible
                            group-hover:opacity-100 group-hover:visible
                            group-focus-within:opacity-100 group-focus-within:visible
                            transition-all duration-200 z-50">
                            <div class="relative bg-white rounded-lg shadow-xl border border-gray-100">
                                <div
                                    class="absolute -top-1.5 right-6 w-3 h-3 bg-white border-t border-l border-gray-100 rotate-45">
                                </div>
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-semibold text-gray-800 truncate">
                                        <?= htmlspecialchars($_SESSION['user_name']) ?>
                                    </p>
                                    <?php if (!empty($_SESSION['user_email'])): ?>
                                        <p class="text-xs text-gray-500 truncate mt-0.5">
                                            <?= htmlspecialchars($_SESSION['user_email']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="py-1">
                                    <a href="<?= BASE_URL ?>/profile"
                                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                        <i class="fa-solid fa-user w-4 text-center"></i> My Profile
                                    </a>
                                    <a href="<?= BASE_URL ?>/orders"
                                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                        <i class="fa-sharp fa-solid fa-cart-shopping w-4 text-center"></i> My Orders
                                    </a>
                                    <a href="<?= BASE_URL ?>/saved"
                                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                        <i class="fa-sharp fa-solid fa-bookmark w-4 text-center"></i> Saved Items
                                    </a>
                                    <a href="<?= BASE_URL ?>/find-professional"
                                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                        <i class="ci ci-fp w-4 text-center text-lg"></i> Find Professional
                                    </a>
                                    <a href="<?= BASE_URL ?>/system-notifications"
                                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                                        <i class="fa-sharp fa-solid fa-message w-4 text-center"></i> System Notifications
                                    </a>
                                </div>
                                <div class="border-t border-gray-100 py-1">
                                    <a href="<?= BASE_URL ?>/logout"
                                        class="flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50 transition-colors duration-150">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <button type="button"
                        class="google-login-trigger flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-orange-50 transition-colors">
                        <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fa-solid fa-user text-gray-600"></i>
                        </span>
                        <span class="hidden lg:flex flex-col items-start leading-tight text-left">
                            <span class="text-[11px] text-gray-500">Hello, Sign in</span>
                            <span class="text-sm font-bold text-gray-900">Account</span>
                        </span>
                    </button>
                <?php endif; ?>

                <!-- Cart -->
                <div class="relative group <?= $isCheckoutPage ? 'hidden' : '' ?>" id="cart-icon-wrapper">
                    <a href="<?= BASE_URL ?>/cartview"
                        class="flex items-center gap-2 ml-1 pl-3 pr-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-orange-500 transition-colors duration-150">
                        <span class="relative inline-flex">
                            <i class="fa-solid fa-cart-shopping text-base"></i>
                            <span id="cart-count"
                                class="hidden absolute -top-1.5 -right-2 bg-orange-500 text-white text-[9px] font-bold leading-none min-w-[15px] h-[15px] px-[3px] rounded-full flex items-center justify-center">
                            </span>
                        </span>
                        <span class="text-sm font-bold">Cart</span>
                    </a>

                    <?php if ($isLoggedIn): ?>
                        <?php include ROOT_PATH . '/user/navigation/cart-dropdown.php'; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Tier 3: Category bar -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-screen-xl mx-auto px-4 h-11 flex items-center gap-7">

            <?php include ROOT_PATH . '/user/navigation/navproductscategory.php'; ?>

            <span class="h-5 w-px bg-gray-200"></span>

            <a href="<?= BASE_URL ?>/inspiration" class="<?= nhCatLinkClass($isInspirationActive) ?>">
                Inspiration
                <?php if ($isInspirationActive) echo squigglyUnderline(); ?>
            </a>

            <a href="<?= BASE_URL ?>/find-professional" class="<?= nhCatLinkClass($isFindProfessionalActive) ?>">
                Find Professional
                <?php if ($isFindProfessionalActive) echo squigglyUnderline(); ?>
            </a>

            <!-- External link: no active state, opens in new tab -->
            <a href="https://www.yfk20.com/login" target="_blank" rel="noopener"
                class="ml-auto flex items-center gap-1.5 text-sm font-semibold text-orange-600 hover:text-orange-500 transition-colors">
                Sunpina
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
            </a>
        </div>
    </div>
</header>

<!-- ===================== MOBILE SEARCH OVERLAY (triggered from bottom nav) ===================== -->
<div id="mobile-search-bar"
    class="hidden md:hidden fixed top-0 left-0 right-0 z-[70] bg-white border-b border-gray-200 shadow-md px-4 pt-4 pb-3">
    <div class="flex items-center gap-2">
        <div
            class="flex-1 flex items-center border border-gray-300 rounded-md overflow-hidden focus-within:ring-2 focus-within:ring-orange-400 relative">
            <div class="flex items-center pl-3 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8" />
                    <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                </svg>
            </div>
            <input type="text" id="mobile-search-input" autocomplete="off" placeholder="Search for products..."
                class="text-sm text-gray-700 placeholder-gray-400 px-3 py-2 flex-1 outline-none bg-white" />
            <button id="mobile-search-submit"
                class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 transition-colors duration-150">
                Search
            </button>
        </div>
        <button id="mobile-search-close"
            class="p-2 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none shrink-0"
            aria-label="Close search">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div id="mobile-search-suggestions"
        class="hidden absolute top-full left-4 right-4 mt-1 bg-white rounded-lg shadow-lg border border-gray-100 z-50 max-h-80 overflow-y-auto">
    </div>

    <div id="mobile-search-error" class="hidden absolute top-full left-6 mt-1 z-50">
        <div class="relative bg-gray-800 text-white text-xs px-3 py-1.5 rounded-md shadow-lg whitespace-nowrap">
            Please fill in the blank.
            <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-800 rotate-45"></div>
        </div>
    </div>
</div>

<div id="search-suggest-backdrop" class="hidden fixed inset-0 bg-black/40 z-30"></div>

<div id="mobile-search-backdrop" class="hidden fixed inset-0 bg-black/40 z-[65] md:hidden"></div>

<!-- ===================== MOBILE SIDEBAR ===================== -->

<!-- Backdrop -->
<div id="sidebar-backdrop"
    class="fixed inset-0 bg-black/40 z-50 hidden opacity-0 transition-opacity duration-300 md:hidden"></div>

<!-- Sidebar panel (slides in from the right) -->
<div id="mobile-sidebar" class="fixed top-0 right-0 h-full w-72 max-w-[85vw] bg-white z-[60] shadow-2xl
            translate-x-full transition-transform duration-300 ease-in-out
            flex flex-col md:hidden">

    <div class="nh-hazard h-1 shrink-0"></div>

    <!-- Sidebar Header -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100 shrink-0">
        <a href="<?= BASE_URL ?>" class="flex items-center gap-2">
            <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="h-8 object-contain"
                onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'font-bold text-lg text-gray-900',textContent:'NobleHome'}))">
            <span class="text-base font-extrabold tracking-tight">
                <span class="text-orange-500">Noble</span><span class="text-gray-900">HomeDepot</span>
            </span>
        </a>
        <button id="sidebar-close" class="p-2 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none"
            aria-label="Close menu">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Scrollable Body -->
    <div class="flex-1 overflow-y-auto">

        <?php if ($isLoggedIn): ?>
            <!-- User Info -->
            <div class="flex items-center gap-3 px-4 py-4 bg-orange-50 border-b border-orange-100">
                <?php if (!empty($_SESSION['user_avatar'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>"
                        alt="<?= htmlspecialchars($_SESSION['user_name']) ?>" referrerpolicy="no-referrer"
                        onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white text-sm font-bold shrink-0',textContent:'<?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>'}))"
                        class="w-10 h-10 rounded-full object-cover shrink-0 ring-2 ring-orange-300" />
                <?php else: ?>
                    <div
                        class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white text-sm font-bold shrink-0">
                        <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($_SESSION['user_name']) ?>
                    </p>
                    <?php if (!empty($_SESSION['user_email'])): ?>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Nav Section -->
        <div class="px-3 py-3 space-y-0.5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Menu</p>

            <a href="<?= BASE_URL ?>/shop"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-bag-shopping text-lg"></i>
                Shop
            </a>

            <a href="<?= BASE_URL ?>/inspiration"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-burst text-lg"></i>
                Inspiration
            </a>

            <a href="<?= BASE_URL ?>/find-professional"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="ci ci-fp text-lg"></i>
                Find Professional
            </a>

            <a href="https://www.yfk20.com/login" target="_blank" rel="noopener"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-semibold text-orange-600 hover:bg-orange-50 transition-colors duration-150">
                <i class="fa-solid fa-arrow-up-right-from-square text-base"></i>
                Sunpina
            </a>
        </div>

        <!-- Quick Links (small mobile only) -->
        <div class="px-3 py-2 space-y-0.5 sm:hidden">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Quick Links</p>
            <a href="<?= BASE_URL ?>/cartview"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-cart-flatbed text-lg w-5 text-center"></i>
                Cart
            </a>
            <a href="<?= BASE_URL ?>/saved"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-solid fa-bookmark text-lg w-5 text-center"></i>
                Saved Items
            </a>
            <a href="<?= BASE_URL ?>/system-notifications"
                class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                <i class="fa-sharp fa-solid fa-message text-lg w-5 text-center"></i>
                System Notifications
            </a>
        </div>

        <?php if ($isLoggedIn): ?>
            <!-- Account Links -->
            <div class="px-3 py-2 space-y-0.5 border-t border-gray-100 mt-2">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Account</p>
                <a href="<?= BASE_URL ?>/profile"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <i class="fa-solid fa-circle-user text-lg"></i>
                    My Profile
                </a>
                <a href="<?= BASE_URL ?>/orders"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <i class="fa-solid fa-cart-flatbed"></i>
                    My Orders
                </a>
                <a href="<?= BASE_URL ?>/settings"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-500 transition-colors duration-150">
                    <i class="fa-solid fa-gear text-lg"></i>
                    Settings
                </a>
            </div>
        <?php endif; ?>

    </div>

    <!-- Sidebar Footer: Login / Logout pinned at bottom -->
    <div class="shrink-0 px-4 py-4 border-t border-gray-100">
        <?php if ($isLoggedIn): ?>
            <a href="<?= BASE_URL ?>/logout"
                class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-medium text-red-500 border border-red-200 hover:bg-red-50 transition-colors duration-150">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Logout
            </a>
        <?php else: ?>
            <button type="button"
                class="google-login-trigger flex items-center justify-center gap-2 w-full bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors duration-150">
                <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                        fill="#fff" opacity=".9" />
                    <path
                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                        fill="#fff" opacity=".9" />
                    <path
                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
                        fill="#fff" opacity=".9" />
                    <path
                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                        fill="#fff" opacity=".9" />
                </svg>
                Login with Google
            </button>
        <?php endif; ?>
    </div>

</div>

<!-- ===================== MOBILE BOTTOM NAV ===================== -->
<nav id="mobile-bottom-nav"
    class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200
           flex items-stretch justify-between px-1 pb-[env(safe-area-inset-bottom)] shadow-[0_-1px_6px_rgba(0,0,0,0.06)]">

    <!-- Home -->
    <a href="<?= BASE_URL ?>" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium
               <?= $isHomeActive ? 'text-orange-500' : 'text-gray-500' ?>">
        <i class="fa-sharp fa-solid fa-house-chimney-window text-lg"></i>
        Home
    </a>

    <!-- Search -->
    <button id="mobile-search-toggle"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium text-gray-500 focus:outline-none">
        <i class="fa-solid fa-magnifying-glass text-lg"></i>
        Search
    </button>

    <!-- Cart (elevated center action button) -->
    <a href="<?= BASE_URL ?>/cartview" class="flex-1 flex flex-col items-center justify-center relative -mt-3">
        <div class="w-12 h-12 rounded-full bg-orange-500 flex items-center justify-center relative ring-4 ring-white">
            <i class="fa-solid fa-cart-flatbed text-white text-lg"></i>
            <span id="cart-count-bottom"
                class="hidden absolute -top-1 -right-1 bg-gray-900 text-white text-[10px] font-bold min-w-[16px] h-4 px-1 rounded-full flex items-center justify-center">
            </span>
        </div>
        <span
            class="text-[10px] font-medium mt-0.5 <?= $isCartPageActive ? 'text-orange-500' : 'text-gray-500' ?>">Cart</span>
    </a>

    <!-- Inspiration -->
    <a href="<?= BASE_URL ?>/inspiration" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium
               <?= $isInspirationActive ? 'text-orange-500' : 'text-gray-500' ?>">
        <i class="fa-solid fa-burst text-lg"></i>
        Inspiration
    </a>

    <!-- Account / Menu (opens same sidebar) -->
    <button id="mobile-bottomnav-menu-toggle"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium text-gray-500 focus:outline-none">
        <?php if ($isLoggedIn && !empty($_SESSION['user_avatar'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="" referrerpolicy="no-referrer"
                onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'fa-solid fa-circle-user text-lg'}))"
                class="w-5 h-5 rounded-full object-cover" />
        <?php else: ?>
            <i class="fa-solid fa-circle-user text-lg"></i>
        <?php endif; ?>
        <?= $isLoggedIn ? 'Account' : 'Menu' ?>
    </button>
</nav>

<script>
    const menuToggle = document.getElementById('mobile-menu-toggle'); // optional (wala na sa desktop header)
    const sidebar = document.getElementById('mobile-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const sidebarClose = document.getElementById('sidebar-close');
    const searchToggle = document.getElementById('mobile-search-toggle');
    const searchBar = document.getElementById('mobile-search-bar');
    const searchClose = document.getElementById('mobile-search-close');
    const searchBackdrop = document.getElementById('mobile-search-backdrop');
    const bottomNavMenuToggle = document.getElementById('mobile-bottomnav-menu-toggle');
    const BASE_URL = '<?= BASE_URL ?>';

    // ===================== DROPDOWN DIM OVERLAY =====================
    // Dumidilim ang page habang bukas ang My Account / Cart dropdown (desktop).
    (function () {
        const backdrop = document.getElementById('nh-dropdown-backdrop');
        if (!backdrop) return;

        const triggers = [
            document.getElementById('account-menu'),
            document.getElementById('cart-dropdown') ? document.getElementById('cart-icon-wrapper') : null
        ].filter(Boolean);

        const isOpen = () => window.innerWidth >= 768 &&
            triggers.some(el => el.matches(':hover') || el.matches(':focus-within'));

        const sync = () => backdrop.classList.toggle('is-open', isOpen());

        triggers.forEach(el => {
            el.addEventListener('mouseenter', sync);
            el.addEventListener('mouseleave', () => setTimeout(sync, 0));
            el.addEventListener('focusin', sync);
            el.addEventListener('focusout', () => setTimeout(sync, 0));
        });
        window.addEventListener('resize', sync);
    })();

    // ===================== STICKY HEADER SHADOW =====================
    // Shadow lang ang tino-toggle — walang binabagong height, kaya walang scroll jitter.
    (function () {
        const header = document.getElementById('site-header');
        if (!header) return;
        let ticking = false;
        const update = () => {
            header.classList.toggle('is-scrolled', window.scrollY > 36);
            ticking = false;
        };
        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });
        update();
    })();

    function openSidebar() {
        if (!backdrop || !sidebar) return;
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => {
            backdrop.classList.add('opacity-100');
            sidebar.classList.remove('translate-x-full');
        });
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!backdrop || !sidebar) return;
        backdrop.classList.remove('opacity-100');
        sidebar.classList.add('translate-x-full');
        setTimeout(() => backdrop.classList.add('hidden'), 300);
        document.body.style.overflow = '';
    }

    if (menuToggle) menuToggle.addEventListener('click', openSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
    if (bottomNavMenuToggle) bottomNavMenuToggle.addEventListener('click', openSidebar);

    function openMobileSearch() {
        if (!searchBar || !searchBackdrop) return;
        searchBar.classList.remove('hidden');
        searchBackdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const input = document.getElementById('mobile-search-input');
            if (input) input.focus();
        }, 50);
    }

    function closeMobileSearch() {
        if (!searchBar || !searchBackdrop) return;
        searchBar.classList.add('hidden');
        searchBackdrop.classList.add('hidden');
        document.body.style.overflow = '';
    }

    if (searchToggle) searchToggle.addEventListener('click', openMobileSearch);
    if (searchClose) searchClose.addEventListener('click', closeMobileSearch);
    if (searchBackdrop) searchBackdrop.addEventListener('click', closeMobileSearch);

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            closeSidebar();
            closeMobileSearch();
        }
    });

    // Sync cart count badge between top navbar and bottom nav
    (function () {
        const cartCountTop = document.getElementById('cart-count');
        const cartCountBottom = document.getElementById('cart-count-bottom');
        if (!cartCountTop || !cartCountBottom) return;

        const syncCartBadge = () => {
            cartCountBottom.textContent = cartCountTop.textContent;
            cartCountBottom.classList.toggle('hidden', cartCountTop.classList.contains('hidden'));
        };

        const observer = new MutationObserver(syncCartBadge);
        observer.observe(cartCountTop, { attributes: true, childList: true, characterData: true, subtree: true });
        syncCartBadge();
    })();

    // ===================== SEARCH SUGGESTIONS =====================
    (function () {
        const searchSuggestUrl = '<?= BASE_URL ?>/search-suggest';
        const suggestBackdrop = document.getElementById('search-suggest-backdrop');

        // Track kung alin sa mga dropdown ang currently open
        const openDropdowns = new Set();

        function showSuggestBackdrop(dropdownEl) {
            openDropdowns.add(dropdownEl);
            if (suggestBackdrop) suggestBackdrop.classList.remove('hidden');
        }
        function hideSuggestBackdrop(dropdownEl) {
            openDropdowns.delete(dropdownEl);
            if (openDropdowns.size === 0 && suggestBackdrop) {
                suggestBackdrop.classList.add('hidden');
            }
        }

        function formatPrice(num) {
            return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        }

        function renderSuggestions(dropdown, suggestions) {
            if (!suggestions || suggestions.length === 0) {
                dropdown.innerHTML = '<p class="px-4 py-3 text-xs text-gray-400">No matching products.</p>';
                dropdown.classList.remove('hidden');
                showSuggestBackdrop(dropdown);
                return;
            }
            dropdown.innerHTML = suggestions.map(item => `
            <a href="<?= BASE_URL ?>/mainproductview?id=${item.id}"
               class="flex items-center gap-3 px-3 py-2.5 hover:bg-orange-50 transition-colors duration-150 border-b border-gray-50 last:border-0">
                <div class="w-10 h-10 rounded-lg bg-gray-50 overflow-hidden flex items-center justify-center border border-gray-100 shrink-0">
                    ${item.image
                    ? `<img src="${item.image}" class="w-full h-full object-contain p-1">`
                    : `<i class="fa-solid fa-image text-gray-300"></i>`}
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-gray-800 font-medium truncate">${item.name}</p>
                    <p class="text-xs text-gray-400 truncate">
                        ${item.category ?? ''}${item.price !== null ? ' · ₱' + formatPrice(item.price) : ''}
                    </p>
                </div>
            </a>
        `).join('');
            dropdown.classList.remove('hidden');
            showSuggestBackdrop(dropdown);
        }

        function attachSearchBox(inputEl, dropdownEl) {
            if (!inputEl || !dropdownEl) return;
            let debounceTimer = null;
            let activeController = null;

            inputEl.addEventListener('input', function () {
                const q = inputEl.value.trim();
                clearTimeout(debounceTimer);

                if (q.length < 2) {
                    dropdownEl.classList.add('hidden');
                    dropdownEl.innerHTML = '';
                    hideSuggestBackdrop(dropdownEl);
                    return;
                }

                debounceTimer = setTimeout(async () => {
                    if (activeController) activeController.abort();
                    activeController = new AbortController();
                    try {
                        const res = await fetch(`${searchSuggestUrl}?q=${encodeURIComponent(q)}`, {
                            signal: activeController.signal
                        });
                        const data = await res.json();
                        renderSuggestions(dropdownEl, data.suggestions);
                    } catch (e) {
                        if (e.name !== 'AbortError') {
                            dropdownEl.classList.add('hidden');
                            hideSuggestBackdrop(dropdownEl);
                        }
                    }
                }, 250);
            });

            inputEl.addEventListener('focus', function () {
                if (inputEl.value.trim().length >= 2 && dropdownEl.innerHTML !== '') {
                    dropdownEl.classList.remove('hidden');
                    showSuggestBackdrop(dropdownEl);
                }
            });

            document.addEventListener('click', function (e) {
                if (!dropdownEl.contains(e.target) && e.target !== inputEl) {
                    dropdownEl.classList.add('hidden');
                    hideSuggestBackdrop(dropdownEl);
                }
            });
        }

        // Click sa backdrop = close lahat ng bukas na dropdown
        if (suggestBackdrop) {
            suggestBackdrop.addEventListener('click', function () {
                document.querySelectorAll('#desktop-search-suggestions, #mobile-search-suggestions').forEach(d => {
                    d.classList.add('hidden');
                    openDropdowns.delete(d);
                });
                suggestBackdrop.classList.add('hidden');
            });
        }

        attachSearchBox(
            document.getElementById('desktop-search-input'),
            document.getElementById('desktop-search-suggestions')
        );
        attachSearchBox(
            document.getElementById('mobile-search-input'),
            document.getElementById('mobile-search-suggestions')
        );
    })();

    // ===================== SEARCH VALIDATION =====================
    const desktopSearchForm = document.getElementById('desktop-search-form');
    if (desktopSearchForm) {
        desktopSearchForm.addEventListener('submit', function (e) {
            const input = document.getElementById('desktop-search-input');
            const tooltip = document.getElementById('desktop-search-error');
            if (input && input.value.trim() === '') {
                e.preventDefault();
                if (tooltip) tooltip.classList.remove('hidden');
                input.focus();
            } else if (tooltip) {
                tooltip.classList.add('hidden');
            }
        });
    }

    const desktopSearchInput = document.getElementById('desktop-search-input');
    if (desktopSearchInput) {
        desktopSearchInput.addEventListener('input', function () {
            const tooltip = document.getElementById('desktop-search-error');
            if (tooltip) tooltip.classList.add('hidden');
        });
    }

    const mobileSearchSubmit = document.getElementById('mobile-search-submit');
    if (mobileSearchSubmit) {
        mobileSearchSubmit.addEventListener('click', function () {
            const input = document.getElementById('mobile-search-input');
            const tooltip = document.getElementById('mobile-search-error');
            if (!input) return;
            if (input.value.trim() === '') {
                if (tooltip) tooltip.classList.remove('hidden');
                input.focus();
                return;
            }
            if (tooltip) tooltip.classList.add('hidden');
            closeMobileSearch();
            window.location.href = `<?= BASE_URL ?>/shop?search=${encodeURIComponent(input.value.trim())}`;
        });
    }

    const mobileSearchInput = document.getElementById('mobile-search-input');
    if (mobileSearchInput) {
        mobileSearchInput.addEventListener('input', function () {
            const tooltip = document.getElementById('mobile-search-error');
            if (tooltip) tooltip.classList.add('hidden');
        });
        // Enter key sa mobile search
        mobileSearchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && mobileSearchSubmit) {
                e.preventDefault();
                mobileSearchSubmit.click();
            }
        });
    }

    // Mawala ang tooltip pag click sa labas
    document.addEventListener('click', function (e) {
        const desktopInput = document.getElementById('desktop-search-input');
        const desktopTooltip = document.getElementById('desktop-search-error');
        if (desktopTooltip && desktopInput && e.target !== desktopInput) {
            desktopTooltip.classList.add('hidden');
        }

        const mobileInput = document.getElementById('mobile-search-input');
        const mobileTooltip = document.getElementById('mobile-search-error');
        if (mobileTooltip && mobileInput && e.target !== mobileInput) {
            mobileTooltip.classList.add('hidden');
        }
    });

    // ===================== NAV LINK LOADING OVERLAY =====================
    // Wala nang 2s na artificial delay — lalabas lang ang loader habang naglo-load ang susunod na page.
    (function () {
        const loaderOverlay = document.getElementById('page-loader-overlay');
        if (!loaderOverlay) return;

        const navContainers = [
            document.getElementById('site-header'),
            document.getElementById('mobile-sidebar'),
            document.getElementById('mobile-bottom-nav')
        ].filter(Boolean);

        navContainers.forEach(container => {
            container.querySelectorAll('a[href]').forEach(link => {
                link.addEventListener('click', function (e) {
                    const href = link.getAttribute('href');

                    // Skip hash, new-tab, modifier-clicks, or opt-out links
                    if (!href || href.startsWith('#') || link.target === '_blank' ||
                        link.hasAttribute('data-no-loader') ||
                        e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
                        return;
                    }

                    loaderOverlay.classList.remove('hidden');
                    loaderOverlay.style.display = 'flex';
                });
            });
        });

        function forceHideLoader() {
            loaderOverlay.classList.add('hidden');
            loaderOverlay.style.display = 'none';
        }

        window.addEventListener('pageshow', forceHideLoader);
        forceHideLoader();
    })();

    // ===================== ANIMATED SEARCH PLACEHOLDER =====================
    (function () {
        const placeholderWords = [
            'products...',
            'tiles...',
            'doors...',
            'lights...',
            'materials...'
        ];

        function setupRotatingPlaceholder(inputEl, textEl, wrapEl) {
            if (!inputEl || !textEl || !wrapEl) return;
            let index = 0;

            function showNextWord() {
                textEl.classList.remove('placeholder-slide-in');
                textEl.classList.add('placeholder-slide-out');

                setTimeout(() => {
                    index = (index + 1) % placeholderWords.length;
                    textEl.textContent = placeholderWords[index];
                    textEl.classList.remove('placeholder-slide-out');
                    textEl.classList.add('placeholder-slide-in');
                }, 350);
            }

            function syncVisibility() {
                const hasValue = inputEl.value.trim().length > 0;
                wrapEl.style.opacity = hasValue ? '0' : '1';
            }

            inputEl.addEventListener('input', syncVisibility);
            inputEl.addEventListener('focus', syncVisibility);
            inputEl.addEventListener('blur', syncVisibility);

            setInterval(showNextWord, 2200);
            syncVisibility();
        }

        setupRotatingPlaceholder(
            document.getElementById('desktop-search-input'),
            document.getElementById('desktop-placeholder-text'),
            document.getElementById('desktop-placeholder-wrap')
        );
    })();

    // ===================== GOOGLE LOGIN POPUP =====================
    (function () {
        function openGoogleLoginPopup() {
            const width = 500, height = 620;
            const left = window.screenX + (window.outerWidth - width) / 2;
            const top = window.screenY + (window.outerHeight - height) / 2.5;

            const popup = window.open(
                `${BASE_URL}/google`,
                'google-login',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );

            if (!popup) {
                // Popup blocked — fallback to full-page redirect
                window.location.href = `${BASE_URL}/google`;
                return;
            }

            const loaderOverlay = document.getElementById('page-loader-overlay');
            if (loaderOverlay) {
                loaderOverlay.classList.remove('hidden');
                loaderOverlay.style.display = 'flex';
            }

            const pollTimer = setInterval(() => {
                if (popup.closed) {
                    clearInterval(pollTimer);
                    if (loaderOverlay) {
                        loaderOverlay.classList.add('hidden');
                        loaderOverlay.style.display = 'none';
                    }
                }
            }, 400);
        }

        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin) return;
            if (!event.data || !event.data.type) return;

            if (event.data.type === 'google-login-success') {
                window.location.reload();
            } else if (event.data.type === 'google-login-error') {
                console.error('Google login failed:', event.data.message);
                const loaderOverlay = document.getElementById('page-loader-overlay');
                if (loaderOverlay) {
                    loaderOverlay.classList.add('hidden');
                    loaderOverlay.style.display = 'none';
                }
                alert('Login failed. Please try again.');
            }
        });

        document.querySelectorAll('.google-login-trigger').forEach(btn => {
            btn.addEventListener('click', openGoogleLoginPopup);
        });
    })();
</script>