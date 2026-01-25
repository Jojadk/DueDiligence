/**
 * User Guide System
 * Provides tooltips, contextual help, and onboarding tours
 *
 * @version 1.0.0
 */

const UserGuide = {
    // Configuration
    config: {
        storageKey: 'duediligence_guide_status',
        tooltipDelay: 500,
        tourDelay: 1000
    },

    // Current tour state
    currentTour: null,
    currentStep: 0,

    // Tour definitions
    tours: {
        'welcome': {
            name: 'Velkommen til DueDiligence',
            steps: [
                {
                    title: 'Velkommen!',
                    content: 'Dette er en hurtig gennemgang af systemet. Du kan altid hoppe over ved at klikke "Luk".',
                    target: null,
                    placement: 'center'
                },
                {
                    title: 'Navigation',
                    content: 'Brug menuen til venstre til at navigere mellem moduler som kunder, projekter og bygninger.',
                    target: '.sidebar',
                    placement: 'right',
                    highlight: true
                },
                {
                    title: 'Hovedindhold',
                    content: 'Det primære indhold vises her. Du kan åbne, redigere og slette elementer direkte.',
                    target: '#module-content',
                    placement: 'top',
                    highlight: true
                },
                {
                    title: 'Hjælp og Support',
                    content: 'Klik på ? ikonet for kontekstuel hjælp på enhver side.',
                    target: '[data-help-trigger]',
                    placement: 'bottom',
                    highlight: true
                },
                {
                    title: 'Kom i gang',
                    content: 'Du er klar! Start med at oprette en kunde eller et projekt.',
                    target: null,
                    placement: 'center'
                }
            ]
        },
        'project-create': {
            name: 'Opret nyt projekt',
            steps: [
                {
                    title: 'Projektoplysninger',
                    content: 'Indtast grundlæggende projektoplysninger. Alle felter markeret med * er påkrævet.',
                    target: '#project-form',
                    placement: 'top'
                },
                {
                    title: 'Vælg kunde',
                    content: 'Søg og vælg en eksisterende kunde, eller opret en ny kunde først.',
                    target: '#customer_id',
                    placement: 'bottom',
                    highlight: true
                },
                {
                    title: 'Projekttype',
                    content: 'Vælg projekttype for at få relevante skabeloner og indstillinger.',
                    target: '[name="project_type"]',
                    placement: 'bottom',
                    highlight: true
                }
            ]
        },
        'report-builder': {
            name: 'Rapport Builder',
            steps: [
                {
                    title: 'Rapport Builder',
                    content: 'Byg professionelle rapporter med live preview og skabeloner.',
                    target: '.report-builder-container',
                    placement: 'top'
                },
                {
                    title: 'Vælg skabelon',
                    content: 'Vælg en skabelon at starte med, eller byg en fra bunden.',
                    target: '.template-selector',
                    placement: 'right',
                    highlight: true
                },
                {
                    title: 'Live Preview',
                    content: 'Se ændringer i real-time mens du redigerer rapporten.',
                    target: '.live-preview',
                    placement: 'left',
                    highlight: true
                }
            ]
        }
    },

    /**
     * Initialize the user guide system
     */
    init() {
        console.log('UserGuide: Initializing...');

        // Initialize tooltips
        this.initTooltips();

        // Initialize help triggers
        this.initHelpTriggers();

        // Check if we should show welcome tour
        this.checkWelcomeTour();

        // Listen for tour triggers
        document.addEventListener('click', (e) => {
            const tourTrigger = e.target.closest('[data-tour]');
            if (tourTrigger) {
                e.preventDefault();
                const tourId = tourTrigger.dataset.tour;
                this.startTour(tourId);
            }
        });

        // Listen for help triggers
        document.addEventListener('click', (e) => {
            const helpTrigger = e.target.closest('[data-help]');
            if (helpTrigger) {
                e.preventDefault();
                const helpTopic = helpTrigger.dataset.help;
                this.showHelp(helpTopic);
            }
        });

        // Help button in header
        document.addEventListener('click', (e) => {
            if (e.target.closest('#helpBtn')) {
                e.preventDefault();
                this.showHelpMenu();
            }
        });

        console.log('UserGuide: Initialized');
    },

    /**
     * Initialize tooltips for all elements with data-tooltip
     */
    initTooltips() {
        // Create tooltip container
        if (!document.getElementById('tooltip-container')) {
            const container = document.createElement('div');
            container.id = 'tooltip-container';
            container.className = 'user-guide-tooltip';
            document.body.appendChild(container);
        }

        // Delegate tooltip events
        document.addEventListener('mouseenter', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                clearTimeout(this.tooltipTimeout);
                this.tooltipTimeout = setTimeout(() => {
                    this.showTooltip(target, target.dataset.tooltip, target.dataset.tooltipPlacement || 'top');
                }, this.config.tooltipDelay);
            }
        }, true);

        document.addEventListener('mouseleave', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                clearTimeout(this.tooltipTimeout);
                this.hideTooltip();
            }
        }, true);
    },

    /**
     * Show tooltip
     */
    showTooltip(element, text, placement = 'top') {
        const tooltip = document.getElementById('tooltip-container');
        tooltip.textContent = text;
        tooltip.className = 'user-guide-tooltip active';

        // Position tooltip
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();

        let top, left;

        switch (placement) {
            case 'top':
                top = rect.top - tooltipRect.height - 10;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'bottom':
                top = rect.bottom + 10;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'left':
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.left - tooltipRect.width - 10;
                break;
            case 'right':
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.right + 10;
                break;
        }

        tooltip.style.top = `${top + window.scrollY}px`;
        tooltip.style.left = `${left + window.scrollX}px`;
        tooltip.dataset.placement = placement;
    },

    /**
     * Hide tooltip
     */
    hideTooltip() {
        const tooltip = document.getElementById('tooltip-container');
        if (tooltip) {
            tooltip.classList.remove('active');
        }
    },

    /**
     * Initialize help triggers (? icons)
     */
    initHelpTriggers() {
        // Add help icon to elements with data-help-trigger
        document.querySelectorAll('[data-help-trigger]').forEach(trigger => {
            if (!trigger.querySelector('.help-icon')) {
                const icon = document.createElement('span');
                icon.className = 'help-icon';
                icon.innerHTML = '?';
                icon.setAttribute('data-help', trigger.dataset.helpTrigger);
                icon.setAttribute('data-tooltip', 'Klik for hjælp');
                trigger.appendChild(icon);
            }
        });
    },

    /**
     * Show contextual help
     */
    async showHelp(topic) {
        console.log('UserGuide: Showing help for:', topic);

        // Define help content
        const helpContent = {
            'customers': {
                title: 'Kunde Administration',
                content: `
                    <h3>Hvordan administrerer jeg kunder?</h3>
                    <p>I kunde-modulet kan du:</p>
                    <ul>
                        <li><strong>Oprette nye kunder:</strong> Klik på "Opret kunde" knappen</li>
                        <li><strong>Redigere kunder:</strong> Klik på penneikon ved den kunde du vil redigere</li>
                        <li><strong>Slette kunder:</strong> Klik på skraldeikon (NB: Slet kun inaktive kunder)</li>
                        <li><strong>Søge kunder:</strong> Brug søgefeltet til at finde specifikke kunder</li>
                    </ul>
                    <h4>Nyttige tips:</h4>
                    <ul>
                        <li>CVR-nummer valideres automatisk</li>
                        <li>Du kan tilføje logo og branding til kunder for rapporter</li>
                        <li>Kunder kan have flere projekter tilknyttet</li>
                    </ul>
                `
            },
            'projects': {
                title: 'Projekt Administration',
                content: `
                    <h3>Hvordan arbejder jeg med projekter?</h3>
                    <p>Projekter er kernen i DueDiligence systemet:</p>
                    <ul>
                        <li><strong>Opret projekt:</strong> Klik "Opret projekt" og vælg en kunde</li>
                        <li><strong>Tilføj bygninger:</strong> Hvert projekt kan have flere bygninger</li>
                        <li><strong>Budgettering:</strong> Tilføj CAPEX og OPEX budgetter</li>
                        <li><strong>Rapporter:</strong> Generér professionelle rapporter fra projektet</li>
                    </ul>
                    <h4>Projekt typer:</h4>
                    <ul>
                        <li><strong>Due Diligence:</strong> Standard bygningsgennemgang</li>
                        <li><strong>Tilstandsrapport:</strong> Detaljeret tilstandsvurdering</li>
                        <li><strong>Vedligeholdelsesplan:</strong> Langsigtet vedligeholdelse</li>
                    </ul>
                `
            },
            'buildings': {
                title: 'Bygnings Administration',
                content: `
                    <h3>Hvordan administrerer jeg bygninger?</h3>
                    <p>Bygninger indeholder alle bygningselementer og data:</p>
                    <ul>
                        <li><strong>Byg hierarki:</strong> Bygning → Etage → Rum → Elementer</li>
                        <li><strong>Tilføj elementer:</strong> Drag-and-drop elementer fra kataloget</li>
                        <li><strong>Upload billeder:</strong> Dokumentér med fotos og annoteringer</li>
                        <li><strong>Red flags:</strong> Markér kritiske problemer automatisk</li>
                    </ul>
                `
            },
            'reports': {
                title: 'Rapport System',
                content: `
                    <h3>Hvordan genererer jeg rapporter?</h3>
                    <p>DueDiligence har et kraftfuldt rapport system:</p>
                    <ul>
                        <li><strong>Vælg skabelon:</strong> Start med en standardskabelon</li>
                        <li><strong>Tilpas indhold:</strong> Brug rapport builder til at tilpasse</li>
                        <li><strong>Live preview:</strong> Se ændringer i real-time</li>
                        <li><strong>Eksportér:</strong> PDF eller Excel format</li>
                    </ul>
                    <h4>Skabelon variabler:</h4>
                    <ul>
                        <li><code>{project.name}</code> - Projektnavn</li>
                        <li><code>{customer.name}</code> - Kundenavn</li>
                        <li><code>{total_budget}</code> - Total budget</li>
                    </ul>
                `
            },
            'budget': {
                title: 'Budget Administration',
                content: `
                    <h3>Hvordan håndterer jeg budgetter?</h3>
                    <p>To typer budgetter:</p>
                    <ul>
                        <li><strong>CAPEX:</strong> Kapitaludgifter (engangsomkostninger)</li>
                        <li><strong>OPEX:</strong> Driftsudgifter (løbende omkostninger)</li>
                    </ul>
                    <h4>Budget funktioner:</h4>
                    <ul>
                        <li>Automatisk beregning af totaler</li>
                        <li>Priskatalog integration</li>
                        <li>Budget vs. faktisk sammenligning</li>
                        <li>Excel export til analyse</li>
                    </ul>
                `
            }
        };

        const content = helpContent[topic] || {
            title: 'Hjælp',
            content: '<p>Hjælp emne ikke fundet. Kontakt support for assistance.</p>'
        };

        await ModalBuilder.alert(content.title, content.content, {
            size: 'large',
            confirmText: 'Forstået'
        });
    },

    /**
     * Check if we should show welcome tour
     */
    checkWelcomeTour() {
        const status = this.getGuideStatus();

        // Show welcome tour for new users
        if (!status.welcome_completed) {
            setTimeout(() => {
                this.startTour('welcome');
            }, this.config.tourDelay);
        }
    },

    /**
     * Start a guided tour
     */
    async startTour(tourId) {
        const tour = this.tours[tourId];
        if (!tour) {
            console.error('UserGuide: Tour not found:', tourId);
            return;
        }

        console.log('UserGuide: Starting tour:', tourId);

        this.currentTour = tourId;
        this.currentStep = 0;

        await this.showTourStep();
    },

    /**
     * Show current tour step
     */
    async showTourStep() {
        const tour = this.tours[this.currentTour];
        const step = tour.steps[this.currentStep];

        if (!step) {
            this.endTour();
            return;
        }

        // Remove existing highlight
        document.querySelectorAll('.user-guide-highlight').forEach(el => {
            el.classList.remove('user-guide-highlight');
        });

        // Add highlight if needed
        if (step.highlight && step.target) {
            const target = document.querySelector(step.target);
            if (target) {
                target.classList.add('user-guide-highlight');
                // Scroll target into view
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Build step content
        const stepHtml = `
            <div class="tour-step-content">
                <div class="tour-progress">
                    Step ${this.currentStep + 1} af ${tour.steps.length}
                </div>
                <h3>${step.title}</h3>
                <p>${step.content}</p>
                <div class="tour-actions">
                    <button type="button" class="btn btn-sm btn-secondary" data-tour-action="skip">
                        Luk
                    </button>
                    ${this.currentStep > 0 ? '<button type="button" class="btn btn-sm btn-secondary" data-tour-action="prev">Forrige</button>' : ''}
                    <button type="button" class="btn btn-sm btn-primary" data-tour-action="next">
                        ${this.currentStep < tour.steps.length - 1 ? 'Næste' : 'Afslut'}
                    </button>
                </div>
            </div>
        `;

        // Show tour modal
        if (step.placement === 'center' || !step.target) {
            // Show as centered modal
            const modal = document.createElement('div');
            modal.className = 'user-guide-tour-modal';
            modal.innerHTML = stepHtml;
            document.body.appendChild(modal);

            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'user-guide-backdrop';
            document.body.appendChild(backdrop);

            // Handle tour actions
            this.attachTourActions(modal);
        } else {
            // Show as positioned tooltip
            const target = document.querySelector(step.target);
            if (target) {
                const tooltip = document.createElement('div');
                tooltip.className = `user-guide-tour-tooltip placement-${step.placement}`;
                tooltip.innerHTML = stepHtml;
                document.body.appendChild(tooltip);

                // Position tooltip
                this.positionTourTooltip(tooltip, target, step.placement);

                // Handle tour actions
                this.attachTourActions(tooltip);
            } else {
                // Target not found, skip to next
                console.warn('UserGuide: Tour target not found:', step.target);
                this.currentStep++;
                await this.showTourStep();
            }
        }
    },

    /**
     * Position tour tooltip relative to target
     */
    positionTourTooltip(tooltip, target, placement) {
        const targetRect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();

        let top, left;

        switch (placement) {
            case 'top':
                top = targetRect.top - tooltipRect.height - 20;
                left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'bottom':
                top = targetRect.bottom + 20;
                left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'left':
                top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
                left = targetRect.left - tooltipRect.width - 20;
                break;
            case 'right':
                top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
                left = targetRect.right + 20;
                break;
        }

        tooltip.style.top = `${top + window.scrollY}px`;
        tooltip.style.left = `${left + window.scrollX}px`;
    },

    /**
     * Attach event handlers to tour actions
     */
    attachTourActions(container) {
        container.addEventListener('click', async (e) => {
            const action = e.target.dataset.tourAction;
            if (!action) return;

            e.preventDefault();

            switch (action) {
                case 'next':
                    this.currentStep++;
                    this.removeTourUI();
                    await this.showTourStep();
                    break;
                case 'prev':
                    this.currentStep--;
                    this.removeTourUI();
                    await this.showTourStep();
                    break;
                case 'skip':
                    this.endTour();
                    break;
            }
        });
    },

    /**
     * Remove tour UI elements
     */
    removeTourUI() {
        // Remove modals
        document.querySelectorAll('.user-guide-tour-modal, .user-guide-tour-tooltip, .user-guide-backdrop').forEach(el => {
            el.remove();
        });

        // Remove highlights
        document.querySelectorAll('.user-guide-highlight').forEach(el => {
            el.classList.remove('user-guide-highlight');
        });
    },

    /**
     * End current tour
     */
    endTour() {
        console.log('UserGuide: Ending tour:', this.currentTour);

        this.removeTourUI();

        // Mark tour as completed
        this.markTourCompleted(this.currentTour);

        this.currentTour = null;
        this.currentStep = 0;
    },

    /**
     * Get guide status from localStorage
     */
    getGuideStatus() {
        const stored = localStorage.getItem(this.config.storageKey);
        return stored ? JSON.parse(stored) : {
            welcome_completed: false,
            tours_completed: []
        };
    },

    /**
     * Mark tour as completed
     */
    markTourCompleted(tourId) {
        const status = this.getGuideStatus();

        if (tourId === 'welcome') {
            status.welcome_completed = true;
        }

        if (!status.tours_completed.includes(tourId)) {
            status.tours_completed.push(tourId);
        }

        localStorage.setItem(this.config.storageKey, JSON.stringify(status));
    },

    /**
     * Reset guide status (for testing)
     */
    reset() {
        localStorage.removeItem(this.config.storageKey);
        console.log('UserGuide: Status reset');
    },

    /**
     * Add a custom tour
     */
    addTour(tourId, tourConfig) {
        this.tours[tourId] = tourConfig;
        console.log('UserGuide: Tour added:', tourId);
    },

    /**
     * Show help menu with available tours and help topics
     */
    async showHelpMenu() {
        const menuHtml = `
            <div class="help-menu-content">
                <h3>Hjælp og Vejledning</h3>

                <div class="help-section">
                    <h4>Guider</h4>
                    <button type="button" class="help-menu-item" data-tour="welcome">
                        <span class="help-icon">📚</span>
                        <div>
                            <strong>Velkommen Tour</strong>
                            <p>Få en grundlæggende introduktion til systemet</p>
                        </div>
                    </button>
                    <button type="button" class="help-menu-item" data-tour="project-create">
                        <span class="help-icon">📁</span>
                        <div>
                            <strong>Opret Projekt</strong>
                            <p>Lær hvordan du opretter et nyt projekt</p>
                        </div>
                    </button>
                    <button type="button" class="help-menu-item" data-tour="report-builder">
                        <span class="help-icon">📊</span>
                        <div>
                            <strong>Rapport Builder</strong>
                            <p>Få hjælp til at bygge professionelle rapporter</p>
                        </div>
                    </button>
                </div>

                <div class="help-section">
                    <h4>Hjælpeemner</h4>
                    <button type="button" class="help-menu-item" data-help="customers">
                        <span class="help-icon">👥</span>
                        <div>
                            <strong>Kunde Administration</strong>
                            <p>Hvordan administrerer jeg kunder?</p>
                        </div>
                    </button>
                    <button type="button" class="help-menu-item" data-help="projects">
                        <span class="help-icon">📁</span>
                        <div>
                            <strong>Projekt Administration</strong>
                            <p>Hvordan arbejder jeg med projekter?</p>
                        </div>
                    </button>
                    <button type="button" class="help-menu-item" data-help="buildings">
                        <span class="help-icon">🏢</span>
                        <div>
                            <strong>Bygnings Administration</strong>
                            <p>Hvordan administrerer jeg bygninger?</p>
                        </div>
                    </button>
                    <button type="button" class="help-menu-item" data-help="reports">
                        <span class="help-icon">📄</span>
                        <div>
                            <strong>Rapport System</strong>
                            <p>Hvordan genererer jeg rapporter?</p>
                        </div>
                    </button>
                    <button type="button" class="help-menu-item" data-help="budget">
                        <span class="help-icon">💰</span>
                        <div>
                            <strong>Budget Administration</strong>
                            <p>Hvordan håndterer jeg budgetter?</p>
                        </div>
                    </button>
                </div>

                <div class="help-section">
                    <h4>Genveje</h4>
                    <div class="keyboard-shortcuts">
                        <div class="shortcut-item">
                            <kbd>Ctrl</kbd> + <kbd>K</kbd>
                            <span>Global søgning</span>
                        </div>
                        <div class="shortcut-item">
                            <kbd>Ctrl</kbd> + <kbd>N</kbd>
                            <span>Opret nyt element</span>
                        </div>
                        <div class="shortcut-item">
                            <kbd>Esc</kbd>
                            <span>Luk modal/dialog</span>
                        </div>
                        <div class="shortcut-item">
                            <kbd>?</kbd>
                            <span>Vis hjælp</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        await ModalBuilder.alert('Hjælp', menuHtml, {
            size: 'large',
            confirmText: 'Luk'
        });
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => UserGuide.init());
} else {
    UserGuide.init();
}

// Expose globally
window.UserGuide = UserGuide;
