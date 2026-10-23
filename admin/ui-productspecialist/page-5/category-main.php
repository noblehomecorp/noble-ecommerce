<?php
// category-main.php  (admin/ui-productspecialist/page-5/category-main.php)
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$result = $conn->query("
    SELECT c.*, COUNT(s.id) as sub_count
    FROM noblecategory c
    LEFT JOIN noblesubcategory s ON s.category_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$categories = $result->fetch_all(MYSQLI_ASSOC);

// All subcategories in one query (instead of one query per category), grouped by category
$subsByCat = [];
$subRes = $conn->query("SELECT * FROM noblesubcategory ORDER BY id DESC");
foreach ($subRes->fetch_all(MYSQLI_ASSOC) as $sub) {
    $subsByCat[(int) $sub['category_id']][] = $sub;
}

$totalCats = count($categories);
$totalSubs = 0;
$noImage = 0;
foreach ($categories as $c) {
    $totalSubs += (int) $c['sub_count'];
    if (empty($c['image'])) $noImage++;
}
foreach ($subsByCat as $list) {
    foreach ($list as $s) if (empty($s['image'])) $noImage++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .nh-cat[data-open="0"] .nh-cat-body { display: none; }
        .nh-cat[data-open="0"] .nh-cat-chev { transform: rotate(-90deg); }
        .nh-modal { display: none; }
        .nh-modal.is-open { display: flex; }
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
                    <span class="text-gray-900 font-medium">Categories</span>
                </nav>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Category Management</h1>
                <p class="text-xs text-gray-500 mt-1">Manage categories, subcategories and their images.</p>
            </div>
            <button type="button" onclick="openAddCategoryModal()"
                class="h-9 px-4 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold inline-flex items-center gap-2 transition-colors">
                <i class="fa-solid fa-plus text-[11px]"></i> Add Category
            </button>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 gap-3 mb-4">
            <?php foreach ([
                ['Categories', $totalCats, 'fa-layer-group'],
                ['Subcategories', $totalSubs, 'fa-sitemap'],
                ['Missing image', $noImage, 'fa-image'],
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

        <?php if (empty($categories)): ?>
            <!-- Empty state -->
            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                <span class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                    <i class="fa-solid fa-layer-group text-gray-400"></i>
                </span>
                <p class="mt-3 text-sm font-semibold text-gray-900">No categories yet</p>
                <p class="text-xs text-gray-500 mt-1">Create your first category to start organizing products.</p>
                <button type="button" onclick="openAddCategoryModal()"
                    class="mt-4 h-9 px-4 rounded-md bg-gray-900 hover:bg-black text-white text-xs font-semibold">
                    Add Category
                </button>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="bg-white rounded-lg border border-gray-200 p-3 mb-4 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[240px] max-w-md">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                    <input id="catSearch" type="text" placeholder="Search category or subcategory..."
                        class="w-full h-9 pl-8 pr-3 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-gray-900">
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="setAllCats(true)"
                        class="h-9 px-3 rounded-md text-xs font-medium text-gray-600 hover:bg-gray-100">Expand all</button>
                    <button type="button" onclick="setAllCats(false)"
                        class="h-9 px-3 rounded-md text-xs font-medium text-gray-600 hover:bg-gray-100">Collapse all</button>
                </div>
                <span id="catResults" class="text-xs text-gray-500 ml-auto"><?= $totalCats ?> categories</span>
            </div>

            <!-- Category List -->
            <div id="catList" class="space-y-2.5">
                <?php foreach ($categories as $cat):
                    $catId = (int) $cat['id'];
                    $subrows = $subsByCat[$catId] ?? [];
                    $catNameJs = htmlspecialchars(json_encode($cat['name']), ENT_QUOTES);
                    ?>
                    <div class="nh-cat bg-white rounded-lg border border-gray-200 overflow-hidden" data-open="1"
                        data-name="<?= htmlspecialchars(mb_strtolower($cat['name'])) ?>">

                        <!-- Category Row -->
                        <div class="flex items-center gap-3 px-4 py-3">
                            <button type="button" onclick="toggleCat(this)" aria-label="Toggle subcategories"
                                class="w-7 h-7 shrink-0 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                <i class="nh-cat-chev fa-solid fa-chevron-down text-[11px] transition-transform duration-200"></i>
                            </button>

                            <?php if (!empty($cat['image'])): ?>
                                <img src="<?= htmlspecialchars($cat['image']) ?>" alt=""
                                    class="w-11 h-11 rounded-md object-cover border border-gray-200 shrink-0">
                            <?php else: ?>
                                <div class="w-11 h-11 rounded-md bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center shrink-0"
                                    title="No image">
                                    <i class="fa-solid fa-image text-gray-300 text-sm"></i>
                                </div>
                            <?php endif; ?>

                            <div class="min-w-0 flex-1">
                                <h2 class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($cat['name']) ?></h2>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    <?= (int) $cat['sub_count'] ?> <?= (int) $cat['sub_count'] === 1 ? 'subcategory' : 'subcategories' ?>
                                    <?php if (empty($cat['image'])): ?>
                                        <span class="text-gray-300 mx-1">·</span><span class="text-amber-600">No image</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                    onclick="openAddSubModal(<?= $catId ?>, <?= $catNameJs ?>)"
                                    class="h-8 px-3 rounded-md border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-plus text-[10px]"></i> Subcategory
                                </button>
                                <button type="button" title="Change image"
                                    onclick="openEditCatImageModal(<?= $catId ?>, <?= $catNameJs ?>)"
                                    class="w-8 h-8 rounded-md flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-gray-900">
                                    <i class="fa-regular fa-image text-sm"></i>
                                </button>
                                <button type="button" title="Delete category"
                                    onclick="deleteCategory(<?= $catId ?>, <?= $catNameJs ?>)"
                                    class="w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-600">
                                    <i class="fa-regular fa-trash-can text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Subcategory List -->
                        <div class="nh-cat-body border-t border-gray-100 bg-gray-50/60 px-4 py-3">
                            <?php if (!empty($subrows)): ?>
                                <div class="grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-2">
                                    <?php foreach ($subrows as $sub):
                                        $subId = (int) $sub['id'];
                                        $subNameJs = htmlspecialchars(json_encode($sub['name']), ENT_QUOTES);
                                        ?>
                                        <div class="nh-sub group flex items-center gap-2.5 bg-white border border-gray-200 rounded-md px-2.5 py-2 hover:border-gray-300"
                                            data-name="<?= htmlspecialchars(mb_strtolower($sub['name'])) ?>">
                                            <?php if (!empty($sub['image'])): ?>
                                                <img src="<?= htmlspecialchars($sub['image']) ?>" alt=""
                                                    class="w-8 h-8 rounded object-cover border border-gray-200 shrink-0">
                                            <?php else: ?>
                                                <div class="w-8 h-8 rounded bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center shrink-0"
                                                    title="No image">
                                                    <i class="fa-solid fa-image text-gray-300 text-[10px]"></i>
                                                </div>
                                            <?php endif; ?>

                                            <span class="text-xs font-medium text-gray-800 flex-1 truncate" title="<?= htmlspecialchars($sub['name']) ?>">
                                                <?= htmlspecialchars($sub['name']) ?>
                                            </span>

                                            <div class="flex items-center opacity-60 group-hover:opacity-100 transition-opacity">
                                                <button type="button" title="Change image"
                                                    onclick="openEditSubImageModal(<?= $subId ?>, <?= $subNameJs ?>)"
                                                    class="w-7 h-7 rounded flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-gray-900">
                                                    <i class="fa-regular fa-image text-xs"></i>
                                                </button>
                                                <button type="button" title="Delete subcategory"
                                                    onclick="deleteSubcategory(<?= $subId ?>, <?= $subNameJs ?>)"
                                                    class="w-7 h-7 rounded flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                    <i class="fa-regular fa-trash-can text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-xs text-gray-500 py-1">
                                    No subcategories yet.
                                    <button type="button" onclick="openAddSubModal(<?= $catId ?>, <?= $catNameJs ?>)"
                                        class="font-semibold text-gray-900 underline underline-offset-2 hover:text-orange-600">Add one</button>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="catNoResults" class="hidden bg-white rounded-lg border border-gray-200 p-10 text-center">
                <p class="text-sm font-semibold text-gray-900">No matches found</p>
                <p class="text-xs text-gray-500 mt-1">Try a different keyword.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // Reusable modal shell
    function nhModalOpen($id, $title, $subtitleLabel = '', $subtitleId = '')
    {
        ?>
        <div id="<?= $id ?>" class="nh-modal fixed inset-0 z-50 items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-gray-900/50" data-modal-close></div>
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
                <div class="flex items-start justify-between px-5 pt-5 pb-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900"><?= $title ?></h2>
                        <?php if ($subtitleId): ?>
                            <p class="text-xs text-gray-500 mt-0.5"><?= $subtitleLabel ?>
                                <span id="<?= $subtitleId ?>" class="font-semibold text-gray-900"></span>
                            </p>
                        <?php endif; ?>
                    </div>
                    <button type="button" data-modal-close aria-label="Close"
                        class="w-8 h-8 -mr-2 -mt-1 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="px-5 pb-5">
        <?php
    }
    function nhModalClose($cancelFn, $submitFn, $submitLabel, $submitId)
    {
        ?>
                </div>
                <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                    <button type="button" onclick="<?= $cancelFn ?>()"
                        class="h-9 px-4 rounded-md border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" id="<?= $submitId ?>" onclick="<?= $submitFn ?>()"
                        class="h-9 px-4 rounded-md bg-gray-900 hover:bg-black text-white text-xs font-semibold inline-flex items-center gap-2 disabled:opacity-60">
                        <?= $submitLabel ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    function nhImagePicker($inputId, $label = 'Image')
    {
        ?>
        <label class="block text-xs font-semibold text-gray-700 mb-1.5"><?= $label ?></label>
        <label for="<?= $inputId ?>"
            class="nh-drop flex items-center gap-3 p-3 border border-dashed border-gray-300 rounded-md cursor-pointer hover:border-gray-500 hover:bg-gray-50 transition-colors">
            <span class="nh-drop-preview w-14 h-14 shrink-0 rounded-md bg-gray-100 flex items-center justify-center overflow-hidden">
                <i class="fa-solid fa-cloud-arrow-up text-gray-400"></i>
            </span>
            <span class="min-w-0">
                <span class="nh-drop-name block text-xs font-semibold text-gray-900 truncate">Choose an image</span>
                <span class="block text-[11px] text-gray-500 mt-0.5">JPG, PNG or WEBP · square works best</span>
            </span>
        </label>
        <input id="<?= $inputId ?>" type="file" accept="image/*" class="sr-only nh-file">
        <?php
    }
    ?>

    <!-- ===== ADD CATEGORY MODAL ===== -->
    <?php nhModalOpen('addCategoryModal', 'Add Category'); ?>
    <label for="categoryName" class="block text-xs font-semibold text-gray-700 mb-1.5">Category name <span class="text-red-500">*</span></label>
    <input id="categoryName" type="text" placeholder="e.g. Plumbing"
        class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm mb-4 focus:outline-none focus:border-gray-900">
    <?php nhImagePicker('categoryImage', 'Image <span class="font-normal text-gray-400">(optional)</span>'); ?>
    <?php nhModalClose('closeAddCategoryModal', 'submitAddCategory', 'Add Category', 'btnAddCategory'); ?>

    <!-- ===== EDIT CATEGORY IMAGE MODAL ===== -->
    <?php nhModalOpen('editCatImageModal', 'Update Category Image', 'Category:', 'editCatName'); ?>
    <?php nhImagePicker('editCatImage', 'New image'); ?>
    <input type="hidden" id="editCatId">
    <?php nhModalClose('closeEditCatImageModal', 'submitEditCatImage', 'Update', 'btnEditCatImage'); ?>

    <!-- ===== ADD SUBCATEGORY MODAL ===== -->
    <?php nhModalOpen('addSubModal', 'Add Subcategory', 'Under:', 'subModalCatName'); ?>
    <label for="subCategoryName" class="block text-xs font-semibold text-gray-700 mb-1.5">Subcategory name <span class="text-red-500">*</span></label>
    <input id="subCategoryName" type="text" placeholder="e.g. Faucets"
        class="w-full h-10 border border-gray-300 rounded-md px-3 text-sm mb-4 focus:outline-none focus:border-gray-900">
    <?php nhImagePicker('subCategoryImage', 'Image <span class="font-normal text-gray-400">(optional)</span>'); ?>
    <input type="hidden" id="subCategoryId">
    <?php nhModalClose('closeAddSubModal', 'submitAddSub', 'Add Subcategory', 'btnAddSub'); ?>

    <!-- ===== EDIT SUBCATEGORY IMAGE MODAL ===== -->
    <?php nhModalOpen('editSubImageModal', 'Update Subcategory Image', 'Subcategory:', 'editSubName'); ?>
    <?php nhImagePicker('editSubImage', 'New image'); ?>
    <input type="hidden" id="editSubId">
    <?php nhModalClose('closeEditSubImageModal', 'submitEditSubImage', 'Update', 'btnEditSubImage'); ?>

    <!-- ===== CONFIRM DELETE MODAL ===== -->
    <div id="confirmModal" class="nh-modal fixed inset-0 z-[60] items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-gray-900/50" data-confirm-cancel></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-sm p-5">
            <div class="flex gap-3">
                <span class="w-10 h-10 shrink-0 rounded-full bg-red-50 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-red-600"></i>
                </span>
                <div>
                    <h3 id="confirmTitle" class="text-sm font-bold text-gray-900">Delete?</h3>
                    <p id="confirmText" class="text-xs text-gray-600 mt-1 leading-relaxed"></p>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" data-confirm-cancel
                    class="h-9 px-4 rounded-md border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" id="confirmOk"
                    class="h-9 px-4 rounded-md bg-red-600 hover:bg-red-700 text-white text-xs font-semibold disabled:opacity-60">Delete</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="nhToast" class="fixed bottom-5 right-5 z-[70] hidden items-center gap-2 px-4 py-3 rounded-md shadow-lg text-sm text-white bg-gray-900">
        <i id="nhToastIcon" class="fa-solid fa-circle-info"></i>
        <span id="nhToastText"></span>
    </div>

    <script>
        const BASE = '<?= BASE_URL ?>';

        // ===== Helpers =====
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

        function openModal(id) {
            document.getElementById(id).classList.add('is-open');
            document.body.style.overflow = 'hidden';
            const first = document.querySelector('#' + id + ' input[type="text"]');
            if (first) setTimeout(() => first.focus(), 50);
        }
        function hideModal(id) {
            document.getElementById(id).classList.remove('is-open');
            if (!document.querySelector('.nh-modal.is-open')) document.body.style.overflow = '';
        }

        function setBusy(btnId, busy, label) {
            const b = document.getElementById(btnId);
            if (!b) return;
            if (busy) { b.dataset.label = b.innerHTML; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (label || 'Saving...'); b.disabled = true; }
            else { if (b.dataset.label) b.innerHTML = b.dataset.label; b.disabled = false; }
        }

        function postForm(url, formData, btnId) {
            setBusy(btnId, true);
            return fetch(url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if (d.success) { toast('Saved successfully.', 'success'); setTimeout(() => location.reload(), 500); }
                    else { toast(d.message || 'Something went wrong.'); setBusy(btnId, false); }
                })
                .catch(() => { toast('Network error. Please try again.'); setBusy(btnId, false); });
        }

        // Image picker previews
        function resetPicker(input) {
            const box = input.previousElementSibling;
            if (!box) return;
            box.querySelector('.nh-drop-preview').innerHTML = '<i class="fa-solid fa-cloud-arrow-up text-gray-400"></i>';
            box.querySelector('.nh-drop-name').textContent = 'Choose an image';
        }
        document.querySelectorAll('.nh-file').forEach(input => {
            input.addEventListener('change', () => {
                const file = input.files[0];
                const box = input.previousElementSibling;
                if (!file) { resetPicker(input); return; }
                if (!file.type.startsWith('image/')) { toast('Please select an image file.'); input.value = ''; resetPicker(input); return; }
                const img = document.createElement('img');
                img.className = 'w-full h-full object-cover';
                img.src = URL.createObjectURL(file);
                const prev = box.querySelector('.nh-drop-preview');
                prev.innerHTML = ''; prev.appendChild(img);
                box.querySelector('.nh-drop-name').textContent = file.name;
            });
        });

        // Close modals: backdrop / X / Esc
        const closers = {
            addCategoryModal: () => closeAddCategoryModal(),
            editCatImageModal: () => closeEditCatImageModal(),
            addSubModal: () => closeAddSubModal(),
            editSubImageModal: () => closeEditSubImageModal(),
        };
        document.querySelectorAll('[data-modal-close]').forEach(el => {
            el.addEventListener('click', () => { const m = el.closest('.nh-modal'); if (m && closers[m.id]) closers[m.id](); });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (document.getElementById('confirmModal').classList.contains('is-open')) { closeConfirm(); return; }
                const open = document.querySelector('.nh-modal.is-open');
                if (open && closers[open.id]) closers[open.id]();
            }
            if (e.key === 'Enter' && e.target.matches('#categoryName')) submitAddCategory();
            if (e.key === 'Enter' && e.target.matches('#subCategoryName')) submitAddSub();
        });

        // ===== Add Category Modal =====
        function openAddCategoryModal() { openModal('addCategoryModal'); }
        function closeAddCategoryModal() {
            hideModal('addCategoryModal');
            document.getElementById('categoryName').value = '';
            const f = document.getElementById('categoryImage'); f.value = ''; resetPicker(f);
        }
        function submitAddCategory() {
            const name = document.getElementById('categoryName').value.trim();
            const file = document.getElementById('categoryImage').files[0];
            if (!name) return toast('Please enter a category name.');

            const formData = new FormData();
            formData.append('name', name);
            if (file) formData.append('image', file);
            postForm(BASE + '/ps-backendadd-category', formData, 'btnAddCategory');
        }

        // ===== Edit Category Image Modal =====
        function openEditCatImageModal(catId, catName) {
            document.getElementById('editCatId').value = catId;
            document.getElementById('editCatName').textContent = catName;
            openModal('editCatImageModal');
        }
        function closeEditCatImageModal() {
            hideModal('editCatImageModal');
            const f = document.getElementById('editCatImage'); f.value = ''; resetPicker(f);
        }
        function submitEditCatImage() {
            const id = document.getElementById('editCatId').value;
            const file = document.getElementById('editCatImage').files[0];
            if (!file) return toast('Please select an image.');

            const formData = new FormData();
            formData.append('id', id);
            formData.append('image', file);
            postForm(BASE + '/ps-backendupdate-category-image', formData, 'btnEditCatImage');
        }

        // ===== Add Subcategory Modal =====
        function openAddSubModal(catId, catName) {
            document.getElementById('subCategoryId').value = catId;
            document.getElementById('subModalCatName').textContent = catName;
            openModal('addSubModal');
        }
        function closeAddSubModal() {
            hideModal('addSubModal');
            document.getElementById('subCategoryName').value = '';
            const f = document.getElementById('subCategoryImage'); f.value = ''; resetPicker(f);
        }
        function submitAddSub() {
            const name = document.getElementById('subCategoryName').value.trim();
            const catId = document.getElementById('subCategoryId').value;
            const file = document.getElementById('subCategoryImage').files[0];
            if (!name) return toast('Please enter a subcategory name.');

            const formData = new FormData();
            formData.append('name', name);
            formData.append('category_id', catId);
            if (file) formData.append('image', file);
            postForm(BASE + '/ps-backendadd-subcategory', formData, 'btnAddSub');
        }

        // ===== Edit Subcategory Image Modal =====
        function openEditSubImageModal(subId, subName) {
            document.getElementById('editSubId').value = subId;
            document.getElementById('editSubName').textContent = subName;
            openModal('editSubImageModal');
        }
        function closeEditSubImageModal() {
            hideModal('editSubImageModal');
            const f = document.getElementById('editSubImage'); f.value = ''; resetPicker(f);
        }
        function submitEditSubImage() {
            const id = document.getElementById('editSubId').value;
            const file = document.getElementById('editSubImage').files[0];
            if (!file) return toast('Please select an image.');

            const formData = new FormData();
            formData.append('id', id);
            formData.append('image', file);
            postForm(BASE + '/ps-backendupdate-subcategory-image', formData, 'btnEditSubImage');
        }

        // ===== Confirm modal =====
        let confirmAction = null;
        function askConfirm(title, text, action) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmText').textContent = text;
            confirmAction = action;
            openModal('confirmModal');
        }
        function closeConfirm() { hideModal('confirmModal'); confirmAction = null; setBusy('confirmOk', false); }
        document.querySelectorAll('[data-confirm-cancel]').forEach(el => el.addEventListener('click', closeConfirm));
        document.getElementById('confirmOk').addEventListener('click', () => { if (confirmAction) confirmAction(); });

        function postDelete(url, id) {
            setBusy('confirmOk', true, 'Deleting...');
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) { toast('Deleted.', 'success'); setTimeout(() => location.reload(), 500); }
                    else { toast(d.message || 'Failed to delete.'); closeConfirm(); }
                })
                .catch(() => { toast('Network error. Please try again.'); closeConfirm(); });
        }

        // ===== Delete =====
        function deleteCategory(id, name) {
            askConfirm('Delete category?',
                `"${name || 'This category'}" and all of its subcategories will be permanently deleted.`,
                () => postDelete(BASE + '/ps-backenddelete-category', id));
        }

        function deleteSubcategory(id, name) {
            askConfirm('Delete subcategory?',
                `"${name || 'This subcategory'}" will be permanently deleted.`,
                () => postDelete(BASE + '/ps-backenddelete-subcategory', id));
        }

        // ===== Expand / collapse & search =====
        function toggleCat(btn) {
            const card = btn.closest('.nh-cat');
            card.dataset.open = card.dataset.open === '1' ? '0' : '1';
        }
        function setAllCats(open) {
            document.querySelectorAll('.nh-cat').forEach(c => c.dataset.open = open ? '1' : '0');
        }

        const search = document.getElementById('catSearch');
        if (search) {
            search.addEventListener('input', () => {
                const q = search.value.trim().toLowerCase();
                let shown = 0;
                document.querySelectorAll('.nh-cat').forEach(card => {
                    const catHit = card.dataset.name.includes(q);
                    let subHit = false;
                    card.querySelectorAll('.nh-sub').forEach(sub => {
                        const hit = !q || catHit || sub.dataset.name.includes(q);
                        sub.style.display = hit ? '' : 'none';
                        if (q && sub.dataset.name.includes(q)) subHit = true;
                    });
                    const visible = !q || catHit || subHit;
                    card.style.display = visible ? '' : 'none';
                    if (q && subHit) card.dataset.open = '1';
                    if (visible) shown++;
                });
                document.getElementById('catResults').textContent = q
                    ? `${shown} of <?= $totalCats ?> categories`
                    : '<?= $totalCats ?> categories';
                document.getElementById('catNoResults').classList.toggle('hidden', shown > 0);
            });
        }
    </script>

</body>

</html>