<?php
// Shared Customer Form Template
$customer = $customer ?? null;
$isEdit = !empty($customer['id']);
?>

<div class="card max-w-lg customer-form-container" style="max-width:800px; margin: 0 auto;">
    <div class="card-header">
        <h2>
            <?= $customer ? 'Edit Customer' : 'New Customer' ?>
        </h2>
    </div>
    <div class="card-body" style="padding:10px; height:100%; display:flex; flex-direction:column;">
        <!-- Tabs -->
        <div class="tabs" style="display:flex; margin-bottom:15px; border-bottom:1px solid #ccc;">
            <div class="tab-item active" data-tab-target="tab-general"
                style="padding:8px 15px; cursor:pointer; border-bottom:2px solid #007bff; font-weight:bold;">General
            </div>
            <div class="tab-item" data-tab-target="tab-projects"
                style="padding:8px 15px; cursor:pointer; border-bottom:2px solid transparent;">Projects</div>
        </div>

        <form class="customer-form" id="customer-form"
            style="flex:1; overflow-y:auto; display:flex; flex-direction:column;">
            <input type="hidden" name="ajax" value="1">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $customer['id'] ?>">
            <?php endif; ?>

            <!-- Tab 1: General -->
            <div id="tab-general" class="tab-content" style="display:block;">
                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="common.name">Name</label>
                    <input type="text" name="name" class="form-input w-full"
                        value="<?= \Core\Utility::e($customer['name'] ?? '') ?>"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="common.email">Email</label>
                    <input type="email" name="email" class="form-input w-full"
                        value="<?= \Core\Utility::e($customer['email'] ?? '') ?>"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="common.phone">Phone</label>
                    <input type="text" name="phone" class="form-input w-full"
                        value="<?= \Core\Utility::e($customer['phone'] ?? '') ?>"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="common.address">Address</label>
                    <textarea name="address" class="form-input w-full"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; height:80px;"><?= \Core\Utility::e($customer['address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Tab 2: Projects -->
            <div id="tab-projects" class="tab-content" style="display:none;">
                <?php if (!empty($customer['projects'])): ?>
                    <table class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th align="left" data-i18n="project.name">Project Name</th>
                                <th align="left" data-i18n="project.status">Status</th>
                                <th data-i18n="common.edit">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer['projects'] as $p): ?>
                                <tr>
                                    <td>
                                        <?= \Core\Utility::e($p['name']) ?>
                                    </td>
                                    <td>
                                        <?= ucfirst($p['status']) ?>
                                    </td>
                                    <td><button class="btn-sm btn-edit-project" data-project-id="<?= $p['id'] ?>"
                                            data-i18n="common.open">Open</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No projects linked to this customer.</div>
                <?php endif; ?>
            </div>

            <div class="flex justify-end gap-2 mt-4"
                style="margin-top:auto; padding-top:15px; border-top:1px solid #eee; display:flex; justify-content:space-between;">
                <?php if ($isEdit && \Core\Auth::hasPermission('customer_delete')): ?>
                    <button type="button" class="btn btn-danger" id="btn-delete-customer"
                        data-customer-id="<?= $customer['id'] ?>"
                        data-customer-name="<?= \Core\Utility::e($customer['name']) ?>"
                        style="background:#dc3545; color:white; border:none; padding:8px 12px; border-radius:4px;"
                        data-i18n="common.delete">Delete
                        Customer</button>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary"
                    style="background:#007bff; color:white; border:none; padding:8px 12px; border-radius:4px;"
                    data-i18n="common.save">Save
                    Customer</button>
            </div>
        </form>
    </div>
</div>