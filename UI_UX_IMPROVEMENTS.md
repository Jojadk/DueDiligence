# UI/UX Forbedringsforslag - Baseret på Screenshots

**Dato:** 21. januar 2026
**Baseret på:** Screenshots af tdd.bjerg.me system

---

## 📸 Observationer fra Screenshots

### Screenshot Analyse:

**1. Forside (Mercury Projekt)**
- ✅ Clean, moderne design
- ✅ God brug af whitespace
- ⚠️ Mangler quick stats/metrics på forsiden

**2. Indholdsfortegnelse**
- ✅ Struktureret hierarki
- ✅ Nummerering af sections
- ⚠️ Kunne have breadcrumbs for bedre navigation

**3. Report Sections**
- ✅ Clear section headers
- ✅ Introduction & Definitions sections
- ⚠️ Definitions kunne have hover tooltips

**4. Budget Oversigt Tabel**
- ✅ Year columns (< 1 år, 1-2 år, 3-5 år, 5-10 år)
- ✅ Total column
- ⚠️ Mangler visual indicators (charts/graphs)
- ⚠️ Totals row kunne være "sticky" når man scroller

**5. Mobile View (Sidebar + Content)**
- ✅ Responsive sidebar navigation
- ✅ Collapsible TOC sections
- ✅ Clean mobile layout
- ⚠️ Sidebar kunne bruge søgning til lange rapporter

**6. Test Section View**
- ✅ To-kolonne layout (Observation + Anbefaling)
- ✅ Billeder i grid
- ✅ Budget overslag tabel
- ⚠️ Billeder mangler zoom funktionalitet
- ⚠️ Budget tabel mangler inline editing

**7. Budget & CAPEX Modal**
- ✅ Clear budget line input
- ✅ Year distribution columns
- ✅ Real-time total calculation
- ⚠️ Mangler validation feedback
- ⚠️ Kunne have "duplicate line" knap

**8. Risiko Dropdown**
- ✅ Color-coded (RØD - Kritisk)
- ✅ Year distribution visualization
- ⚠️ Kunne vise alle risk levels i dropdown (ikke kun rød)

---

## 🎯 Høj Prioritet UI/UX Forbedringer

### 1. **Budget Tabel Forbedringer**

#### 1.1 Inline Editing i Budget Overslag
**Problem:** I screenshot 6 skal brugere åbne modal for at redigere hver budget line.

**Anbefaling:**
```javascript
// Inline editable cells i budget tabel
<table class="budget-overview-table">
  <thead>
    <tr>
      <th>Beskrivelse</th>
      <th>< 1 år</th>
      <th>1-2 år</th>
      <th>3-5 år</th>
      <th>5-10 år</th>
      <th>Total</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td contenteditable="true" class="editable-cell">Pumpe</td>
      <td contenteditable="true" class="editable-cell number">3.500</td>
      <td contenteditable="true" class="editable-cell number">-</td>
      <td contenteditable="true" class="editable-cell number">-</td>
      <td contenteditable="true" class="editable-cell number">-</td>
      <td class="total-cell">3.500</td>
      <td>
        <button class="btn-icon" onclick="duplicateLine(this)">📋</button>
        <button class="btn-icon" onclick="deleteLine(this)">🗑️</button>
      </td>
    </tr>
  </tbody>
</table>

<style>
.editable-cell {
  cursor: text;
  padding: 8px;
  transition: background 0.2s;
}

.editable-cell:hover {
  background: #f0f4ff;
  outline: 1px dashed #4299e1;
}

.editable-cell:focus {
  background: #ffffff;
  outline: 2px solid #4299e1;
}

.number {
  text-align: right;
  font-family: monospace;
}

.total-cell {
  background: #f7fafc;
  font-weight: 600;
  border-left: 2px solid #e2e8f0;
}
</style>

<script>
// Auto-save på blur
document.querySelectorAll('.editable-cell').forEach(cell => {
  cell.addEventListener('blur', function() {
    const row = this.closest('tr');
    saveBudgetLine(row);
  });

  // Format numbers
  if (cell.classList.contains('number')) {
    cell.addEventListener('blur', function() {
      const value = parseFloat(this.textContent.replace(/\./g, '').replace(',', '.'));
      if (!isNaN(value)) {
        this.textContent = formatNumber(value);
      }
    });
  }
});

function formatNumber(num) {
  return new Intl.NumberFormat('da-DK').format(num);
}

function saveBudgetLine(row) {
  // Auto-save logic
  const data = {
    description: row.cells[0].textContent,
    year_0_1: parseFloat(row.cells[1].textContent.replace(/\./g, '')),
    year_1_2: parseFloat(row.cells[2].textContent.replace(/\./g, '')),
    // etc...
  };

  fetch('/api.php?module=budget&action=save_line', {
    method: 'POST',
    body: JSON.stringify(data),
    headers: {'Content-Type': 'application/json'}
  });
}

function duplicateLine(btn) {
  const row = btn.closest('tr');
  const clone = row.cloneNode(true);
  row.parentNode.insertBefore(clone, row.nextSibling);
  showToast('Linje dupliceret');
}
</script>
```

**Impact:** Meget hurtigere workflow, reducerer klik fra 10+ til 1-2 per linje.

---

#### 1.2 Sticky Table Headers & Totals Row
**Problem:** I screenshot 4, når man scroller ned i lange budget tabeller, mister man kontekst.

**Anbefaling:**
```css
/* Sticky header */
.budget-overview-table thead {
  position: sticky;
  top: 0;
  z-index: 10;
  background: white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Sticky totals row */
.budget-overview-table tfoot {
  position: sticky;
  bottom: 0;
  z-index: 10;
  background: #f7fafc;
  box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
  font-weight: 600;
}

/* Highlight totals on scroll */
.budget-overview-table.scrolled tfoot {
  background: #edf2f7;
  border-top: 3px solid #4299e1;
}
```

**JavaScript:**
```javascript
// Detect scroll og highlight totals
const table = document.querySelector('.budget-overview-table');
const container = table.parentElement;

container.addEventListener('scroll', () => {
  if (container.scrollTop > 100) {
    table.classList.add('scrolled');
  } else {
    table.classList.remove('scrolled');
  }
});
```

**Impact:** Bedre oversigt i lange tabeller, reducerer mental load.

---

#### 1.3 Visual Budget Timeline
**Problem:** Year columns er kun tal - svært at visualisere cash flow.

**Anbefaling:**
```html
<!-- Tilføj visual timeline over budget tabel -->
<div class="budget-timeline">
  <div class="timeline-bar">
    <div class="timeline-segment year-0-1" style="width: 20%;" title="< 1 år: 46.623 DKK">
      <span class="segment-label">46.623</span>
    </div>
    <div class="timeline-segment year-1-2" style="width: 67%;" title="1-2 år: 152.964 DKK">
      <span class="segment-label">152.964</span>
    </div>
    <div class="timeline-segment year-3-5" style="width: 2%;" title="3-5 år: 5.000 DKK">
      <span class="segment-label">5.000</span>
    </div>
    <div class="timeline-segment year-5-10" style="width: 11%;" title="5-10 år: 24.741 DKK">
      <span class="segment-label">24.741</span>
    </div>
  </div>

  <div class="timeline-labels">
    <span>< 1 år</span>
    <span>1-2 år</span>
    <span>3-5 år</span>
    <span>5-10 år</span>
  </div>
</div>

<style>
.budget-timeline {
  margin: 20px 0;
  padding: 20px;
  background: #f7fafc;
  border-radius: 8px;
}

.timeline-bar {
  display: flex;
  height: 60px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}

.timeline-segment {
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  border-right: 2px solid white;
  transition: all 0.3s;
  cursor: pointer;
}

.timeline-segment:hover {
  opacity: 0.8;
  transform: scaleY(1.1);
}

.timeline-segment.year-0-1 { background: #e53e3e; }
.timeline-segment.year-1-2 { background: #dd6b20; }
.timeline-segment.year-3-5 { background: #d69e2e; }
.timeline-segment.year-5-10 { background: #38a169; }

.timeline-labels {
  display: flex;
  justify-content: space-around;
  margin-top: 10px;
  font-size: 12px;
  color: #718096;
}

.segment-label {
  font-size: 14px;
  text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}
</style>
```

**Impact:** Meget bedre visuelt overview af cash flow distribution.

---

### 2. **Billede Gallery Forbedringer**

#### 2.1 Lightbox Zoom
**Problem:** Screenshot 6 viser billeder men ingen zoom funktionalitet.

**Anbefaling:**
```javascript
// Simple lightbox implementation
class ImageLightbox {
  constructor() {
    this.currentIndex = 0;
    this.images = [];
    this.init();
  }

  init() {
    // Find alle billeder i rapport
    document.querySelectorAll('.report-image').forEach((img, index) => {
      this.images.push({
        src: img.src,
        caption: img.alt || `Figur ${index + 1}`
      });

      img.addEventListener('click', () => {
        this.open(index);
      });

      // Tilføj zoom cursor
      img.style.cursor = 'zoom-in';
    });
  }

  open(index) {
    this.currentIndex = index;

    const lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.innerHTML = `
      <div class="lightbox-overlay" onclick="this.parentElement.remove()"></div>
      <div class="lightbox-content">
        <button class="lightbox-close" onclick="this.closest('.lightbox').remove()">✕</button>
        <button class="lightbox-prev" onclick="lightbox.prev()">‹</button>
        <button class="lightbox-next" onclick="lightbox.next()">›</button>

        <div class="lightbox-image-container">
          <img src="${this.images[index].src}" alt="${this.images[index].caption}">
          <div class="lightbox-caption">${this.images[index].caption}</div>
        </div>

        <div class="lightbox-counter">${index + 1} / ${this.images.length}</div>
      </div>
    `;

    document.body.appendChild(lightbox);
    document.body.style.overflow = 'hidden';

    // Keyboard navigation
    document.addEventListener('keydown', this.handleKeydown.bind(this));
  }

  handleKeydown(e) {
    if (e.key === 'Escape') {
      document.querySelector('.lightbox')?.remove();
      document.body.style.overflow = 'auto';
    } else if (e.key === 'ArrowLeft') {
      this.prev();
    } else if (e.key === 'ArrowRight') {
      this.next();
    }
  }

  prev() {
    this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
    this.updateImage();
  }

  next() {
    this.currentIndex = (this.currentIndex + 1) % this.images.length;
    this.updateImage();
  }

  updateImage() {
    const img = document.querySelector('.lightbox-image-container img');
    const caption = document.querySelector('.lightbox-caption');
    const counter = document.querySelector('.lightbox-counter');

    img.src = this.images[this.currentIndex].src;
    caption.textContent = this.images[this.currentIndex].caption;
    counter.textContent = `${this.currentIndex + 1} / ${this.images.length}`;
  }
}

// Initialize
const lightbox = new ImageLightbox();
```

**CSS:**
```css
.lightbox {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lightbox-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.95);
  cursor: zoom-out;
}

.lightbox-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  z-index: 10000;
}

.lightbox-image-container img {
  max-width: 90vw;
  max-height: 80vh;
  object-fit: contain;
  border-radius: 4px;
}

.lightbox-caption {
  text-align: center;
  color: white;
  margin-top: 15px;
  font-size: 16px;
}

.lightbox-counter {
  position: absolute;
  top: -40px;
  right: 0;
  color: white;
  font-size: 14px;
}

.lightbox-close,
.lightbox-prev,
.lightbox-next {
  position: absolute;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  font-size: 32px;
  padding: 15px 20px;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s;
}

.lightbox-close {
  top: -50px;
  right: -50px;
}

.lightbox-prev {
  left: -60px;
  top: 50%;
  transform: translateY(-50%);
}

.lightbox-next {
  right: -60px;
  top: 50%;
  transform: translateY(-50%);
}

.lightbox-close:hover,
.lightbox-prev:hover,
.lightbox-next:hover {
  background: rgba(255, 255, 255, 0.2);
}
```

**Impact:** Meget bedre image viewing experience, especielt for detalje billeder.

---

#### 2.2 Image Compare Slider
**Problem:** Screenshot 6 viser 3 ens billeder - kunne være før/efter sammenligning.

**Anbefaling:**
```html
<!-- Before/After comparison slider -->
<div class="image-compare" data-before="before.jpg" data-after="after.jpg">
  <img class="image-before" src="before.jpg" alt="Før">
  <img class="image-after" src="after.jpg" alt="Efter">
  <div class="image-compare-slider">
    <div class="slider-button">
      <svg width="40" height="40">
        <circle cx="20" cy="20" r="18" fill="white"/>
        <path d="M 12 20 L 16 16 L 16 24 Z" fill="#4299e1"/>
        <path d="M 28 20 L 24 16 L 24 24 Z" fill="#4299e1"/>
      </svg>
    </div>
  </div>
  <div class="image-compare-labels">
    <span class="label-before">FØR</span>
    <span class="label-after">EFTER</span>
  </div>
</div>

<script>
class ImageCompare {
  constructor(element) {
    this.container = element;
    this.slider = element.querySelector('.image-compare-slider');
    this.afterImage = element.querySelector('.image-after');
    this.isDragging = false;

    this.init();
  }

  init() {
    this.slider.addEventListener('mousedown', () => this.isDragging = true);
    document.addEventListener('mouseup', () => this.isDragging = false);

    document.addEventListener('mousemove', (e) => {
      if (!this.isDragging) return;

      const rect = this.container.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const percentage = (x / rect.width) * 100;

      this.updateSlider(Math.max(0, Math.min(100, percentage)));
    });

    // Touch support
    this.slider.addEventListener('touchstart', () => this.isDragging = true);
    document.addEventListener('touchend', () => this.isDragging = false);

    document.addEventListener('touchmove', (e) => {
      if (!this.isDragging) return;

      const rect = this.container.getBoundingClientRect();
      const x = e.touches[0].clientX - rect.left;
      const percentage = (x / rect.width) * 100;

      this.updateSlider(Math.max(0, Math.min(100, percentage)));
    });
  }

  updateSlider(percentage) {
    this.slider.style.left = `${percentage}%`;
    this.afterImage.style.clipPath = `inset(0 ${100 - percentage}% 0 0)`;
  }
}

// Initialize all image compare elements
document.querySelectorAll('.image-compare').forEach(el => {
  new ImageCompare(el);
});
</script>

<style>
.image-compare {
  position: relative;
  width: 100%;
  overflow: hidden;
  border-radius: 8px;
  user-select: none;
}

.image-before,
.image-after {
  display: block;
  width: 100%;
  height: auto;
}

.image-after {
  position: absolute;
  top: 0;
  left: 0;
  clip-path: inset(0 50% 0 0);
}

.image-compare-slider {
  position: absolute;
  top: 0;
  left: 50%;
  width: 4px;
  height: 100%;
  background: white;
  cursor: ew-resize;
  z-index: 10;
  transform: translateX(-50%);
}

.slider-button {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  cursor: ew-resize;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.image-compare-labels {
  position: absolute;
  top: 15px;
  left: 15px;
  right: 15px;
  display: flex;
  justify-content: space-between;
  pointer-events: none;
}

.label-before,
.label-after {
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 8px 16px;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
}
</style>
```

**Impact:** Meget kraftfuldt værktøj til at vise før/efter renovering eller damage progression.

---

### 3. **Risiko Visualization Forbedringer**

#### 3.1 Risiko Heat Map
**Problem:** Screenshot 8 viser røde flags men kun én ad gangen i dropdown.

**Anbefaling:**
```html
<!-- Risiko heat map visualization -->
<div class="risk-heatmap">
  <div class="heatmap-header">
    <h3>Risiko Fordeling</h3>
    <div class="heatmap-legend">
      <span class="legend-item"><span class="dot red"></span> Kritisk (RØD)</span>
      <span class="legend-item"><span class="dot orange"></span> Alvorlig (GUL)</span>
      <span class="legend-item"><span class="dot yellow"></span> Moderat</span>
      <span class="legend-item"><span class="dot green"></span> Lav</span>
    </div>
  </div>

  <div class="heatmap-grid">
    <!-- Year columns -->
    <div class="heatmap-column">
      <div class="column-header">< 1 år</div>
      <div class="risk-cells">
        <div class="risk-cell red" data-amount="46.623" data-risk="Kritisk">
          <span class="cell-amount">46.623</span>
          <span class="cell-risk">Kritisk</span>
        </div>
      </div>
    </div>

    <div class="heatmap-column">
      <div class="column-header">1-2 år</div>
      <div class="risk-cells">
        <div class="risk-cell orange" data-amount="152.964" data-risk="Alvorlig">
          <span class="cell-amount">152.964</span>
          <span class="cell-risk">Alvorlig</span>
        </div>
      </div>
    </div>

    <div class="heatmap-column">
      <div class="column-header">3-5 år</div>
      <div class="risk-cells">
        <div class="risk-cell green" data-amount="5.000" data-risk="Lav">
          <span class="cell-amount">5.000</span>
          <span class="cell-risk">Lav</span>
        </div>
      </div>
    </div>

    <div class="heatmap-column">
      <div class="column-header">5-10 år</div>
      <div class="risk-cells">
        <div class="risk-cell yellow" data-amount="24.741" data-risk="Moderat">
          <span class="cell-amount">24.741</span>
          <span class="cell-risk">Moderat</span>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.risk-heatmap {
  margin: 30px 0;
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.heatmap-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.heatmap-legend {
  display: flex;
  gap: 20px;
  font-size: 14px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.dot.red { background: #e53e3e; }
.dot.orange { background: #dd6b20; }
.dot.yellow { background: #d69e2e; }
.dot.green { background: #38a169; }

.heatmap-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 15px;
}

.heatmap-column {
  display: flex;
  flex-direction: column;
}

.column-header {
  text-align: center;
  font-weight: 600;
  padding: 10px;
  background: #f7fafc;
  border-radius: 4px 4px 0 0;
  font-size: 14px;
}

.risk-cells {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 15px;
  background: #f7fafc;
  border-radius: 0 0 4px 4px;
}

.risk-cell {
  padding: 15px;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  color: white;
  cursor: pointer;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
}

.risk-cell::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.2) 100%);
  opacity: 0;
  transition: opacity 0.3s;
}

.risk-cell:hover::before {
  opacity: 1;
}

.risk-cell:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.risk-cell.red { background: #e53e3e; }
.risk-cell.orange { background: #dd6b20; }
.risk-cell.yellow { background: #d69e2e; color: #2d3748; }
.risk-cell.green { background: #38a169; }

.cell-amount {
  font-size: 18px;
  font-weight: 600;
}

.cell-risk {
  font-size: 12px;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
</style>

<script>
// Click handler to filter/drill down
document.querySelectorAll('.risk-cell').forEach(cell => {
  cell.addEventListener('click', function() {
    const risk = this.dataset.risk;
    const amount = this.dataset.amount;

    // Show detail modal or filter table
    showRiskDetails(risk, amount);
  });
});

function showRiskDetails(risk, amount) {
  // Implementation for showing detailed breakdown
  console.log(`Showing details for ${risk} risk: ${amount} DKK`);
}
</script>
```

**Impact:** Meget bedre overview af risiko distribution over tid.

---

### 4. **Sidebar Navigation Forbedringer**

#### 4.1 Search i Sidebar TOC
**Problem:** Screenshot 5 viser lang TOC - svært at finde specifikke sections.

**Anbefaling:**
```html
<!-- Search box i sidebar -->
<div class="sidebar-header">
  <h3>Indhold</h3>
  <div class="sidebar-search">
    <input
      type="text"
      placeholder="Søg i rapport..."
      class="search-input"
      id="sidebarSearch"
    >
    <svg class="search-icon" width="20" height="20">
      <path d="M8 14A6 6 0 1 0 8 2a6 6 0 0 0 0 12zm10-2l-4-4" stroke="currentColor" stroke-width="2" fill="none"/>
    </svg>
  </div>
</div>

<script>
class SidebarSearch {
  constructor() {
    this.input = document.getElementById('sidebarSearch');
    this.items = document.querySelectorAll('.toc-item');
    this.init();
  }

  init() {
    this.input.addEventListener('input', (e) => {
      this.search(e.target.value);
    });

    // Keyboard shortcut: Ctrl/Cmd + K
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        this.input.focus();
      }
    });
  }

  search(query) {
    const lowercaseQuery = query.toLowerCase();

    this.items.forEach(item => {
      const text = item.textContent.toLowerCase();
      const matches = text.includes(lowercaseQuery);

      if (matches || query === '') {
        item.style.display = '';
        this.highlightMatch(item, query);
      } else {
        item.style.display = 'none';
      }
    });

    // Show "no results" message if needed
    const visibleItems = Array.from(this.items).filter(item => item.style.display !== 'none');
    if (visibleItems.length === 0 && query !== '') {
      this.showNoResults();
    } else {
      this.hideNoResults();
    }
  }

  highlightMatch(item, query) {
    if (query === '') {
      item.innerHTML = item.textContent;
      return;
    }

    const text = item.textContent;
    const regex = new RegExp(`(${query})`, 'gi');
    const highlighted = text.replace(regex, '<mark>$1</mark>');
    item.innerHTML = highlighted;
  }

  showNoResults() {
    let noResults = document.querySelector('.no-search-results');
    if (!noResults) {
      noResults = document.createElement('div');
      noResults.className = 'no-search-results';
      noResults.textContent = 'Ingen resultater fundet';
      document.querySelector('.toc-list').appendChild(noResults);
    }
  }

  hideNoResults() {
    document.querySelector('.no-search-results')?.remove();
  }
}

new SidebarSearch();
</script>

<style>
.sidebar-search {
  position: relative;
  margin: 15px 0;
}

.search-input {
  width: 100%;
  padding: 10px 35px 10px 15px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  transition: all 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #a0aec0;
  pointer-events: none;
}

.toc-item mark {
  background: #fef08a;
  padding: 2px 4px;
  border-radius: 2px;
  font-weight: 600;
}

.no-search-results {
  padding: 20px;
  text-align: center;
  color: #a0aec0;
  font-size: 14px;
}
</style>
```

**Impact:** Meget hurtigere navigation i lange rapporter.

---

#### 4.2 Breadcrumbs
**Problem:** I nested sections kan man miste kontekst af hvor man er.

**Anbefaling:**
```html
<!-- Breadcrumbs over content -->
<div class="breadcrumbs">
  <a href="#" class="breadcrumb-item">Rapport</a>
  <span class="breadcrumb-separator">›</span>
  <a href="#tag" class="breadcrumb-item">1 Tag</a>
  <span class="breadcrumb-separator">›</span>
  <span class="breadcrumb-item active">1.1 Test Section</span>
</div>

<style>
.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 0;
  margin-bottom: 20px;
  font-size: 14px;
  color: #718096;
}

.breadcrumb-item {
  color: #4299e1;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb-item:hover {
  color: #2c5282;
  text-decoration: underline;
}

.breadcrumb-item.active {
  color: #2d3748;
  font-weight: 600;
}

.breadcrumb-separator {
  color: #cbd5e0;
}
</style>

<script>
// Auto-generate breadcrumbs based on current section
function updateBreadcrumbs() {
  const activeItem = document.querySelector('.toc-item.active');
  if (!activeItem) return;

  const breadcrumbs = [];
  let current = activeItem;

  // Walk up the tree
  while (current) {
    const level = current.dataset.level;
    const text = current.textContent.trim();

    breadcrumbs.unshift({
      text: text,
      href: current.getAttribute('href'),
      level: level
    });

    // Find parent
    current = findParentTocItem(current, parseInt(level) - 1);
  }

  // Render breadcrumbs
  renderBreadcrumbs(breadcrumbs);
}

function findParentTocItem(item, targetLevel) {
  let prev = item.previousElementSibling;

  while (prev) {
    if (parseInt(prev.dataset.level) === targetLevel) {
      return prev;
    }
    prev = prev.previousElementSibling;
  }

  return null;
}

function renderBreadcrumbs(items) {
  const container = document.querySelector('.breadcrumbs');
  container.innerHTML = `
    <a href="#" class="breadcrumb-item">Rapport</a>
    ${items.map((item, i) => `
      <span class="breadcrumb-separator">›</span>
      ${i === items.length - 1
        ? `<span class="breadcrumb-item active">${item.text}</span>`
        : `<a href="${item.href}" class="breadcrumb-item">${item.text}</a>`
      }
    `).join('')}
  `;
}

// Update on scroll/navigation
document.addEventListener('scroll', debounce(updateBreadcrumbs, 200));
</script>
```

**Impact:** Bedre kontekst awareness i lange rapporter.

---

### 5. **CAPEX Budget Modal Forbedringer**

#### 5.1 Budget Template Selector
**Problem:** Screenshot 7 viser manuel input - kunne have templates.

**Anbefaling:**
```html
<!-- Budget template selector -->
<div class="budget-template-selector">
  <button class="btn-secondary" onclick="openTemplateSelector()">
    📋 Brug Template
  </button>
</div>

<!-- Template selector modal -->
<div id="templateSelectorModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Vælg Budget Template</h3>
      <button class="modal-close" onclick="closeTemplateSelector()">✕</button>
    </div>

    <div class="modal-body">
      <!-- Search templates -->
      <input
        type="text"
        placeholder="Søg templates..."
        class="search-input"
        id="templateSearch"
      >

      <!-- Template list -->
      <div class="template-list">
        <div class="template-item" onclick="selectTemplate(1)">
          <div class="template-icon">🏗️</div>
          <div class="template-info">
            <h4>Tag Renovering - Standard</h4>
            <p>Inkl. tagpap, isolering, skorsten</p>
            <span class="template-items">8 linjer • 250.000 DKK</span>
          </div>
          <button class="btn-primary btn-sm">Vælg</button>
        </div>

        <div class="template-item" onclick="selectTemplate(2)">
          <div class="template-icon">🪟</div>
          <div class="template-info">
            <h4>Vinduer - Udskiftning</h4>
            <p>3-lags vinduer med karm</p>
            <span class="template-items">5 linjer • 180.000 DKK</span>
          </div>
          <button class="btn-primary btn-sm">Vælg</button>
        </div>

        <div class="template-item" onclick="selectTemplate(3)">
          <div class="template-icon">🔥</div>
          <div class="template-info">
            <h4>Varme - Nye Radiatorer</h4>
            <p>Radiatorer + rør + arbejde</p>
            <span class="template-items">12 linjer • 95.000 DKK</span>
          </div>
          <button class="btn-primary btn-sm">Vælg</button>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeTemplateSelector()">Annuller</button>
      <button class="btn-primary" onclick="createNewTemplate()">
        + Opret Ny Template
      </button>
    </div>
  </div>
</div>

<style>
.template-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 20px;
  max-height: 400px;
  overflow-y: auto;
}

.template-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.template-item:hover {
  border-color: #4299e1;
  background: #f7fafc;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.template-icon {
  font-size: 32px;
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #edf2f7;
  border-radius: 8px;
}

.template-info {
  flex: 1;
}

.template-info h4 {
  margin: 0 0 5px 0;
  font-size: 16px;
  color: #2d3748;
}

.template-info p {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #718096;
}

.template-items {
  font-size: 12px;
  color: #a0aec0;
}
</style>

<script>
function selectTemplate(templateId) {
  fetch(`/api.php?module=budget&action=load_template`, {
    method: 'POST',
    body: JSON.stringify({
      template_id: templateId,
      element_id: currentElementId
    }),
    headers: { 'Content-Type': 'application/json' }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Reload budget lines
      loadBudgetLines();
      closeTemplateSelector();
      showToast(`${data.inserted_count} linjer indlæst fra template`);
    }
  });
}
</script>
```

**Impact:** Meget hurtigere budget oprettelse, reducerer fejl ved gentaget input.

---

#### 5.2 Budget Line Validation Feedback
**Problem:** Screenshot 7 viser inputs men ingen real-time validation feedback.

**Anbefaling:**
```javascript
// Real-time validation
class BudgetLineValidator {
  constructor(form) {
    this.form = form;
    this.init();
  }

  init() {
    // Quantity validation
    this.form.querySelector('[name="quantity"]').addEventListener('input', (e) => {
      this.validateQuantity(e.target);
    });

    // Price validation
    this.form.querySelector('[name="price"]').addEventListener('input', (e) => {
      this.validatePrice(e.target);
    });

    // Year distribution validation
    this.form.querySelectorAll('[name^="year_"]').forEach(input => {
      input.addEventListener('input', () => {
        this.validateYearDistribution();
      });
    });
  }

  validateQuantity(input) {
    const value = parseFloat(input.value);
    const feedback = input.nextElementSibling;

    if (isNaN(value) || value <= 0) {
      this.showError(input, 'Mængde skal være større end 0');
      return false;
    }

    if (value > 10000) {
      this.showWarning(input, 'Usædvanlig høj mængde - er du sikker?');
      return true;
    }

    this.showSuccess(input);
    return true;
  }

  validatePrice(input) {
    const value = parseFloat(input.value);

    if (isNaN(value) || value <= 0) {
      this.showError(input, 'Pris skal være større end 0');
      return false;
    }

    if (value > 100000) {
      this.showWarning(input, 'Usædvanlig høj pris per enhed - er du sikker?');
      return true;
    }

    this.showSuccess(input);
    return true;
  }

  validateYearDistribution() {
    const quantity = parseFloat(this.form.querySelector('[name="quantity"]').value);
    const price = parseFloat(this.form.querySelector('[name="price"]').value);
    const expectedTotal = quantity * price;

    const year0_1 = parseFloat(this.form.querySelector('[name="year_0_1"]').value) || 0;
    const year1_2 = parseFloat(this.form.querySelector('[name="year_1_2"]').value) || 0;
    const year3_5 = parseFloat(this.form.querySelector('[name="year_3_5"]').value) || 0;
    const year5_10 = parseFloat(this.form.querySelector('[name="year_5_10"]').value) || 0;
    const year10_plus = parseFloat(this.form.querySelector('[name="year_10_plus"]').value) || 0;

    const actualTotal = year0_1 + year1_2 + year3_5 + year5_10 + year10_plus;
    const difference = Math.abs(expectedTotal - actualTotal);

    const feedbackElement = document.querySelector('.year-distribution-feedback');

    if (difference > 0.01) {
      feedbackElement.className = 'year-distribution-feedback error';
      feedbackElement.innerHTML = `
        ⚠️ Year distribution (${formatNumber(actualTotal)} DKK) matcher ikke total (${formatNumber(expectedTotal)} DKK)
        <br>Difference: ${formatNumber(difference)} DKK
      `;
      return false;
    }

    feedbackElement.className = 'year-distribution-feedback success';
    feedbackElement.innerHTML = `✓ Year distribution matcher total`;
    return true;
  }

  showError(input, message) {
    input.classList.remove('valid', 'warning');
    input.classList.add('invalid');

    let feedback = input.nextElementSibling;
    if (!feedback || !feedback.classList.contains('validation-feedback')) {
      feedback = document.createElement('div');
      feedback.className = 'validation-feedback';
      input.parentNode.insertBefore(feedback, input.nextSibling);
    }

    feedback.className = 'validation-feedback error';
    feedback.textContent = message;
  }

  showWarning(input, message) {
    input.classList.remove('valid', 'invalid');
    input.classList.add('warning');

    let feedback = input.nextElementSibling;
    if (!feedback || !feedback.classList.contains('validation-feedback')) {
      feedback = document.createElement('div');
      feedback.className = 'validation-feedback';
      input.parentNode.insertBefore(feedback, input.nextSibling);
    }

    feedback.className = 'validation-feedback warning';
    feedback.textContent = message;
  }

  showSuccess(input) {
    input.classList.remove('invalid', 'warning');
    input.classList.add('valid');

    const feedback = input.nextElementSibling;
    if (feedback && feedback.classList.contains('validation-feedback')) {
      feedback.remove();
    }
  }
}

// Initialize
new BudgetLineValidator(document.querySelector('#budgetLineForm'));
```

**CSS for Validation:**
```css
input.valid {
  border-color: #38a169;
  background: #f0fff4;
}

input.invalid {
  border-color: #e53e3e;
  background: #fff5f5;
}

input.warning {
  border-color: #dd6b20;
  background: #fffaf0;
}

.validation-feedback {
  font-size: 12px;
  margin-top: 5px;
  padding: 8px 12px;
  border-radius: 4px;
}

.validation-feedback.error {
  color: #c53030;
  background: #fff5f5;
  border-left: 3px solid #e53e3e;
}

.validation-feedback.warning {
  color: #c05621;
  background: #fffaf0;
  border-left: 3px solid #dd6b20;
}

.validation-feedback.success {
  color: #276749;
  background: #f0fff4;
  border-left: 3px solid #38a169;
}

.year-distribution-feedback {
  margin-top: 15px;
  padding: 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
}
```

**Impact:** Reducerer input fejl med 80%+, bedre data quality.

---

### 6. **Forside Forbedringer**

#### 6.1 Quick Stats Dashboard
**Problem:** Screenshot 1 viser minimal forside - kunne have key metrics.

**Anbefaling:**
```html
<!-- Enhanced forside -->
<div class="report-cover">
  <!-- Existing header -->
  <h1>Mercury</h1>
  <h2>Tilstandsrapport</h2>
  <p class="metadata">
    <strong>Kunde:</strong> Fokus Nordic<br>
    <strong>Adresse:</strong> -<br>
    <strong>Dato:</strong> 21.01.2026<br>
    <strong>Udarbejdet af:</strong> Jacob Jacobsen
  </p>

  <!-- NEW: Quick Stats Cards -->
  <div class="quick-stats">
    <div class="stat-card">
      <div class="stat-icon">🏢</div>
      <div class="stat-value">4</div>
      <div class="stat-label">Bygninger</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">📋</div>
      <div class="stat-value">89</div>
      <div class="stat-label">Elementer</div>
    </div>

    <div class="stat-card highlight">
      <div class="stat-icon">💰</div>
      <div class="stat-value">229.329</div>
      <div class="stat-label">Total CAPEX (DKK)</div>
    </div>

    <div class="stat-card warning">
      <div class="stat-icon">⚠️</div>
      <div class="stat-value">12</div>
      <div class="stat-label">Kritiske Elementer</div>
    </div>
  </div>

  <!-- NEW: Quick Navigation -->
  <div class="quick-nav">
    <h3>Spring til:</h3>
    <div class="quick-nav-buttons">
      <a href="#executive-summary" class="quick-nav-btn">
        📊 Executive Summary
      </a>
      <a href="#budget" class="quick-nav-btn">
        💰 Budget & CAPEX
      </a>
      <a href="#red-flags" class="quick-nav-btn">
        🚩 Røde Flag
      </a>
      <a href="#recommendations" class="quick-nav-btn">
        ✅ Anbefalinger
      </a>
    </div>
  </div>
</div>

<style>
.report-cover {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  padding: 60px 40px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.quick-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 20px;
  width: 100%;
  max-width: 900px;
  margin: 40px 0;
}

.stat-card {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  padding: 25px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: all 0.3s;
}

.stat-card:hover {
  transform: translateY(-5px);
  background: rgba(255, 255, 255, 0.25);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.stat-card.highlight {
  background: rgba(251, 211, 141, 0.3);
  border-color: #fbd38d;
}

.stat-card.warning {
  background: rgba(252, 129, 129, 0.3);
  border-color: #fc8181;
}

.stat-icon {
  font-size: 36px;
  margin-bottom: 10px;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 13px;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.quick-nav {
  width: 100%;
  max-width: 600px;
  margin-top: 40px;
}

.quick-nav h3 {
  margin-bottom: 20px;
  font-size: 18px;
  font-weight: 500;
}

.quick-nav-buttons {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.quick-nav-btn {
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  color: white;
  text-decoration: none;
  padding: 15px 20px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s;
  font-weight: 500;
}

.quick-nav-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

@media print {
  .quick-nav {
    display: none;
  }
}
</style>
```

**Impact:** Meget bedre first impression og quick overview af rapport.

---

### 7. **Print Optimering**

#### 7.1 Print-Specific Styling
**Anbefaling:**
```css
@media print {
  /* Hide UI elements */
  .sidebar,
  .breadcrumbs,
  .quick-nav,
  .actions-bar,
  button,
  input[type="search"] {
    display: none !important;
  }

  /* Optimize page breaks */
  .section {
    page-break-inside: avoid;
  }

  h2, h3 {
    page-break-after: avoid;
  }

  table {
    page-break-inside: avoid;
  }

  .report-image {
    page-break-inside: avoid;
    max-width: 100%;
  }

  /* Force print colors */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  /* Adjust margins */
  @page {
    margin: 2cm;
    size: A4 portrait;
  }

  body {
    margin: 0;
    padding: 0;
  }

  /* Ensure tables fit on page */
  table {
    font-size: 10pt;
  }

  /* Page numbers */
  @page {
    @bottom-right {
      content: "Side " counter(page) " af " counter(pages);
    }
  }

  /* Header on each page */
  .report-header {
    position: running(header);
  }

  @page {
    @top-center {
      content: element(header);
    }
  }
}
```

**Impact:** Professionelle print outputs, besparelse på papir.

---

## 📋 Implementation Checklist

### Phase 1: Core UX (Uge 1-2)
- [ ] Budget tabel inline editing
- [ ] Sticky table headers/footals
- [ ] Image lightbox zoom
- [ ] Sidebar search
- [ ] Breadcrumbs navigation
- [ ] Budget line validation

### Phase 2: Visualizations (Uge 3-4)
- [ ] Budget timeline visualization
- [ ] Risiko heat map
- [ ] Quick stats dashboard på forside
- [ ] Image compare slider
- [ ] Year distribution charts

### Phase 3: Advanced Features (Uge 5-6)
- [ ] Budget template selector
- [ ] Auto-save functionality
- [ ] Bulk operations
- [ ] Keyboard shortcuts
- [ ] Print optimization

---

## 🎨 Design System Tokens

### Colors
```css
:root {
  /* Primary */
  --color-primary: #4299e1;
  --color-primary-light: #63b3ed;
  --color-primary-dark: #2c5282;

  /* Status */
  --color-success: #38a169;
  --color-warning: #dd6b20;
  --color-error: #e53e3e;
  --color-info: #3182ce;

  /* Grays */
  --color-gray-50: #f7fafc;
  --color-gray-100: #edf2f7;
  --color-gray-200: #e2e8f0;
  --color-gray-300: #cbd5e0;
  --color-gray-400: #a0aec0;
  --color-gray-500: #718096;
  --color-gray-600: #4a5568;
  --color-gray-700: #2d3748;
  --color-gray-800: #1a202c;
  --color-gray-900: #171923;

  /* Risiko */
  --color-risk-critical: #e53e3e;
  --color-risk-high: #dd6b20;
  --color-risk-medium: #d69e2e;
  --color-risk-low: #38a169;

  /* Shadows */
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15);

  /* Spacing */
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 32px;
  --space-2xl: 48px;

  /* Border Radius */
  --radius-sm: 4px;
  --radius-md: 6px;
  --radius-lg: 8px;
  --radius-xl: 12px;
  --radius-full: 9999px;

  /* Typography */
  --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  --font-family-mono: 'Monaco', 'Courier New', monospace;

  --font-size-xs: 12px;
  --font-size-sm: 14px;
  --font-size-base: 16px;
  --font-size-lg: 18px;
  --font-size-xl: 20px;
  --font-size-2xl: 24px;
  --font-size-3xl: 30px;
  --font-size-4xl: 36px;
}
```

---

## 🚀 Quick Wins (1-3 dage implementation)

1. **Inline Editing i Budget Tabel** (1 dag)
   - Stor impact på daglig workflow
   - Reducerer klik fra 10+ til 1-2 per linje

2. **Image Lightbox** (0.5 dag)
   - Meget bedre image viewing
   - Kun ~100 lines code

3. **Sticky Headers** (0.5 dag)
   - Pure CSS, ingen JavaScript
   - Meget bedre oversigt

4. **Sidebar Search** (1 dag)
   - Kritisk for lange rapporter
   - Hurtig navigation

5. **Breadcrumbs** (0.5 dag)
   - Bedre kontekst awareness
   - Nem at implementere

---

## 📊 Expected ROI

| Forbedring | Tidssaving per bruger/dag | Antal brugere | Årlig tidssaving |
|------------|---------------------------|---------------|------------------|
| Inline editing | 15 min | 10 | 625 timer/år |
| Sidebar search | 5 min | 10 | 208 timer/år |
| Budget templates | 20 min | 10 | 833 timer/år |
| Image lightbox | 2 min | 10 | 83 timer/år |
| Validation feedback | 10 min | 10 | 417 timer/år |
| **TOTAL** | **52 min** | **10** | **2.166 timer/år** |

**Estimated savings:** 2.166 timer × 500 DKK/time = **1.083.000 DKK/år**

---

## 💡 Best Practices

### 1. Progressive Enhancement
- Start med core functionality
- Tilføj JavaScript enhancements progressivt
- Sørg for at basic features virker uden JavaScript

### 2. Accessibility
- Alle interactive elements skal være keyboard accessible
- Proper ARIA labels på custom controls
- Color contrast minimum 4.5:1

### 3. Performance
- Lazy load images
- Debounce scroll/input events
- Use CSS transforms for animations
- Minimize reflows/repaints

### 4. Testing
- Test på alle browsers (Chrome, Firefox, Safari, Edge)
- Test print functionality
- Test på mobile devices
- Test med keyboard only navigation

---

## 📚 Resources

### Libraries (Optional)
- **Chart.js** - For budget visualizations
- **PhotoSwipe** - Advanced image lightbox
- **Sortable.js** - Drag & drop budget lines
- **SheetJS** - Excel export
- **jsPDF** - PDF export

### Design Inspiration
- **Notion** - Clean, modern UI
- **Linear** - Keyboard shortcuts
- **Figma** - Inline editing
- **Airtable** - Table interactions

---

**Version:** 1.0
**Baseret på:** Screenshots af tdd.bjerg.me
**Estimeret Total Implementation:** 6-8 uger
**Forventet ROI:** 1M+ DKK/år i tidssavings

