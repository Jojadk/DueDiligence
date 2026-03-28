<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 data-i18n="nav.customers">Customers</h2>
        <?php if (\Core\Auth::hasPermission('customer_create')): ?>
            <button id="btn-new-customer" class="btn btn-primary" data-i18n="common.create">New Customer</button>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th data-i18n="common.name">Name</th>
                    <th data-i18n="common.email">Email</th>
                    <th data-i18n="common.phone">Phone</th>
                    <th data-i18n="common.edit">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td data-label="Name">
                            <?= htmlspecialchars($c['name']) ?>
                        </td>
                        <td data-label="Email">
                            <?= htmlspecialchars($c['email']) ?>
                        </td>
                        <td data-label="Phone">
                            <?= htmlspecialchars($c['phone']) ?>
                        </td>
                        <td data-label="Actions">
                            <button class="btn-sm btn-edit-customer" data-customer-id="<?= $c['id'] ?>"
                                data-i18n="common.edit">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Customer Template (Hidden) -->
<div id="customer-template" style="display:none">
    <?php
    $customer = null;
    include 'modules/Customer/CustomerForm.php';
    ?>
</div>

<script>
    App.loadScript('assets/js/modules/customer.js').then(() => {
        if (typeof CustomerModule !== 'undefined') {
            CustomerModule.init();
        }
    });
</script>

</script>