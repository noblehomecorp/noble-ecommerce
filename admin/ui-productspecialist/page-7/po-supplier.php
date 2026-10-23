<?php
// admin/ui-productspecialist/page-7/po-supplier.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch supplier types for dropdown
$types = [];
$resTypes = $conn->query("SELECT * FROM noblecompanysuppliertype ORDER BY type_name ASC");
while ($row = $resTypes->fetch_assoc()) {
    $types[] = $row;
}

// Fetch all suppliers (+ number of linked products)
$suppliers = [];
$resSuppliers = $conn->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM nobleproductsupplierlink l WHERE l.supplier_id = s.id) AS product_count
    FROM noblecompanysupplier s
    ORDER BY s.created_at DESC
");
while ($row = $resSuppliers->fetch_assoc()) {
    $suppliers[] = $row;
}

$totalSuppliers = count($suppliers);
$totalLinked = 0;
$withContact = 0;
foreach ($suppliers as $s) {
    $totalLinked += (int) $s['product_count'];
    if (!empty($s['suppersonname'])) $withContact++;
}

/**
 * Renders the supplier form fields (shared by Add and Edit modals).
 * Field names are unchanged; Edit fields keep their original "edit-*" IDs.
 */
function nhSupplierFields(string $mode, array $types): void
{
    $id = fn($n) => $mode === 'edit' ? 'id="edit-' . $n . '"' : 'id="add-' . $n . '"';
    $input = 'w-full h-10 border border-gray-300 rounded-md px-3 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-gray-900';
    $label = 'block text-xs font-semibold text-gray-700 mb-1.5';
    ?>
    <!-- Logo -->
    <div class="flex items-center gap-4">
        <div onclick="document.getElementById('<?= $mode ?>-logo-input').click()"
            class="w-20 h-20 shrink-0 border border-dashed border-gray-300 rounded-lg flex items-center justify-center cursor-pointer overflow-hidden bg-gray-50 hover:border-gray-500 transition-colors">
            <img id="<?= $mode ?>-logo-preview" class="hidden w-full h-full object-contain p-1" alt="" />
            <div id="<?= $mode ?>-logo-placeholder" class="text-center">
                <i class="fa-solid fa-building text-gray-300 text-xl"></i>
            </div>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-900">Company logo</p>
            <p class="text-[11px] text-gray-500 mt-0.5">PNG or JPG, transparent background works best.</p>
            <button type="button" onclick="document.getElementById('<?= $mode ?>-logo-input').click()"
                class="mt-2 h-8 px-3 rounded-md border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-50">
                <?= $mode === 'edit' ? 'Change logo' : 'Upload logo' ?>
            </button>
        </div>
        <input type="file" id="<?= $mode ?>-logo-input" name="suplogoimagecompany" accept="image/*" class="hidden"
            onchange="previewLogo(this, '<?= $mode ?>-logo-preview', '<?= $mode ?>-logo-placeholder')">
    </div>

    <!-- Company -->
    <div>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-3">Company details</p>
        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
                <label class="<?= $label ?>">Supplier name <span class="text-red-500">*</span></label>
                <input type="text" name="supname" <?= $id('supname') ?> required placeholder="e.g. ABC Trading Corp." class="<?= $input ?>">
            </div>
            <div class="col-span-2">
                <label class="<?= $label ?>">Supplier type <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <select name="suptype" <?= $id('suptype') ?> required class="nh-type-select flex-1 <?= $input ?> bg-white">
                        <option value="">Select type</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t['type_name']) ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="openTypeModal()" title="Manage supplier types"
                        class="shrink-0 h-10 px-3 rounded-md border border-gray-300 text-xs font-medium text-gray-600 hover:bg-gray-50 inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-sliders text-[11px]"></i> Manage
                    </button>
                </div>
            </div>
            <div class="col-span-2">
                <label class="<?= $label ?>">Address</label>
                <input type="text" name="supaddress" <?= $id('supaddress') ?> placeholder="Street, City, Province" class="<?= $input ?>">
            </div>
            <div class="col-span-2">
                <label class="<?= $label ?>">Country / Region</label>
                <input type="text" name="countryregion" <?= $id('countryregion') ?> placeholder="e.g. Philippines" class="<?= $input ?>">
            </div>
        </div>
    </div>

    <!-- Contact -->
    <div class="pt-4 border-t border-gray-100">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-3">Contact person</p>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="<?= $label ?>">Name</label>
                <input type="text" name="suppersonname" <?= $id('suppersonname') ?> placeholder="Full name" class="<?= $input ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Job title</label>
                <input type="text" name="suppersonjobtitle" <?= $id('suppersonjobtitle') ?> placeholder="e.g. Sales Manager" class="<?= $input ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Phone number</label>
                <input type="text" name="suppersonnumber" <?= $id('suppersonnumber') ?> placeholder="+63 9XX XXX XXXX" class="<?= $input ?>">
            </div>
            <div>
                <label class="<?= $label ?>">Email</label>
                <input type="email" name="suppersonemail" <?= $id('suppersonemail') ?> placeholder="contact@supplier.com" class="<?= $input ?>">
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO Supplier</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .nh-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .nh-scroll::-webkit-scrollbar { width: 8px; }
        .nh-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>

    <div class="ml-60 min-h-screen bg-gray-50 px-8 py-7">

        <!-- Header -->
        <div class="flex items-end justify-between gap-4 mb-6">
            <div>
                <nav class="flex items-center gap-1.5 text-[11px] text-gray-500 mb-1.5" aria-label="Breadcrumb">
                    <span>Product Specialist</span>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-900 font-medium">Suppliers</span>
                </nav>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Suppliers</h1>
                <p class="text-xs text-gray-500 mt-1">Manage your company suppliers and the products they supply.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="openTypeModal()"
                    class="h-9 px-3 rounded-md border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 inline-flex items-center gap-2">
                    <i class="fa-solid fa-tags text-[11px] text-gray-500"></i> Supplier Types
                </button>
                <button type="button" onclick="openAddModal()"
                    class="h-9 px-4 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold inline-flex items-center gap-2 transition-colors">
                    <i class="fa-solid fa-plus text-[11px]"></i> Add Supplier
                </button>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-4 gap-3 mb-4">
            <?php foreach ([
                ['Suppliers', $totalSuppliers, 'fa-truck-ramp-box'],
                ['Supplier types', count($types), 'fa-tags'],
                ['Linked products', $totalLinked, 'fa-boxes-stacked'],
                ['With contact person', $withContact, 'fa-address-card'],
            ] as [$label, $value, $icon]): ?>
                <div class="bg-white border border-gray-200 rounded-lg px-4 py-3.5 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-medium text-gray-500"><?= $label ?></p>
                        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= number_format($value) ?></p>
                    </div>
                    <span class="w-9 h-9 rounded-md bg-gray-100 flex items-center justify-center">
                        <i class="fa-solid <?= $icon ?> text-sm text-gray-500"></i>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($suppliers)): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                <span class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                    <i class="fa-solid fa-truck-ramp-box text-gray-400"></i>
                </span>
                <p class="mt-3 text-sm font-semibold text-gray-900">No suppliers yet</p>
                <p class="text-xs text-gray-500 mt-1">Add your first supplier to start linking products.</p>
                <button type="button" onclick="openAddModal()"
                    class="mt-4 h-9 px-4 rounded-md bg-gray-900 hover:bg-black text-white text-xs font-semibold">Add Supplier</button>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="bg-white rounded-lg border border-gray-200 p-3 mb-4 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[240px] max-w-md">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                    <input id="supSearch" type="text" placeholder="Search supplier, contact, email or location..."
                        class="w-full h-9 pl-8 pr-3 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-gray-900">
                </div>
                <select id="supTypeFilter"
                    class="h-9 px-3 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900">
                    <option value="">All types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars(mb_strtolower($t['type_name'])) ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span id="supResults" class="text-xs text-gray-500 ml-auto"><?= $totalSuppliers ?> suppliers</span>
            </div>

            <!-- Supplier table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="nh-scroll overflow-auto" style="max-height: calc(100vh - 330px);">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-50 text-[11px] font-semibold text-gray-500 border-b border-gray-200">
                                <th class="text-left px-4 py-2.5">Supplier</th>
                                <th class="text-left px-4 py-2.5">Type</th>
                                <th class="text-left px-4 py-2.5">Contact person</th>
                                <th class="text-left px-4 py-2.5">Location</th>
                                <th class="text-center px-4 py-2.5">Products</th>
                                <th class="text-right px-4 py-2.5">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($suppliers as $s):
                                $search = mb_strtolower(implode(' ', [
                                    $s['supname'] ?? '', $s['suptype'] ?? '', $s['supaddress'] ?? '', $s['countryregion'] ?? '',
                                    $s['suppersonname'] ?? '', $s['suppersonjobtitle'] ?? '', $s['suppersonemail'] ?? '', $s['suppersonnumber'] ?? '',
                                ]));
                                $location = trim(implode(', ', array_filter([$s['supaddress'] ?? '', $s['countryregion'] ?? ''])));
                                ?>
                                <tr class="nh-sup-row hover:bg-gray-50/70" data-search="<?= htmlspecialchars($search) ?>"
                                    data-type="<?= htmlspecialchars(mb_strtolower($s['suptype'] ?? '')) ?>">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-md border border-gray-200 bg-white flex items-center justify-center shrink-0 overflow-hidden">
                                                <?php if (!empty($s['suplogoimagecompany'])): ?>
                                                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($s['suplogoimagecompany']) ?>"
                                                        class="w-full h-full object-contain p-0.5" alt="">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-building text-gray-300"></i>
                                                <?php endif; ?>
                                            </div>
                                            <p class="font-semibold text-gray-900 truncate max-w-[220px]" title="<?= htmlspecialchars($s['supname']) ?>">
                                                <?= htmlspecialchars($s['supname']) ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($s['suptype'])): ?>
                                            <span class="inline-flex px-2 py-0.5 rounded border border-gray-200 bg-gray-50 text-[11px] font-medium text-gray-700 whitespace-nowrap">
                                                <?= htmlspecialchars($s['suptype']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-300">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($s['suppersonname'])): ?>
                                            <p class="text-xs font-medium text-gray-900">
                                                <?= htmlspecialchars($s['suppersonname']) ?>
                                                <?php if (!empty($s['suppersonjobtitle'])): ?>
                                                    <span class="font-normal text-gray-500">· <?= htmlspecialchars($s['suppersonjobtitle']) ?></span>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="flex flex-col gap-0.5 mt-0.5">
                                            <?php if (!empty($s['suppersonemail'])): ?>
                                                <a href="mailto:<?= htmlspecialchars($s['suppersonemail']) ?>"
                                                    class="text-[11px] text-gray-500 hover:text-gray-900 truncate max-w-[220px]">
                                                    <i class="fa-regular fa-envelope mr-1 text-gray-400"></i><?= htmlspecialchars($s['suppersonemail']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($s['suppersonnumber'])): ?>
                                                <span class="text-[11px] text-gray-500">
                                                    <i class="fa-solid fa-phone mr-1 text-gray-400 text-[10px]"></i><?= htmlspecialchars($s['suppersonnumber']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (empty($s['suppersonname']) && empty($s['suppersonemail']) && empty($s['suppersonnumber'])): ?>
                                            <span class="text-gray-300">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-xs text-gray-600 max-w-[220px] truncate" title="<?= htmlspecialchars($location) ?>">
                                            <?= $location !== '' ? htmlspecialchars($location) : '<span class="text-gray-300">—</span>' ?>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-xs font-semibold <?= (int) $s['product_count'] > 0 ? 'text-gray-900' : 'text-gray-400' ?>">
                                            <?= (int) $s['product_count'] ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="<?= BASE_URL ?>/ps-supplierlink?supplier_id=<?= (int) $s['id'] ?>"
                                                class="h-8 px-3 rounded-md border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1.5 whitespace-nowrap">
                                                <i class="fa-solid fa-boxes-stacked text-[11px] text-gray-500"></i> Products
                                            </a>
                                            <button type="button" title="Edit supplier"
                                                onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                                class="w-8 h-8 rounded-md flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-gray-900">
                                                <i class="fa-solid fa-pen text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="supNoResults" class="hidden p-10 text-center">
                        <p class="text-sm font-semibold text-gray-900">No matches found</p>
                        <p class="text-xs text-gray-500 mt-1">Try a different keyword or type.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── ADD MODAL ── -->
    <div id="add-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-gray-900/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <p class="text-base font-bold text-gray-900">Add Supplier</p>
                    <p class="text-xs text-gray-500 mt-0.5">Fields marked <span class="text-red-500">*</span> are required.</p>
                </div>
                <button type="button" onclick="closeAddModal()" aria-label="Close"
                    class="w-8 h-8 -mr-2 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="add-form" enctype="multipart/form-data" class="flex flex-col min-h-0">
                <div class="nh-scroll px-6 py-5 space-y-5 overflow-y-auto">
                    <?php nhSupplierFields('add', $types); ?>
                </div>
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex justify-end gap-2">
                    <button type="button" onclick="closeAddModal()"
                        class="h-9 px-4 rounded-md border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" onclick="submitAdd()" id="add-submit-btn"
                        class="h-9 px-4 rounded-md bg-gray-900 hover:bg-black text-white text-xs font-semibold inline-flex items-center gap-2 disabled:opacity-60">
                        <i class="fa-solid fa-plus"></i> Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── EDIT MODAL ── -->
    <div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-gray-900/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <p class="text-base font-bold text-gray-900">Edit Supplier</p>
                    <p class="text-xs text-gray-500 mt-0.5">Update the supplier's details.</p>
                </div>
                <button type="button" onclick="closeEditModal()" aria-label="Close"
                    class="w-8 h-8 -mr-2 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="edit-form" enctype="multipart/form-data" class="flex flex-col min-h-0">
                <input type="hidden" name="supplier_id" id="edit-supplier-id">
                <input type="hidden" name="existing_logo" id="edit-existing-logo">
                <div class="nh-scroll px-6 py-5 space-y-5 overflow-y-auto">
                    <?php nhSupplierFields('edit', $types); ?>
                </div>
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex justify-end gap-2">
                    <button type="button" onclick="closeEditModal()"
                        class="h-9 px-4 rounded-md border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" onclick="submitEdit()" id="edit-submit-btn"
                        class="h-9 px-4 rounded-md bg-gray-900 hover:bg-black text-white text-xs font-semibold inline-flex items-center gap-2 disabled:opacity-60">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── TYPE MANAGER MODAL ── -->
    <div id="type-modal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4 bg-gray-900/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm overflow-hidden">
            <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <p class="text-base font-bold text-gray-900">Supplier Types</p>
                    <p class="text-xs text-gray-500 mt-0.5">Add or rename the types used for suppliers.</p>
                </div>
                <button type="button" onclick="closeTypeModal()" aria-label="Close"
                    class="w-8 h-8 -mr-2 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Add new type -->
            <div class="px-5 pt-4">
                <label for="new-type-input" class="block text-xs font-semibold text-gray-700 mb-1.5">New type</label>
                <div class="flex gap-2">
                    <input type="text" id="new-type-input" placeholder="e.g. Logistics / Delivery"
                        class="flex-1 h-9 border border-gray-300 rounded-md px-3 text-sm focus:outline-none focus:border-gray-900">
                    <button type="button" onclick="submitNewType()" id="new-type-btn"
                        class="shrink-0 h-9 px-3 rounded-md bg-gray-900 hover:bg-black text-white text-xs font-semibold inline-flex items-center gap-1.5 disabled:opacity-60">
                        <i class="fa-solid fa-plus text-[11px]"></i> Add
                    </button>
                </div>
            </div>

            <div class="px-5 pt-4 pb-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                    Existing types <span id="type-count" class="text-gray-400 normal-case tracking-normal">(<?= count($types) ?>)</span>
                </p>
            </div>
            <div class="nh-scroll px-3 pb-3 max-h-64 overflow-y-auto" id="type-list">
                <?php foreach ($types as $t): $tid = (int) $t['id']; ?>
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-gray-50" id="type-row-<?= $tid ?>">
                        <span class="flex-1 text-sm text-gray-800 truncate" id="type-label-<?= $tid ?>"><?= htmlspecialchars($t['type_name']) ?></span>
                        <input type="text" id="type-input-<?= $tid ?>" value="<?= htmlspecialchars($t['type_name']) ?>"
                            data-original="<?= htmlspecialchars($t['type_name']) ?>"
                            class="hidden flex-1 h-8 border border-gray-300 rounded-md px-2 text-sm focus:outline-none focus:border-gray-900">
                        <button type="button" onclick="startEditType(<?= $tid ?>)" id="type-edit-btn-<?= $tid ?>" title="Rename"
                            class="w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-900">
                            <i class="fa-solid fa-pen text-xs"></i>
                        </button>
                        <button type="button" onclick="saveEditType(<?= $tid ?>)" id="type-save-btn-<?= $tid ?>" title="Save"
                            class="hidden w-8 h-8 rounded-md items-center justify-center bg-gray-900 text-white hover:bg-black">
                            <i class="fa-solid fa-check text-xs"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
                <p id="type-empty" class="<?= empty($types) ? '' : 'hidden' ?> px-2 py-3 text-xs text-gray-500">No types yet.</p>
            </div>

            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex justify-end">
                <button type="button" onclick="closeTypeModal()"
                    class="h-9 px-4 rounded-md border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50">Done</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="nhToast" class="fixed bottom-5 right-5 z-[80] hidden items-center gap-2 px-4 py-3 rounded-md shadow-lg text-sm text-white bg-gray-900">
        <i id="nhToastIcon" class="fa-solid fa-circle-info"></i>
        <span id="nhToastText"></span>
    </div>

    <script>
        const BASE = '<?= BASE_URL ?>';

        // ── Helpers ──
        function escHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }
        let toastTimer = null;
        function toast(msg, type = 'error') {
            const el = document.getElementById('nhToast');
            el.classList.remove('bg-gray-900', 'bg-red-600', 'bg-green-600');
            el.classList.add(type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-gray-900');
            document.getElementById('nhToastIcon').className = 'fa-solid ' +
                (type === 'success' ? 'fa-circle-check' : type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
            document.getElementById('nhToastText').textContent = msg;
            el.classList.remove('hidden'); el.classList.add('flex');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { el.classList.add('hidden'); el.classList.remove('flex'); }, 3000);
        }
        function showModal(id) {
            const m = document.getElementById(id);
            m.classList.remove('hidden'); m.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        function hideModal(id) {
            const m = document.getElementById(id);
            m.classList.add('hidden'); m.classList.remove('flex');
            if (!document.querySelector('#add-modal.flex, #edit-modal.flex, #type-modal.flex')) document.body.style.overflow = '';
        }
        function validateForm(form) {
            const name = form.querySelector('[name="supname"]');
            const type = form.querySelector('[name="suptype"]');
            const email = form.querySelector('[name="suppersonemail"]');
            if (!name.value.trim()) { toast('Please enter the supplier name.'); name.focus(); return false; }
            if (!type.value) { toast('Please select a supplier type.'); type.focus(); return false; }
            if (email.value && !email.checkValidity()) { toast('Please enter a valid email address.'); email.focus(); return false; }
            return true;
        }

        // ── TYPE MODAL ──
        let typesChanged = false;
        function openTypeModal() {
            showModal('type-modal');
            setTimeout(() => document.getElementById('new-type-input').focus(), 50);
        }
        function closeTypeModal() {
            hideModal('type-modal');
            // Reload only if types changed and no supplier form is open (so typed data isn't lost)
            if (typesChanged && !document.querySelector('#add-modal.flex, #edit-modal.flex')) location.reload();
        }
        document.getElementById('type-modal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeTypeModal();
        });

        // Keep the type dropdowns in sync without reloading
        function syncTypeOption(oldName, newName) {
            document.querySelectorAll('.nh-type-select').forEach(sel => {
                if (oldName === null) {
                    const opt = document.createElement('option');
                    opt.value = newName; opt.textContent = newName;
                    sel.appendChild(opt);
                } else {
                    sel.querySelectorAll('option').forEach(o => {
                        if (o.value === oldName) { o.value = newName; o.textContent = newName; }
                    });
                }
            });
        }

        function startEditType(id) {
            document.getElementById('type-label-' + id).classList.add('hidden');
            document.getElementById('type-input-' + id).classList.remove('hidden');
            document.getElementById('type-edit-btn-' + id).classList.add('hidden');
            const save = document.getElementById('type-save-btn-' + id);
            save.classList.remove('hidden'); save.classList.add('flex');
            const input = document.getElementById('type-input-' + id);
            input.focus(); input.select();
        }
        function endEditType(id) {
            document.getElementById('type-label-' + id).classList.remove('hidden');
            document.getElementById('type-input-' + id).classList.add('hidden');
            document.getElementById('type-edit-btn-' + id).classList.remove('hidden');
            const save = document.getElementById('type-save-btn-' + id);
            save.classList.add('hidden'); save.classList.remove('flex');
        }

        function saveEditType(id) {
            const input = document.getElementById('type-input-' + id);
            const newName = input.value.trim();
            const oldName = input.dataset.original ?? '';
            if (!newName) { toast('Type name cannot be empty.'); return; }
            if (newName === oldName) { endEditType(id); return; }

            fetch(BASE + '/ps-backendsuppliertype-update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&type_name=${encodeURIComponent(newName)}`
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        document.getElementById('type-label-' + id).textContent = newName;
                        input.dataset.original = newName;
                        syncTypeOption(oldName, newName);
                        typesChanged = true;
                        endEditType(id);
                        toast('Type renamed.', 'success');
                    } else {
                        toast(res.error ?? 'Something went wrong.');
                    }
                })
                .catch(() => toast('Network error. Please try again.'));
        }

        function submitNewType() {
            const input = document.getElementById('new-type-input');
            const name = input.value.trim();
            if (!name) { toast('Please enter a type name.'); input.focus(); return; }

            const btn = document.getElementById('new-type-btn');
            btn.disabled = true;

            fetch(BASE + '/ps-backendsuppliertype-insert', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type_name=${encodeURIComponent(name)}`
            })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    if (!res.success) { toast(res.error ?? 'Something went wrong.'); return; }

                    const id = parseInt(res.id, 10);
                    const safe = escHtml(name);
                    const list = document.getElementById('type-list');
                    const div = document.createElement('div');
                    div.className = 'flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-gray-50';
                    div.id = 'type-row-' + id;
                    div.innerHTML = `
                        <span class="flex-1 text-sm text-gray-800 truncate" id="type-label-${id}">${safe}</span>
                        <input type="text" id="type-input-${id}" value="${safe}" data-original="${safe}"
                            class="hidden flex-1 h-8 border border-gray-300 rounded-md px-2 text-sm focus:outline-none focus:border-gray-900">
                        <button type="button" onclick="startEditType(${id})" id="type-edit-btn-${id}" title="Rename"
                            class="w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-900">
                            <i class="fa-solid fa-pen text-xs"></i>
                        </button>
                        <button type="button" onclick="saveEditType(${id})" id="type-save-btn-${id}" title="Save"
                            class="hidden w-8 h-8 rounded-md items-center justify-center bg-gray-900 text-white hover:bg-black">
                            <i class="fa-solid fa-check text-xs"></i>
                        </button>`;
                    list.insertBefore(div, document.getElementById('type-empty'));
                    document.getElementById('type-empty').classList.add('hidden');
                    const count = list.querySelectorAll('[id^="type-row-"]').length;
                    document.getElementById('type-count').textContent = `(${count})`;
                    syncTypeOption(null, name);
                    typesChanged = true;
                    input.value = '';
                    input.focus();
                    toast('Type added.', 'success');
                })
                .catch(() => { btn.disabled = false; toast('Network error. Please try again.'); });
        }

        // Enter / Esc shortcuts
        document.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.id === 'new-type-input') { e.preventDefault(); submitNewType(); }
            if (e.key === 'Enter' && e.target.id && e.target.id.startsWith('type-input-')) {
                e.preventDefault(); saveEditType(e.target.id.replace('type-input-', ''));
            }
            if (e.key === 'Escape') {
                if (document.getElementById('type-modal').classList.contains('flex')) closeTypeModal();
                else if (document.getElementById('edit-modal').classList.contains('flex')) closeEditModal();
                else if (document.getElementById('add-modal').classList.contains('flex')) closeAddModal();
            }
        });

        // ── Logo Preview ──
        function previewLogo(input, previewId, placeholderId) {
            const file = input.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) { toast('Please select an image file.'); input.value = ''; return; }
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById(previewId);
                const ph = document.getElementById(placeholderId);
                img.src = e.target.result;
                img.classList.remove('hidden');
                ph.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }

        // ── ADD MODAL ──
        function openAddModal() {
            document.getElementById('add-form').reset();
            document.getElementById('add-logo-preview').classList.add('hidden');
            document.getElementById('add-logo-placeholder').classList.remove('hidden');
            showModal('add-modal');
            setTimeout(() => document.getElementById('add-supname').focus(), 50);
        }
        function closeAddModal() { hideModal('add-modal'); }
        document.getElementById('add-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeAddModal(); });

        function submitAdd() {
            const form = document.getElementById('add-form');
            if (!validateForm(form)) return;

            const btn = document.getElementById('add-submit-btn');
            const resetBtn = () => { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-plus"></i> Add Supplier'; };
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            fetch(BASE + '/ps-backendsupplier-insert', { method: 'POST', body: new FormData(form) })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        toast('Supplier added.', 'success');
                        closeAddModal();
                        setTimeout(() => location.reload(), 400);
                    } else {
                        resetBtn();
                        toast(res.error ?? 'Something went wrong.');
                    }
                })
                .catch(() => { resetBtn(); toast('Network error. Please try again.'); });
        }

        // ── EDIT MODAL ──
        function openEditModal(data) {
            document.getElementById('edit-supplier-id').value = data.id;
            document.getElementById('edit-existing-logo').value = data.suplogoimagecompany ?? '';
            document.getElementById('edit-supname').value = data.supname ?? '';
            document.getElementById('edit-suptype').value = data.suptype ?? '';
            document.getElementById('edit-supaddress').value = data.supaddress ?? '';
            document.getElementById('edit-countryregion').value = data.countryregion ?? '';
            document.getElementById('edit-suppersonname').value = data.suppersonname ?? '';
            document.getElementById('edit-suppersonjobtitle').value = data.suppersonjobtitle ?? '';
            document.getElementById('edit-suppersonnumber').value = data.suppersonnumber ?? '';
            document.getElementById('edit-suppersonemail').value = data.suppersonemail ?? '';

            // Show existing logo
            const img = document.getElementById('edit-logo-preview');
            const ph = document.getElementById('edit-logo-placeholder');
            if (data.suplogoimagecompany) {
                img.src = BASE + '/uploads/' + encodeURIComponent(data.suplogoimagecompany).replace(/%2F/g, '/');
                img.classList.remove('hidden');
                ph.classList.add('hidden');
            } else {
                img.classList.add('hidden');
                ph.classList.remove('hidden');
            }

            // Reset file input
            document.getElementById('edit-logo-input').value = '';
            showModal('edit-modal');
        }
        function closeEditModal() { hideModal('edit-modal'); }
        document.getElementById('edit-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeEditModal(); });

        function submitEdit() {
            const form = document.getElementById('edit-form');
            if (!validateForm(form)) return;

            const btn = document.getElementById('edit-submit-btn');
            const resetBtn = () => { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes'; };
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            fetch(BASE + '/ps-backendsupplier-update', { method: 'POST', body: new FormData(form) })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        toast('Changes saved.', 'success');
                        closeEditModal();
                        setTimeout(() => location.reload(), 400);
                    } else {
                        resetBtn();
                        toast(res.error ?? 'Something went wrong.');
                    }
                })
                .catch(() => { resetBtn(); toast('Network error. Please try again.'); });
        }

        // ── Search & type filter ──
        (function () {
            const search = document.getElementById('supSearch');
            const typeSel = document.getElementById('supTypeFilter');
            if (!search) return;
            const rows = document.querySelectorAll('.nh-sup-row');
            const total = rows.length;
            function apply() {
                const q = search.value.trim().toLowerCase();
                const t = typeSel.value;
                let shown = 0;
                rows.forEach(r => {
                    const ok = (!q || r.dataset.search.includes(q)) && (!t || r.dataset.type === t);
                    r.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                document.getElementById('supResults').textContent =
                    (q || t) ? `${shown} of ${total} suppliers` : `${total} suppliers`;
                document.getElementById('supNoResults').classList.toggle('hidden', shown > 0);
            }
            search.addEventListener('input', apply);
            typeSel.addEventListener('change', apply);
        })();
    </script>

</body>

</html>