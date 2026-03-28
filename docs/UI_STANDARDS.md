# DueDiligence UI Standards

## Knap Standarder

### Primære Action Knapper
Konsistent naming og styling:

```html
<!-- Liste view - Opret ny entitet -->
<button class="btn btn-primary" onclick="ModuleName.openCreate()">
    <?= icon('plus', 20) ?> Opret [Entitet]
</button>

<!-- Eksempler -->
<button class="btn btn-primary" onclick="CustomerModule.openCreate()">
    <?= icon('plus', 20) ?> Opret Kunde
</button>

<button class="btn btn-primary" onclick="ProjectModule.openCreate()">
    <?= icon('plus', 20) ?> Opret Projekt
</button>
```

### Modal Footer Standard
Altid samme rækkefølge og styling:

```html
<div class="modal-footer">
    <!-- Annuller altid til venstre, sekundær styling -->
    <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>

    <!-- Primær action til højre -->
    <button type="submit" class="btn btn-primary">${id ? 'Gem' : 'Opret'}</button>
</div>
```

**VIGTIGT:** Brug `data-modal-close` i stedet for `onclick="Modal.close()"` (håndteres af event delegation)

### Slet Knapper
Altid "btn-danger" styling:

```html
<button class="btn btn-sm btn-danger" onclick="ModuleName.delete(<?= $id ?>)">
    <?= icon('trash-2', 16) ?> Slet
</button>
```

### Søg Knapper
```html
<button type="submit" class="btn btn-secondary">
    <?= icon('search', 16) ?> Søg
</button>
```

## Notifikation Standard

### Fra Template (Server-side)
```php
<?php if ($successMessage): ?>
    <script>notify('<?= esc_js($successMessage) ?>', { type: 'success' });</script>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <script>notify('<?= esc_js($errorMessage) ?>', { type: 'error' });</script>
<?php endif; ?>
```

### Fra JavaScript
```javascript
// Brug notify() helper
notify('Gemt', { type: 'success' });
notify('Der opstod en fejl', { type: 'error' });

// Eller brug NotificationSystem direkte
window.NotificationSystem.success('Gemt');
window.NotificationSystem.error('Fejl');
window.NotificationSystem.warning('Advarsel');
window.NotificationSystem.info('Information');

// Toast alias virker stadig (backward compatibility)
Toast.success('Gemt'); // Fungerer, men brug notify() i stedet
```

## Form Standard

### AJAX Form Submit
Brug `data-ajax-form` attribute for automatisk håndtering:

```html
<form data-ajax-form action="/api.php" method="POST">
    <input type="hidden" name="module" value="customer">
    <input type="hidden" name="action" value="save">

    <div class="form-group">
        <label>Navn</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
        <button type="submit" class="btn btn-primary">Gem</button>
    </div>
</form>
```

Event delegation håndterer automatisk:
- CSRF token
- Submit button loading state
- Success/error notifikationer
- Modal close på success

## CSS Class Standarder

### Knap Classes
- **Primær action**: `btn btn-primary`
- **Sekundær action**: `btn btn-secondary`
- **Danger action**: `btn btn-danger`
- **Small knap**: `btn btn-sm btn-[type]`
- **Icon knap**: `btn-icon` eller `icon-btn`

### Form Classes
- **Input/Select/Textarea**: `form-control`
- **Form group**: `form-group`
- **Checkbox**: `checkbox-label`
- **Error state**: `form-control is-invalid`

### Layout Classes
- **Card**: `card`, `card-header`, `card-body`, `card-footer`
- **Modal**: `modal`, `modal-header`, `modal-body`, `modal-footer`
- **Grid**: `grid grid-cols-2`, `grid grid-cols-3`, `grid grid-cols-4`

### Icon Classes
- Brug helper function: `<?= icon('icon-name', size) ?>`
- Standard sizes: 16, 20, 24, 48

## Konsistens Tjekliste

- [ ] Alle "Opret" knapper bruger samme text
- [ ] Alle modal footers har knapper i samme rækkefølge
- [ ] Alle slet knapper bruger btn-danger
- [ ] Alle notifikationer bruger notify() eller NotificationSystem
- [ ] Alle forms med AJAX bruger data-ajax-form
- [ ] Alle modals bruger data-modal-close i stedet for onclick
- [ ] Alle ikoner bruger icon() helper
