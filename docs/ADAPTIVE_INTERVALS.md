# Adaptive Polling Intervals

## Overview

Systemet bruger intelligente polling intervals der tilpasser sig baseret på brugerens aktivitet for at spare ressourcer og reducere server load.

## Hvordan Det Virker

### 🟢 Aktivt Vindue (Normal Polling)

Når browser vinduet er **aktivt** (fokuseret):
- **Live Updates**: Poller hver 3. sekund
- **Lock Heartbeat**: Sender heartbeat hver 30. sekund

### 🟡 Inaktivt Vindue (Reduceret Polling)

Når browser vinduet er **inaktivt** (i baggrunden):
- **Live Updates**: Poller hver 30. sekund (10x langsommere)
- **Lock Heartbeat**: Sender heartbeat hver 60. sekund (2x langsommere)

### 🔵 Modal Åben (Reduceret Polling)

Når en modal dialog er **åben**:
- **Live Updates**: Poller hver 30. sekund
- **Lock Heartbeat**: Sender heartbeat hver 60. sekund

### ⚡ Instant Resume

Når vinduet bliver aktivt igen eller modal lukker:
- **Øjeblikkelig poll** - Henter opdateringer med det samme
- **Genoptag normal interval** - Går tilbage til hurtig polling

---

## API Reference

### LiveUpdateManager

```javascript
const liveUpdate = new LiveUpdateManager({
    recordType: 'budget_template_items',
    recordId: templateId,
    pollInterval: 3000,         // Aktivt interval (ms)
    pollIntervalInactive: 30000, // Inaktivt interval (ms)
    onUpdate: (changes) => { ... }
});

liveUpdate.start();
```

**Options:**
- `pollInterval` - Polling interval når vinduet er aktivt (default: 3000ms = 3 sek)
- `pollIntervalInactive` - Polling interval når vinduet er inaktivt (default: 30000ms = 30 sek)

### LockManager

```javascript
const lockManager = new LockManager({
    heartbeatInterval: 30000,         // Aktivt interval (ms)
    heartbeatIntervalInactive: 60000, // Inaktivt interval (ms)
});
```

**Options:**
- `heartbeatInterval` - Heartbeat interval når vinduet er aktivt (default: 30000ms = 30 sek)
- `heartbeatIntervalInactive` - Heartbeat interval når vinduet er inaktivt (default: 60000ms = 60 sek)

---

## Modal Integration

### Automatisk Integration

**Bootstrap Modals** - Auto-detekteret:
```html
<!-- Ingen kode nødvendig - virker automatisk -->
<div class="modal" id="myModal">
  ...
</div>
```

**Native Dialog Elements** - Auto-detekteret:
```html
<dialog id="myDialog">
  ...
</dialog>
```

### Manuel Integration

For custom modal systemer, brug ModalManager:

```javascript
// Når modal åbnes
ModalManager.open();

// Når modal lukkes
ModalManager.close();
```

**Eksempel med custom modal:**
```javascript
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    ModalManager.open();
}

function hideModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    ModalManager.close();
}
```

**Nested Modals:**
```javascript
// ModalManager håndterer automatisk nested modals
ModalManager.open();  // Modal 1
ModalManager.open();  // Modal 2
ModalManager.close(); // Modal 2 lukket
ModalManager.close(); // Modal 1 lukket - nu dispatches modalClose event
```

---

## Page Visibility API

Systemet bruger [Page Visibility API](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API) til at detektere vindue status.

**Understøttede Events:**
- `visibilitychange` - Når tab skifter (primær detection)
- `focus` - Når vinduet får fokus (fallback)
- `blur` - Når vinduet mister fokus (fallback)

**Browser Support:**
- Chrome 14+
- Firefox 10+
- Safari 7+
- Edge 12+
- IE 10+

---

## Performance Benefits

### Ressource Besparelse

**Scenario:** 10 brugere med hver 3 åbne tabs

**Uden Adaptive Intervals:**
- Total requests: 30 tabs × 20 req/min = **600 requests/min**
- Server load: Konstant højt

**Med Adaptive Intervals:**
- Active tabs: 10 tabs × 20 req/min = 200 requests/min
- Inactive tabs: 20 tabs × 2 req/min = 40 requests/min
- **Total: 240 requests/min** (60% reduktion!)

### Battery Savings

Mobile/laptop brugere vil opleve:
- Mindre CPU brug i baggrunden
- Længere batterilevetid
- Mindre data forbrug

---

## Debugging

Adaptive intervals logger til console:

```
Window inactive - reducing poll frequency
Window active - restoring poll frequency
Modal opened - reducing heartbeat frequency
Modal closed - restoring heartbeat frequency
```

**Console Commands:**
```javascript
// Check hvis modal er åben
ModalManager.isOpen(); // true/false

// Force reset modal counter
ModalManager.reset();
```

---

## Best Practices

### 1. Include Scripts i Rækkefølge

```html
<!-- Core scripts -->
<script src="/js/lock-manager.js"></script>
<script src="/js/live-update.js"></script>

<!-- Modal manager (optional, hvis du bruger custom modals) -->
<script src="/js/modal-manager.js"></script>
```

### 2. Custom Intervals

For specifikke use cases kan du justere intervals:

```javascript
// Meget kritisk data - poll oftere
const criticalUpdate = new LiveUpdateManager({
    recordType: 'critical_data',
    pollInterval: 1000,         // 1 sekund aktiv
    pollIntervalInactive: 10000 // 10 sekunder inaktiv
});

// Mindre kritisk data - poll sjældnere
const lowPriorityUpdate = new LiveUpdateManager({
    recordType: 'low_priority',
    pollInterval: 10000,        // 10 sekunder aktiv
    pollIntervalInactive: 60000 // 1 minut inaktiv
});
```

### 3. Cleanup

Husk altid at stoppe managers ved navigation:

```javascript
window.addEventListener('beforeunload', () => {
    lockManager.destroy();
    liveUpdate.stop();
});
```

---

## Migration Guide

### Fra Gammel Kode

**Før:**
```javascript
setInterval(() => {
    fetchUpdates();
}, 3000);
```

**Efter:**
```javascript
const liveUpdate = new LiveUpdateManager({
    recordType: 'your_type',
    onUpdate: (changes) => {
        handleUpdates(changes);
    }
});
liveUpdate.start();
```

### Eksisterende Modal Systemer

Tilføj blot disse linjer til din modal kode:

```javascript
// I din showModal funktion
ModalManager.open();

// I din hideModal funktion
ModalManager.close();
```

---

## Troubleshooting

### Problem: Polling stopper ikke når vinduet er inaktivt

**Løsning:** Check at Page Visibility API er understøttet:

```javascript
if (typeof document.hidden !== 'undefined') {
    console.log('Page Visibility API supported');
} else {
    console.warn('Page Visibility API not supported');
}
```

### Problem: Modal events dispatches ikke

**Løsning:** Verificer at ModalManager er loaded:

```javascript
console.log(typeof ModalManager); // Should be "object"
```

Eller manuel dispatch:
```javascript
document.dispatchEvent(new CustomEvent('modalOpen'));
document.dispatchEvent(new CustomEvent('modalClose'));
```

### Problem: Heartbeat timeout på inaktive tabs

**Løsning:** Øg inaktivt interval så det er under 120 sekunder:

```javascript
const lockManager = new LockManager({
    heartbeatIntervalInactive: 60000 // 60 sek < 120 sek timeout
});
```

---

## Technical Details

### Event Flow

```
Window becomes inactive
  ↓
onWindowInactive() called
  ↓
adjustPollInterval() / adjustHeartbeatInterval()
  ↓
Clear current interval
  ↓
Set new interval with slower rate
```

```
Window becomes active
  ↓
onWindowActive() called
  ↓
poll() / sendHeartbeat() immediately
  ↓
adjustPollInterval() / adjustHeartbeatInterval()
  ↓
Clear current interval
  ↓
Set new interval with faster rate
```

### State Machine

```
Window State: [Active, Inactive]
Modal State:  [Open, Closed]

Effective Interval = (Window Active && Modal Closed)
                     ? FastInterval
                     : SlowInterval
```

---

## Further Reading

- [Page Visibility API - MDN](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API)
- [Battery Considerations for Web Apps](https://web.dev/battery/)
- [Efficient Resource Loading](https://web.dev/fast/)
