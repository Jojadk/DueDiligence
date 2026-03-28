/**
 * Global JavaScript Error Handler
 * Captures execution errors and unhandled promise rejections
 * Sends logs to backend for debugging.
 */

(function () {
    // Prevent recursive logging loops
    let isReportingError = false;

    function sendError(data) {
        if (isReportingError) return;
        isReportingError = true;

        const payload = {
            type: data.type || 'error',
            message: data.message,
            url: data.url || window.location.href,
            line: data.line,
            col: data.col,
            stack: data.stack,
            meta: {
                userAgent: navigator.userAgent,
                platform: navigator.platform,
                lastInteraction: window.lastInteractionElement ? window.lastInteractionElement.tagName : 'N/A'
            }
        };

        // Use fetch to send beacon-like request
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch('?module=System&action=logError', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
            },
            body: JSON.stringify(payload)
        }).catch(err => {
            console.warn('Failed to report error:', err);
        }).finally(() => {
            isReportingError = false;
        });
    }

    // Capture user interactions to provide context (last clicked element)
    window.addEventListener('click', (e) => {
        try {
            const target = e.target;
            window.lastInteractionElement = {
                tagName: target.tagName,
                id: target.id,
                className: target.className,
                text: target.innerText ? target.innerText.substring(0, 20) : ''
            };
        } catch (e) { }
    }, true);

    // Global Error Listener
    window.onerror = function (message, source, lineno, colno, error) {
        console.group('🚨 Global JS Error');
        console.error('Msg:', message);
        console.error('Src:', source + ':' + lineno + ':' + colno);
        if (error && error.stack) console.error(error.stack);
        console.groupEnd();

        sendError({
            type: 'js_error',
            message: message,
            url: source,
            line: lineno,
            col: colno,
            stack: error ? error.stack : 'No stack trace'
        });
        // Return false to let default handler run (print to console)
        return false;
    };

    // Unhandled Promise Rejection Listener
    window.addEventListener('unhandledrejection', function (event) {
        let message = 'Unhandled Promise Rejection';
        let stack = '';

        if (event.reason) {
            if (event.reason instanceof Error) {
                message = event.reason.message;
                stack = event.reason.stack;
            } else {
                message = JSON.stringify(event.reason);
            }
        }

        console.group('🚨 Unhandled Promise Rejection');
        console.error(message);
        if (stack) console.error(stack);
        console.groupEnd();

        sendError({
            type: 'promise_rejection',
            message: message,
            stack: stack
        });
    });
})();
