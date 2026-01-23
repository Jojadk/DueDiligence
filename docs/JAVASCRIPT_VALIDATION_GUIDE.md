# JavaScript Validation Migration Guide

## Oversigt

Dette dokument beskriver hvordan man bruger det nye centrale `Validation` bibliotek til at standardisere client-side validering på tværs af alle JavaScript-filer i applikationen.

## Formål

Det centrale Validation bibliotek (`assets/js/validation.js`) sikrer:

1. **Konsistent validering** - Samme valideringslogik bruges overalt
2. **Reduceret duplikering** - Ingen behov for at skrive samme validering flere gange
3. **Bedre sikkerhed** - Centraliseret HTML escaping og sanitization
4. **Nemmere vedligeholdelse** - Ændringer til validering sker ét sted

## Integration

### Inkluder biblioteket

Sørg for at `validation.js` er inkluderet i dit HTML før andre komponenter:

```html
<script src="/assets/js/validation.js"></script>
<script src="/assets/js/components/your-component.js"></script>
```

## Brug af Validation biblioteket

### 1. Streng validering

**Før:**
```javascript
function validateName(name) {
    if (!name || name.trim().length === 0) {
        return { valid: false, error: 'Navn er påkrævet' };
    }
    if (name.length > 100) {
        return { valid: false, error: 'Navn må maks være 100 tegn' };
    }
    return { valid: true };
}
```

**Efter:**
```javascript
const result = Validation.string(name, {
    required: true,
    maxLength: 100,
    trim: true
});
```

### 2. Email validering

**Før:**
```javascript
function validateEmail(email) {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        return { valid: false, error: 'Ugyldig email' };
    }
    return { valid: true };
}
```

**Efter:**
```javascript
const result = Validation.email(email, true); // true = required
```

### 3. Tal validering

**Før:**
```javascript
function validatePrice(price) {
    const num = parseFloat(price);
    if (isNaN(num)) {
        return { valid: false, error: 'Skal være et tal' };
    }
    if (num < 0) {
        return { valid: false, error: 'Skal være positiv' };
    }
    return { valid: true, value: num };
}
```

**Efter:**
```javascript
const result = Validation.float(price, {
    required: true,
    min: 0,
    decimals: 2
});
// result.sanitized indeholder det parsede tal
```

### 4. Fil validering

**Før (image-upload.js):**
```javascript
handleFileSelect(files) {
    Array.from(files).forEach((file) => {
        if (!file.type.startsWith('image/')) {
            this.showError(`${file.name} er ikke et billede`);
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            this.showError(`${file.name} er for stor (max 10MB)`);
            return;
        }

        // Process file...
    });
}
```

**Efter:**
```javascript
handleFileSelect(files) {
    // Valider alle filer på én gang
    const validation = Validation.files(files, 'image', { maxFiles: 50 });

    if (!validation.valid) {
        this.showError(validation.error);
        return;
    }

    // Process validated files...
}
```

### 5. Form validering

**Før:**
```javascript
function validateForm() {
    const errors = {};

    if (!data.name || data.name.trim().length === 0) {
        errors.name = 'Navn er påkrævet';
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(data.email)) {
        errors.email = 'Ugyldig email';
    }

    const price = parseFloat(data.price);
    if (isNaN(price) || price < 0) {
        errors.price = 'Pris skal være positiv';
    }

    return Object.keys(errors).length === 0 ? null : errors;
}
```

**Efter:**
```javascript
const rules = {
    name: {
        type: 'string',
        options: { required: true, maxLength: 100 }
    },
    email: {
        type: 'email',
        options: { required: true }
    },
    price: {
        type: 'float',
        options: { required: true, min: 0, decimals: 2 }
    }
};

const result = Validation.validateForm(formData, rules);

if (!result.valid) {
    // result.errors indeholder alle fejl
    Validation.showFormErrors(formElement, result.errors);
} else {
    // result.sanitized indeholder de rensede værdier
    submitForm(result.sanitized);
}
```

### 6. HTML Escaping (XSS beskyttelse)

**Før:**
```javascript
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Brug:
element.innerHTML = `<div>${escapeHtml(userInput)}</div>`;
```

**Efter:**
```javascript
element.innerHTML = `<div>${Validation.escapeHtml(userInput)}</div>`;
```

### 7. Fil størrelse formatering

**Før:**
```javascript
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}
```

**Efter:**
```javascript
const size = Validation.formatFileSize(file.size);
```

## Tilgængelige validationsfunktioner

### Basale typer

| Funktion | Formål | Eksempel |
|----------|--------|----------|
| `Validation.string(value, options)` | Validerer strenge | `Validation.string(name, {required: true, maxLength: 100})` |
| `Validation.int(value, options)` | Validerer heltal | `Validation.int(age, {min: 0, max: 150})` |
| `Validation.float(value, options)` | Validerer decimaltal | `Validation.float(price, {min: 0, decimals: 2})` |
| `Validation.email(value, required)` | Validerer email | `Validation.email(email, true)` |
| `Validation.date(value, options)` | Validerer dato | `Validation.date(date, {minDate: '2020-01-01'})` |

### Specialiserede typer

| Funktion | Formål | Eksempel |
|----------|--------|----------|
| `Validation.cvr(value, required)` | Validerer CVR-nummer (8 cifre) | `Validation.cvr('12345678', true)` |
| `Validation.phone(value, required)` | Validerer telefonnummer | `Validation.phone('+45 12345678', false)` |
| `Validation.url(value, required)` | Validerer URL | `Validation.url('https://example.com', false)` |

### Fil validering

| Funktion | Formål | Eksempel |
|----------|--------|----------|
| `Validation.file(file, type, options)` | Validerer enkelt fil | `Validation.file(file, 'image', {maxSize: 5*1024*1024})` |
| `Validation.files(files, type, options)` | Validerer flere filer | `Validation.files(files, 'pdf', {maxFiles: 10})` |

Tilgængelige filtyper:
- `'image'` - JPEG, PNG, WebP, GIF (max 10MB)
- `'pdf'` - PDF dokumenter (max 20MB)
- `'document'` - PDF, Word, Excel (max 50MB)

### Form hjælpere

| Funktion | Formål | Eksempel |
|----------|--------|----------|
| `Validation.validateForm(data, rules)` | Validerer hele form | Se eksempel ovenfor |
| `Validation.showFieldError(field, msg)` | Vis fejl på felt | `Validation.showFieldError(inputEl, 'Ugyldig værdi')` |
| `Validation.clearFieldError(field)` | Fjern fejl fra felt | `Validation.clearFieldError(inputEl)` |
| `Validation.showFormErrors(form, errors)` | Vis alle fejl | `Validation.showFormErrors(formEl, result.errors)` |
| `Validation.clearFormErrors(form)` | Fjern alle fejl | `Validation.clearFormErrors(formEl)` |

### Hjælpefunktioner

| Funktion | Formål | Eksempel |
|----------|--------|----------|
| `Validation.escapeHtml(text)` | Escape HTML (XSS beskyttelse) | `Validation.escapeHtml(userInput)` |
| `Validation.sanitizeHtml(html)` | Fjern farlige script/attributter | `Validation.sanitizeHtml(richText)` |
| `Validation.formatFileSize(bytes)` | Formater filstørrelse | `Validation.formatFileSize(1048576)` → "1 MB" |

## Response format

Alle validationsfunktioner returnerer et objekt med følgende struktur:

```javascript
{
    valid: true/false,        // Om validationen lykkedes
    error: "fejlbesked",      // Fejlbesked hvis valid=false
    sanitized: value          // Renset/parsed værdi hvis valid=true
}
```

## Eksempler fra migrering

### image-upload.js

**Før:**
```javascript
handleFileSelect(files) {
    Array.from(files).forEach((file) => {
        if (!file.type.startsWith('image/')) {
            this.showError(`${file.name} er ikke et billede`);
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            this.showError(`${file.name} er for stor (max 10MB)`);
            return;
        }
        // ... process file
    });
}
```

**Efter:**
```javascript
handleFileSelect(files) {
    const validation = Validation.files(files, 'image', { maxFiles: 50 });
    if (!validation.valid) {
        this.showError(validation.error);
        return;
    }
    // ... process validated files
}
```

### budget-modal.js

**Før:**
```javascript
inputs.forEach(input => {
    let value = input.value;
    if (['quantity', 'price_per_unit'].includes(field)) {
        value = parseFloat(value) || 0;
    }
    this.lines[index][field] = value;
});
```

**Efter:**
```javascript
inputs.forEach(input => {
    const field = input.dataset.field;
    let value = input.value;

    if (['quantity', 'price_per_unit'].includes(field)) {
        const result = Validation.float(value, { min: 0, decimals: 2 });
        value = result.valid ? result.sanitized : 0;
    }

    this.lines[index][field] = value;
});
```

## Best Practices

### 1. Valider altid bruger-input

```javascript
// Dårligt
function handleSubmit(data) {
    API.post('/api.php', data); // Ingen validering!
}

// Godt
function handleSubmit(data) {
    const rules = {
        name: { type: 'string', options: { required: true } },
        email: { type: 'email', options: { required: true } }
    };

    const result = Validation.validateForm(data, rules);
    if (!result.valid) {
        showErrors(result.errors);
        return;
    }

    API.post('/api.php', result.sanitized);
}
```

### 2. Brug altid escapeHtml ved HTML output

```javascript
// Dårligt - XSS sårbarhed
element.innerHTML = `<div>${userInput}</div>`;

// Godt
element.innerHTML = `<div>${Validation.escapeHtml(userInput)}</div>`;
```

### 3. Vis brugervenlige fejlbeskeder

```javascript
const result = Validation.int(age, { min: 18, max: 99 });
if (!result.valid) {
    // result.error indeholder en brugervenlig besked på dansk
    Validation.showFieldError(ageInput, result.error);
}
```

### 4. Brug sanitized værdi

```javascript
const result = Validation.float(priceInput.value, { decimals: 2 });
if (result.valid) {
    // Brug result.sanitized (ikke den originale værdi)
    formData.price = result.sanitized; // Parsed og afrundet
}
```

## Komplementær backend-validering

**VIGTIGT:** Client-side validering skal ALTID suppleres med backend-validering.

JavaScript validation kan omgås, så backend SKAL også validere:

```php
// Backend (PHP)
$validation = api_validate_params([
    'price' => ['float', 'POST', true],
    'email' => ['email', 'POST', true]
]);

if (!$validation['success']) {
    return $validation; // Return error
}

$data = $validation['data']; // Safe to use
```

Client-side validering forbedrer brugeroplevelsen ved at give øjeblikkelig feedback, mens backend-validering sikrer dataintegriteten.

## Migrering af eksisterende kode

### Trin 1: Identificer validering

Find steder hvor du:
- Parser tal (`parseFloat`, `parseInt`)
- Checker længde (`value.length`)
- Bruger regex (`/pattern/.test(value)`)
- Checker filtype eller størrelse
- Escaper HTML

### Trin 2: Erstat med Validation

Erstat med passende `Validation.*` funktion.

### Trin 3: Test

Test grundigt at valideringen virker som forventet.

### Trin 4: Fjern duplikeret kode

Fjern gamle hjælpefunktioner der nu er i Validation biblioteket.

## Support

Ved spørgsmål eller problemer, se:
- `/assets/js/validation.js` - Fuld kildekode og dokumentation
- `/assets/js/components/image-upload.js` - Eksempel på brug i praksis
- `/core/api-helpers.php` - Backend validation der matcher

## Se også

- [PHP Validation Guide](/docs/OPTIMIZATION_MIGRATION_GUIDE.md) - Backend validation med `api_validate_params()`
- [Security Best Practices](/docs/SECURITY.md) - Sikkerhedsvejledning
