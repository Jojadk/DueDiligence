/**
 * i18n - Internationalization Module
 * Supports Danish and English translations
 */
const i18n = {
    currentLang: 'da', // Default language
    fallbackLang: 'en',

    translations: {
        da: {
            // Common
            'common.save': 'Gem',
            'common.cancel': 'Annuller',
            'common.delete': 'Slet',
            'common.edit': 'Rediger',
            'common.close': 'Luk',
            'common.loading': 'Indlæser...',
            'common.error': 'Fejl',
            'common.success': 'Succes',
            'common.warning': 'Advarsel',
            'common.confirm': 'Bekræft',
            'common.search': 'Søg',
            'common.add': 'Tilføj',
            'common.update': 'Opdater',
            'common.create': 'Opret',
            'common.tools': 'Værktøjer',
            'report.title': 'Rapport',
            'review.title': 'Text Review',

            // TDD Standard Structure (DA)
            "std.1": "Udendørsarealer (Terræn)",
            "std.1.1": "Belægninger (Vej, sti, fortov, pladser)",
            "std.1.2": "Grønne områder (Beplantning, græsarealer)",
            "std.1.3": "Hegn, porte og støttemure",
            "std.1.4": "Udvendig belysning",
            "std.1.5": "Afvanding af terræn (Brønde, render)",
            "std.1.6": "Skilte og inventar (Bænke, cykelstativer)",

            "std.2": "Bygningsskal (Klimaskærm)",
            "std.2.1": "Fundamenter og terrændæk",
            "std.2.2": "Facader (Murværk, beton, lette facader)",
            "std.2.3": "Vinduer og yderdøre",
            "std.2.4": "Tage (Tagdækning, ovenlys, tagbrønde)",
            "std.2.5": "Altaner og udvendige trapper",
            "std.2.6": "Porte og ramper (f.eks. til vareindlevering)",

            "std.3": "Indvendige bygningsdele",
            "std.3.1": "Indvendige vægge og skillevægge",
            "std.3.2": "Indvendige døre og partier",
            "std.3.3": "Gulve og gulvbelægninger",
            "std.3.4": "Lofter (Systemlofter, faste lofter)",
            "std.3.5": "Indvendige trapper",
            "std.3.6": "Inventar (Køkkener, toiletter, faste skabe)",

            "std.4": "Tekniske installationer",
            "std.4.1": "Vand (Brugsvand, sanitet)",
            "std.4.2": "Afløb (Spildevand, regnvand indvendigt)",
            "std.4.3": "Varme (Radiatorer, gulvvarme, fjernvarmeunits)",
            "std.4.4": "Køling (Køleflader, serverrumskøling)",
            "std.4.5": "Ventilation (Aggregater, kanaler, styring)",
            "std.4.6": "El-grundinstallationer (Tavler, føringsveje)",
            "std.4.7": "Belysning (Indvendig)",
            "std.4.8": "Elevatorer og løfteplatforme",
            "std.4.9": "Brandsikring (ABA, varsling, sprinkler, røgudluftning)",
            "std.4.10": "CTS / Bygningsautomation",

            // Navigation
            'nav.projects': 'Projekter',
            'nav.customers': 'Kunder',
            'nav.dashboard': 'Dashboard',
            'nav.settings': 'Indstillinger',
            'nav.logout': 'Log ud',
            'nav.profile': 'Min Profil',

            // Building Elements
            'element.new_section': 'NYT AFSNIT',
            'element.edit': 'Rediger',
            'element.delete': 'Slet',
            'element.location': 'Lokation',
            'element.name': 'Navn',
            'element.description': 'Beskrivelse',
            'element.recommendation': 'Anbefaling',
            'element.observation': 'Observation',
            'element.internal_notes': 'Interne Noter',
            'element.risk_level': 'Risikoniveau',
            'element.condition': 'Tilstand',
            'element.budget': 'Budget',
            'element.media': 'Billeder',
            'element.loading_content': 'Indlæser element...',
            'element.load_failed': 'Kunne ikke indlæse element',

            // Projects
            'project.name': 'Projektnavn',
            'project.client': 'Kunde',
            'project.status': 'Status',
            'project.created': 'Oprettet',
            'project.team': 'Team',
            'project.save_success': 'Projekt gemt!',
            'project.delete_confirm': 'Er du sikker på at du vil slette dette projekt?',

            // Customers
            'customer.name': 'Kundenavn',
            'customer.email': 'Email',
            'customer.phone': 'Telefon',
            'customer.address': 'Adresse',
            'customer.save_success': 'Kunde gemt!',
            'customer.delete_confirm': 'Er du sikker?',

            // Versioning
            'version.save': 'Gem Version',
            'version.history': 'Versioner',
            'version.number': 'Version Nummer',
            'version.note': 'Version Note',
            'version.restore': 'Gendan',
            'version.save_current': 'Gem nuværende tilstand',
            'version.save_success': 'Version gemt!',
            'version.restore_success': 'Version gendannet!',

            // Errors
            'error.network': 'Netværksfejl',
            'error.system': 'Systemfejl',
            'error.not_found': 'Ikke fundet',
            'error.permission_denied': 'Adgang nægtet',
            'error.validation': 'Valideringsfejl',
            'error.required': 'Dette felt er påkrævet',
            'error.min_length': 'Minimum {0} tegn påkrævet',

            // Messages
            'msg.confirm_delete': 'Er du sikker på at du vil slette "{0}"?',
            'msg.changes_saved': 'Ændringer gemt',
            'msg.no_results': 'Ingen resultater',
        },

        en: {
            // Common
            'common.save': 'Save',
            'common.cancel': 'Cancel',
            'common.delete': 'Delete',
            'common.edit': 'Edit',
            'common.close': 'Close',
            'common.loading': 'Loading...',
            'common.error': 'Error',
            'common.success': 'Success',
            'common.warning': 'Warning',
            'common.confirm': 'Confirm',
            'common.search': 'Search',
            'common.add': 'Add',
            'common.update': 'Update',
            'common.create': 'Create',
            'common.tools': 'Tools',
            'report.title': 'Report',
            'review.title': 'Text Review',

            // TDD Standard Structure (EN)
            "std.1": "Outdoor Areas (Terrain)",
            "std.1.1": "Pavings (Road, path, sidewalk, squares)",
            "std.1.2": "Green Areas (Planting, grass areas)",
            "std.1.3": "Fences, gates and retaining walls",
            "std.1.4": "Outdoor lighting",
            "std.1.5": "Drainage of terrain (Wells, gutters)",
            "std.1.6": "Signs and inventory (Benches, bike racks)",

            "std.2": "Building Envelope (Climate Screen)",
            "std.2.1": "Foundations and terrain deck",
            "std.2.2": "Facades (Masonry, concrete, light facades)",
            "std.2.3": "Windows and outer doors",
            "std.2.4": "Roofs (Roofing, skylights, roof wells)",
            "std.2.5": "Balconies and outdoor stairs",
            "std.2.6": "Gates and ramps (e.g. for delivery)",

            "std.3": "Interior Building Parts",
            "std.3.1": "Interior walls and partitions",
            "std.3.2": "Interior doors and sections",
            "std.3.3": "Floors and floor coverings",
            "std.3.4": "Ceilings (System ceilings, fixed ceilings)",
            "std.3.5": "Interior stairs",
            "std.3.6": "Inventory (Kitchens, toilets, fixed cupboards)",

            "std.4": "Technical Installations",
            "std.4.1": "Water (Domestic water, sanitation)",
            "std.4.2": "Drainage (Wastewater, rainwater internal)",
            "std.4.3": "Heating (Radiators, floor heating, heating units)",
            "std.4.4": "Cooling (Cooling surfaces, server room cooling)",
            "std.4.5": "Ventilation (Aggregates, ducts, control)",
            "std.4.6": "Electrical basics (Panels, cableways)",
            "std.4.7": "Lighting (Internal)",
            "std.4.8": "Elevators and lifting platforms",
            "std.4.9": "Fire protection (ABA, warning, sprinkler, smoke venting)",
            "std.4.10": "CTS / Building Automation",

            // Navigation
            'nav.projects': 'Projects',
            'nav.customers': 'Customers',
            'nav.dashboard': 'Dashboard',
            'nav.settings': 'Settings',
            'nav.logout': 'Logout',
            'nav.profile': 'My Profile',

            // Building Elements
            'element.new_section': 'NEW SECTION',
            'element.edit': 'Edit',
            'element.delete': 'Delete',
            'element.location': 'Location',
            'element.name': 'Name',
            'element.description': 'Description',
            'element.recommendation': 'Recommendation',
            'element.observation': 'Observation',
            'element.internal_notes': 'Internal Notes',
            'element.risk_level': 'Risk Level',
            'element.condition': 'Condition',
            'element.budget': 'Budget',
            'element.media': 'Images',
            'element.loading_content': 'Loading element...',
            'element.load_failed': 'Could not load element',

            // Projects
            'project.name': 'Project Name',
            'project.client': 'Client',
            'project.status': 'Status',
            'project.created': 'Created',
            'project.team': 'Team',
            'project.save_success': 'Project saved!',
            'project.delete_confirm': 'Are you sure you want to delete this project?',

            // Customers
            'customer.name': 'Customer Name',
            'customer.email': 'Email',
            'customer.phone': 'Phone',
            'customer.address': 'Address',
            'customer.save_success': 'Customer saved!',
            'customer.delete_confirm': 'Are you sure?',

            // Versioning
            'version.save': 'Save Version',
            'version.history': 'Versions',
            'version.number': 'Version Number',
            'version.note': 'Version Note',
            'version.restore': 'Restore',
            'version.save_current': 'Save current state',
            'version.save_success': 'Version saved!',
            'version.restore_success': 'Version restored!',

            // Errors
            'error.network': 'Network error',
            'error.system': 'System error',
            'error.not_found': 'Not found',
            'error.permission_denied': 'Permission denied',
            'error.validation': 'Validation error',
            'error.required': 'This field is required',
            'error.min_length': 'Minimum {0} characters required',

            // Messages
            'msg.confirm_delete': 'Are you sure you want to delete "{0}"?',
            'msg.changes_saved': 'Changes saved',
            'msg.no_results': 'No results',
        }
    },

    /**
     * Initialize i18n with language from localStorage or default
     */
    init: function () {
        const savedLang = localStorage.getItem('app_language');
        if (savedLang && this.translations[savedLang]) {
            this.currentLang = savedLang;
        }
        this.updatePageLanguage();
    },

    /**
     * Get translation for a key
     * @param {string} key - Translation key (e.g., 'common.save')
     * @param {Array} replacements - Optional array of values to replace {0}, {1}, etc.
     * @returns {string} Translated text
     */
    t: function (key, replacements = []) {
        let text = this.translations[this.currentLang]?.[key]
            || this.translations[this.fallbackLang]?.[key]
            || key;

        // Replace placeholders {0}, {1}, etc.
        replacements.forEach((value, index) => {
            text = text.replace(`{${index}}`, value);
        });

        return text;
    },

    /**
     * Set current language
     * @param {string} lang - Language code ('da' or 'en')
     */
    setLanguage: function (lang) {
        if (this.translations[lang]) {
            this.currentLang = lang;
            localStorage.setItem('app_language', lang);
            this.updatePageLanguage();

            // Trigger custom event for components to update
            document.dispatchEvent(new CustomEvent('languageChanged', {
                detail: { language: lang }
            }));
        }
    },

    /**
     * Get current language
     * @returns {string} Current language code
     */
    getLanguage: function () {
        return this.currentLang;
    },

    /**
     * Update all elements with data-i18n attribute
     */
    updatePageLanguage: function () {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const replacements = el.getAttribute('data-i18n-replace');
            const values = replacements ? JSON.parse(replacements) : [];

            el.textContent = this.t(key, values);
        });

        // Update placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            el.placeholder = this.t(key);
        });

        // Update titles
        document.querySelectorAll('[data-i18n-title]').forEach(el => {
            const key = el.getAttribute('data-i18n-title');
            el.title = this.t(key);
        });

        // Update document lang attribute
        document.documentElement.lang = this.currentLang;
    }
};

// Initialize on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => i18n.init());
} else {
    i18n.init();
}

// Make globally available
window.i18n = i18n;
