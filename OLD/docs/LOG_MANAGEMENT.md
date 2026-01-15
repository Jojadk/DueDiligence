# Central Error Log Management

Dette dokument beskriver hvordan systemfejl (både PHP og JavaScript) logges, læses og håndteres centralt.

## Systemoversigt

Systemet fanger automatisk:
- **PHP Fejl**: E_ERROR, E_WARNING, Exceptions (via `Core\ErrorHandler`).
- **JavaScript Fejl**: Globale errors og unhandled rejections (via `assets/js/error_handler.js`).

Fejlene samles i en fælles logfil for at give et komplet overblik.

## Log Fil
Loggen gemmes i filen:
**`logs/system_errors.log`**

## Workflow for AI Assistant

Når du fejlsøger eller er blevet bedt om at tjekke logs, skal du følge denne procedure:

1.  **Læs Loggen:**
    Brug `tail` til at læse de seneste fejl.
    ```bash
    tail -n 20 logs/system_errors.log
    ```

2.  **Analyser Fejl:**
    Loggen indeholder timestamp, type (PHP/JS), fejlbesked, kontekst (fil/linje), stack trace og requester info.

3.  **Ret Fejlen:**
    Udfør de nødvendige rettelser i koden.

4.  **Slet/Ryd Loggen:**
    Når fejlen er rettet, SKAL loggen ryddes for at nulstille status.
    ```bash
    echo "" > logs/system_errors.log
    ```

## Konsol Log
JS fejl logges også i browserens konsol. PHP fejl vises i browseren hvis `display_errors` er slået til (dev), ellers logges de kun til filen.
