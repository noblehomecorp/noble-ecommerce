<?php
// navbar.php - Admin Sidebar Navigation
$currentUrl = $_SERVER['REQUEST_URI'];

$role = $_SESSION['role'] ?? '';
$position = $_SESSION['position'] ?? POSITION_STAFF;
$isHead = $position === POSITION_HEAD;
$isStaff = $position === POSITION_STAFF;
$isCustodian = $position === POSITION_CUSTODIAN;
$isCustooAssistant = $position === POSITION_CUSTOASSISTANT;

if (!function_exists('isActiveNav')) {
    // true kapag ang kasalukuyang URL ay tugma sa $path
    function isActiveNav(string $path): bool
    {
        global $currentUrl;
        $currentPath = rtrim(parse_url($currentUrl, PHP_URL_PATH), '/');
        $path = rtrim($path, '/');
        return $currentPath === $path || str_ends_with($currentPath, $path);
    }
}

if (!function_exists('isActive')) {
    // Pinanatili para sa ibang file na gumagamit pa nito (nagbabalik ng classes)
    function isActive(string $path): string
    {
        return isActiveNav($path)
            ? 'nh-nav-active'
            : 'text-gray-600 hover:bg-gray-100/80 hover:text-gray-900';
    }
}

if (!function_exists('navLink')) {
    // Iisang style para sa lahat ng sidebar link
    function navLink(string $path, string $icon, string $label): void
    {
        $on = isActiveNav($path); ?>
        <a href="<?= BASE_URL . $path ?>" class="nh-nav-link group relative flex items-center gap-2.5 px-3 py-2 rounded-md text-xs font-medium transition-colors duration-150
                   <?= $on ? 'nh-nav-active' : 'text-gray-600 hover:bg-gray-100/80 hover:text-gray-900' ?>" <?= $on ? 'aria-current="page"' : '' ?>>
            <i
                class="fa-solid <?= $icon ?> w-4 text-center text-[13px] <?= $on ? 'text-orange-600' : 'text-gray-400 group-hover:text-gray-600' ?>"></i>
            <span class="truncate"><?= htmlspecialchars($label) ?></span>
        </a>
        <?php
    }
}

if (!function_exists('navLabel')) {
    function navLabel(string $text, bool $first = false): void
    { ?>
        <p
            class="px-3 pb-1.5 <?= $first ? 'pt-1' : 'pt-5' ?> text-[10px] font-semibold uppercase tracking-[0.12em] text-gray-400">
            <?= htmlspecialchars($text) ?>
        </p>
        <?php
    }
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = $_SESSION['email'] ?? '';
$adminInitial = strtoupper(substr($adminName ?: 'A', 0, 1));
$roleLabel = $role ? ucwords(strtolower($role)) : 'Department';
?>

<style>
    /* Active link: light orange + left accent bar */
    #sidebar .nh-nav-active {
        background: #fff7ed;
        color: #c2410c;
        font-weight: 600;
    }

    #sidebar .nh-nav-active::before {
        content: "";
        position: absolute;
        left: -12px;
        top: 6px;
        bottom: 6px;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: #f97316;
    }

    /* Manipis na scrollbar sa nav */
    #sidebar .nh-nav-scroll {
        scrollbar-width: thin;
        scrollbar-color: #e5e7eb transparent;
    }

    #sidebar .nh-nav-scroll::-webkit-scrollbar {
        width: 5px;
    }

    #sidebar .nh-nav-scroll::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 9999px;
    }
</style>

<!-- Sidebar -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-60 bg-white border-r border-gray-200 flex flex-col z-40 overflow-hidden transition-transform duration-300">

    <!-- Watermark logo (transparent sa background) -->
    <img src="<?= BASE_URL ?>/icon/logo.png" alt="" aria-hidden="true"
        class="pointer-events-none select-none absolute -right-14 bottom-24 w-64 max-w-none opacity-[0.05] grayscale z-0">

    <!-- Construction accent -->
    <div class="relative z-10 h-1 shrink-0"
        style="background: repeating-linear-gradient(-45deg,#f97316 0 8px,#111827 8px 16px);"></div>

    <!-- Logo / brand -->
    <div class="relative z-10 flex items-center gap-2.5 px-4 py-3.5 border-b border-gray-100 shrink-0">
        <span class="w-9 h-9 rounded-md bg-white border border-gray-200 flex items-center justify-center p-1 shrink-0">
            <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="w-full h-full object-contain">
        </span>
        <div class="leading-tight min-w-0">
            <p class="text-xs font-bold text-gray-900 tracking-tight">Noble<span class="text-orange-500">Home</span></p>
            <p class="text-[10px] text-gray-500 truncate" title="<?= htmlspecialchars($roleLabel) ?>">
                <?= htmlspecialchars($roleLabel) ?></p>
        </div>
    </div>

    <!-- Nav -->
    <nav class="nh-nav-scroll relative z-10 flex-1 px-3 py-3 space-y-0.5 overflow-y-auto">

        <?php navLabel('Main', true); ?>

        <!-- HR -->
        <?php if (in_array($role, [ROLE_HR])): ?>
            <?php navLink('/hr-main', 'fa-sliders', 'Dashboard'); ?>
            <?php navLink('/hr-registration-department', 'fa-building-user', 'Create Department'); ?>
            <?php navLink('/hr-registration-account', 'fa-user-plus', 'Create Account'); ?>

            <?php navLabel('Reports'); ?>
            <?php navLink('/hr-logs', 'fa-clock-rotate-left', 'Activity Logs'); ?>
        <?php endif; ?>

        <!-- PRODUCT SPECIALIST -->
        <?php if (in_array($role, [ROLE_PRODUCTSPECIALIST])): ?>
            <?php navLink('/ps-main', 'fa-sliders', 'Dashboard'); ?>
            <?php navLink('/ps-tracking', 'fa-truck-fast', 'Truck List'); ?>
            <?php navLink('/ps-insertproduct', 'fa-square-plus', 'Insert Product'); ?>
            <?php navLink('/ps-updateproduct', 'fa-pen-to-square', 'Update Product'); ?>

            <?php navLabel('Management'); ?>
            <?php navLink('/ps-quantitylimit', 'fa-ruler-combined', 'Quantity Limit'); ?>
            <?php navLink('/ps-promotiontimer', 'fa-clock', 'Promotion Timer'); ?>
            <?php navLink('/ps-promotion', 'fa-bullhorn', 'Promotion'); ?>
            <?php navLink('/ps-promotionwebsite', 'fa-globe', 'Promotion Website'); ?>
            <?php navLink('/ps-category', 'fa-list', 'Category'); ?>
            <?php navLink('/ps-productlinking', 'fa-link', 'Product Linking'); ?>
            <?php navLink('/ps-posupplier', 'fa-truck-ramp-box', 'PO Supplier'); ?>
        <?php endif; ?>

        <!-- ACCOUNTING -->
        <?php if (in_array($role, [ROLE_ACCOUNTING])): ?>
            <?php if ($position === 'head'): ?>
                <?php navLink('/accounting', 'fa-list-check', 'Order List'); ?>
                <?php navLink('/accountant-includepo', 'fa-file-invoice', 'PO List Pending'); ?>
                <?php navLink('/accountant-replacement', 'fa-rotate', 'Replacement Order'); ?>
            <?php endif; ?>

            <?php if ($position === 'staff'): ?>
                <?php navLink('/accountantstaff', 'fa-truck-ramp-box', 'PO Supplier Approval'); ?>
            <?php endif; ?>

            <?php if ($position === 'custodian'): ?>
                <?php navLink('/accountantcustodian', 'fa-truck-ramp-box', 'PO Supplier Approval'); ?>
            <?php endif; ?>
        <?php endif; ?>

        <!-- WAREHOUSE -->
        <?php if (in_array($role, [ROLE_WAREHOUSE])): ?>
            <?php if ($position === 'head'): ?>
                <?php navLink('/warehousemain', 'fa-list-check', 'Warehouse Order List'); ?>
            <?php endif; ?>

            <?php if ($position === 'warehousestaff'): ?>
                <?php navLink('/warehousestaff', 'fa-clipboard-list', 'My Assigned Orders'); ?>
            <?php endif; ?>

            <?php if ($position === 'warehousereceiver'): ?>
                <?php navLink('/warehousereceiver', 'fa-clipboard-list', 'My Assigned Orders'); ?>
                <?php navLink('/warehousereceiverstorage', 'fa-warehouse', 'Storage'); ?>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SUPER ADMIN -->
        <?php if (in_array($role, [ROLE_SUPERADMIN])): ?>
            <?php navLink('/superadmin', 'fa-file-invoice', 'PO List Pending'); ?>
        <?php endif; ?>

        <!-- LOGISTIC -->
        <?php if (in_array($role, [ROLE_LOGISTIC])): ?>
            <?php if ($position === 'logisticstaff'): ?>
                <?php navLink('/logisticstaff', 'fa-sliders', 'Dashboard'); ?>
            <?php endif; ?>

            <?php if ($position === 'logisticdispatcher'): ?>
                <?php navLink('/logisticdispatcher', 'fa-sliders', 'Dashboard'); ?>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SALES -->
        <?php if (in_array($role, [ROLE_SALES])): ?>
            <?php if ($position === 'staff'): ?>
                <?php navLink('/sales', 'fa-sliders', 'Dashboard'); ?>
                <?php navLink('/sales-replacementorder', 'fa-rotate', 'Replacement Order'); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php navLabel('Signature'); ?>
        <?php navLink('/signaturedinsert', 'fa-signature', 'Insert Signature'); ?>

        <?php navLabel('Notifications'); ?>
        <!-- Notifications trigger button -->
        <button id="notif-toggle" type="button" onclick="toggleNotif()" class="group relative flex items-center gap-2.5 px-3 py-2 rounded-md text-xs font-medium w-full
                   text-gray-600 hover:bg-gray-100/80 hover:text-gray-900 transition-colors duration-150">
            <span class="relative inline-flex items-center justify-center w-4">
                <i class="fa-solid fa-bell text-[13px] text-gray-400 group-hover:text-gray-600"></i>
                <span id="notif-badge" class="absolute -top-1.5 -right-2 bg-red-500 text-white text-[8px] font-bold leading-none
                    min-w-[14px] h-[14px] rounded-full flex items-center justify-center px-0.5 hidden">0</span>
            </span>
            Notifications
        </button>

        <!-- Flyout Panel (overlay, outside sidebar flow) -->
        <div id="notif-panel" class="fixed top-0 right-0 h-screen w-80 bg-white border-l border-gray-200 shadow-xl
           z-50 flex flex-col transform translate-x-full transition-transform duration-300 ease-in-out">

            <div class="h-1 shrink-0"
                style="background: repeating-linear-gradient(-45deg,#f97316 0 8px,#111827 8px 16px);"></div>

            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3.5 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-bell text-gray-500 text-xs"></i>
                    <span class="text-xs font-semibold text-gray-900">Notifications</span>
                </div>
                <button type="button" onclick="toggleNotif()" aria-label="Close notifications"
                    class="w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <!-- Sub-header -->
            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100 bg-gray-50">
                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-[0.12em]">Recent</span>
                <button type="button" onclick="markAllRead()"
                    class="text-[10px] font-semibold text-orange-600 hover:text-orange-700 transition">
                    Mark all as read
                </button>
            </div>

            <!-- List -->
            <div id="notif-list" class="flex-1 overflow-y-auto divide-y divide-gray-100 text-xs">
                <div class="px-4 py-6 text-xs text-gray-400 text-center">Loading…</div>
            </div>
        </div>

        <!-- Backdrop -->
        <div id="notif-backdrop" class="fixed inset-0 z-40 bg-gray-900/20 hidden" onclick="toggleNotif()"></div>

    </nav>

    <!-- Bottom: User Info + Logout -->
    <div class="relative z-10 border-t border-gray-100 p-3 bg-white/80 backdrop-blur-sm">
        <div class="flex items-center gap-2.5 px-1.5 py-1.5">
            <div
                class="w-8 h-8 rounded-full bg-gray-900 flex items-center justify-center text-white text-[11px] font-bold shrink-0 ring-2 ring-orange-500/30">
                <?= htmlspecialchars($adminInitial) ?>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-gray-900 truncate"><?= htmlspecialchars($adminName) ?></p>
                <p class="text-[10px] text-gray-500 truncate"><?= htmlspecialchars($adminEmail) ?></p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/logoutadmin"
            class="mt-1.5 flex items-center gap-2.5 w-full px-3 py-2 rounded-md text-xs font-medium text-red-600 hover:bg-red-50 transition-colors">
            <i class="fa-solid fa-right-from-bracket w-4 text-center text-[13px]"></i>
            Logout
        </a>
    </div>

</aside>

<script src="<?= BASE_URL ?>/js/notifications.js"></script>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>