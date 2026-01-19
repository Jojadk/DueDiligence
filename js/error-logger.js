/**
 * Central Error Logger
 *
 * Samler alle fejl et centralt sted for senere bearbejdning
 */

class ErrorLogger {
    constructor() {
        this.errors = [];
        this.maxErrors = 100;
        this.endpoint = '/api.php?module=system&action=log_error';
        this.batchSize = 10;
        this.flushInterval = 30000; // 30 sek
        this.queue = [];

        this.startAutoFlush();
        this.setupGlobalErrorHandler();
    }

    /**
     * Log an error
     */
    log(error, context = {}) {
        const errorEntry = {
            timestamp: new Date().toISOString(),
            message: error.message || String(error),
            stack: error.stack || null,
            context: context,
            url: window.location.href,
            userAgent: navigator.userAgent
        };

        this.errors.push(errorEntry);
        this.queue.push(errorEntry);

        // Trim errors array hvis for stor
        if (this.errors.length > this.maxErrors) {
            this.errors = this.errors.slice(-this.maxErrors);
        }

        // Flush hvis queue er fuld
        if (this.queue.length >= this.batchSize) {
            this.flush();
        }

        // Log til console i development
        if (window.APP_CONFIG && window.APP_CONFIG.debug) {
            console.error('[ErrorLogger]', error, context);
        }
    }

    /**
     * Flush errors to server
     */
    async flush() {
        if (this.queue.length === 0) return;

        const errorsToSend = [...this.queue];
        this.queue = [];

        try {
            await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    errors: errorsToSend,
                    csrf_token: window.CSRF_TOKEN
                })
            });
        } catch (e) {
            // Silent fail - vi kan ikke logge at logging fejler
            this.queue.unshift(...errorsToSend);
        }
    }

    /**
     * Start auto-flush interval
     */
    startAutoFlush() {
        setInterval(() => this.flush(), this.flushInterval);

        // Flush ved beforeunload
        window.addEventListener('beforeunload', () => {
            if (this.queue.length > 0) {
                // Sync request for at sikre den sender
                navigator.sendBeacon(this.endpoint, JSON.stringify({
                    errors: this.queue,
                    csrf_token: window.CSRF_TOKEN
                }));
            }
        });
    }

    /**
     * Setup global error handler
     */
    setupGlobalErrorHandler() {
        window.addEventListener('error', (event) => {
            this.log(event.error || new Error(event.message), {
                type: 'global',
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno
            });
        });

        window.addEventListener('unhandledrejection', (event) => {
            this.log(event.reason, {
                type: 'unhandled_promise'
            });
        });
    }

    /**
     * Get recent errors
     */
    getErrors(limit = 50) {
        return this.errors.slice(-limit);
    }

    /**
     * Clear all errors
     */
    clear() {
        this.errors = [];
        this.queue = [];
    }
}

// Global instance
window.ErrorLogger = new ErrorLogger();

// Helper function
window.logError = (error, context) => {
    if (window.ErrorLogger) {
        window.ErrorLogger.log(error, context);
    }
};
