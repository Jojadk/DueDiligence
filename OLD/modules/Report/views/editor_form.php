<!DOCTYPE html>
<html>

<head>
    <title>Edit Template</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        textarea {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 12px;
            tab-size: 2;
        }

        .syntax-guide {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .syntax-guide h5 {
            margin-bottom: 10px;
        }

        .syntax-guide code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .syntax-guide table {
            font-size: 12px;
        }

        .syntax-guide td {
            padding: 4px 8px;
        }

        .preview-btn {
            margin-left: 10px;
        }
    </style>
</head>

<body class="p-4">
    <h1><?= $template ? 'Edit' : 'Create' ?> Template</h1>

    <!-- Syntax Guide -->
    <div class="syntax-guide">
        <h5>📘 Template Syntax Guide</h5>
        <div class="row">
            <div class="col-md-4">
                <strong>Variables</strong>
                <table class="table table-sm">
                    <tr>
                        <td><code>{{ project.name }}</code></td>
                        <td>Project name</td>
                    </tr>
                    <tr>
                        <td><code>{{ client.name }}</code></td>
                        <td>Client name</td>
                    </tr>
                    <tr>
                        <td><code>{{ today | date }}</code></td>
                        <td>Today's date</td>
                    </tr>
                    <tr>
                        <td><code>{{ value | money }}</code></td>
                        <td>Format as DKK</td>
                    </tr>
                    <tr>
                        <td><code>{{ text | nl2br }}</code></td>
                        <td>Line breaks</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-4">
                <strong>Conditionals</strong>
                <pre style="font-size:11px; background:#fff; padding:8px;">{if project.cover_image}
    &lt;img src="..."&gt;
{else}
    No image
{/if}</pre>
            </div>
            <div class="col-md-4">
                <strong>Loops</strong>
                <pre style="font-size:11px; background:#fff; padding:8px;">{foreach tree as category}
    {{ category.name }}
    {foreach category.children as el}
        {{ el.name }}
    {/foreach}
{/foreach}</pre>
            </div>
        </div>
        <details>
            <summary><strong>Available Data Objects</strong></summary>
            <div class="row mt-2">
                <div class="col-md-6">
                    <code>project.*</code>: name, address, bbr_number, construction_year, renovation_year, area_m2,
                    heating_type, cover_image, report_intro, report_disclaimer<br>
                    <code>client.*</code>: name, email, phone, address<br>
                    <code>today</code>: Current date
                </div>
                <div class="col-md-6">
                    <code>tree[]</code>: Array of categories<br>
                    <code>tree[].children[]</code>: Child elements<br>
                    <code>tree[].totals.*</code>: 0_1, 1_2, 3_5, 6_10, total<br>
                    <code>element.*</code>: name, description, recommendation, risk_level, capex, image, budget
                </div>
            </div>
        </details>
    </div>

    <form action="?module=Report&action=saveTemplate" method="POST" id="templateForm">
        <?php if ($template): ?><input type="hidden" name="id" value="<?= $template['id'] ?>">
        <?php endif; ?>

        <!-- Validation Alert -->
        <div id="validationAlert" class="alert alert-danger d-none mb-3" role="alert">
            <strong>⚠️ Syntax Issues Found:</strong>
            <ul id="validationErrors" class="mb-0 mt-2"></ul>
        </div>

        <div class="mb-3">
            <label class="form-label">Template Name</label>
            <input type="text" name="name" id="templateName" class="form-control"
                value="<?= htmlspecialchars($template['name'] ?? '') ?>" required>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">HTML Content <span id="htmlStatus" class="badge bg-secondary ms-2">Not
                            validated</span></label>
                    <textarea name="content" id="htmlContent" class="form-control" style="height:600px;"
                        required><?= htmlspecialchars($template['content'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">CSS Styles <span id="cssStatus" class="badge bg-secondary ms-2">Not
                            validated</span></label>
                    <textarea name="css" id="cssContent" class="form-control"
                        style="height:600px;"><?= htmlspecialchars($template['css'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-outline-primary me-2" onclick="validateTemplate()">🔍 Validate
                Syntax</button>
            <button type="submit" class="btn btn-success" id="submitBtn">💾 Save Template</button>
            <a href="?module=Report&action=editor" class="btn btn-secondary ms-2">Cancel</a>
            <?php if ($template): ?>
                <a href="?module=Report&action=renderFromTemplate&template_id=<?= $template['id'] ?>&project_id=1"
                    class="btn btn-info preview-btn" target="_blank">👁️ Preview with Project 1</a>
            <?php endif; ?>
        </div>
    </form>

    <script>
        // Template syntax validation
        function validateTemplate() {
            const html = document.getElementById('htmlContent').value;
            const css = document.getElementById('cssContent').value;
            const errors = [];

            // Check template syntax
            const syntaxErrors = validateTemplateSyntax(html);
            errors.push(...syntaxErrors);

            // Check CSS syntax
            const cssErrors = validateCSS(css);
            errors.push(...cssErrors);

            // Update UI
            const alertEl = document.getElementById('validationAlert');
            const errorsList = document.getElementById('validationErrors');
            const htmlStatus = document.getElementById('htmlStatus');
            const cssStatus = document.getElementById('cssStatus');

            if (errors.length > 0) {
                errorsList.innerHTML = errors.map(e => `<li>${e}</li>`).join('');
                alertEl.classList.remove('d-none');

                htmlStatus.className = 'badge bg-danger ms-2';
                htmlStatus.textContent = 'Issues found';
            } else {
                alertEl.classList.add('d-none');

                htmlStatus.className = 'badge bg-success ms-2';
                htmlStatus.textContent = '✓ Valid';
                cssStatus.className = 'badge bg-success ms-2';
                cssStatus.textContent = '✓ Valid';
            }

            return errors.length === 0;
        }

        function validateTemplateSyntax(html) {
            const errors = [];

            // Check for unclosed {foreach} tags
            const foreachOpens = (html.match(/\{foreach\s+/g) || []).length;
            const foreachCloses = (html.match(/\{\/foreach\}/g) || []).length;
            if (foreachOpens !== foreachCloses) {
                errors.push(`Unbalanced foreach tags: ${foreachOpens} opens, ${foreachCloses} closes`);
            }

            // Check for unclosed {if} tags
            const ifOpens = (html.match(/\{if\s+/g) || []).length;
            const ifCloses = (html.match(/\{\/if\}/g) || []).length;
            if (ifOpens !== ifCloses) {
                errors.push(`Unbalanced if tags: ${ifOpens} opens, ${ifCloses} closes`);
            }

            // Check for unclosed {{ }} variables
            const openBraces = (html.match(/\{\{/g) || []).length;
            const closeBraces = (html.match(/\}\}/g) || []).length;
            if (openBraces !== closeBraces) {
                errors.push(`Unbalanced variable braces: ${openBraces} {{ but ${closeBraces} }}`);
            }

            // Check for common HTML errors (basic)
            const divOpens = (html.match(/<div/gi) || []).length;
            const divCloses = (html.match(/<\/div>/gi) || []).length;
            if (divOpens !== divCloses) {
                errors.push(`Possible unclosed div tags: ${divOpens} opens, ${divCloses} closes`);
            }

            // Check for invalid syntax patterns
            if (html.match(/\{foreach\s+\w+\s*\}/)) {
                errors.push('Invalid foreach syntax - use {foreach list as item}');
            }

            if (html.match(/\{\{\s*\w+\s+\|\s*\}\}/)) {
                errors.push('Empty filter in variable - e.g. {{ var | }} should be {{ var | money }}');
            }

            return errors;
        }

        function validateCSS(css) {
            const errors = [];

            if (!css || css.trim() === '') {
                return errors; // Empty CSS is OK
            }

            // Check for balanced braces
            const openBraces = (css.match(/\{/g) || []).length;
            const closeBraces = (css.match(/\}/g) || []).length;
            if (openBraces !== closeBraces) {
                errors.push(`Unbalanced CSS braces: ${openBraces} { but ${closeBraces} }`);
            }

            // Check for common CSS errors
            if (css.match(/;\s*\}/)) {
                // This is actually fine
            }

            // Check for missing semicolons before }
            const badPatterns = css.match(/[^;\s\{]\s*\}/g);
            if (badPatterns && badPatterns.length > 0) {
                errors.push('Possible missing semicolons in CSS (check properties before closing braces)');
            }

            return errors;
        }

        // Optional: Auto-validate on blur
        document.getElementById('htmlContent').addEventListener('blur', function () {
            const errors = validateTemplateSyntax(this.value);
            const status = document.getElementById('htmlStatus');
            if (errors.length === 0) {
                status.className = 'badge bg-success ms-2';
                status.textContent = '✓ Valid';
            } else {
                status.className = 'badge bg-warning ms-2';
                status.textContent = errors.length + ' issues';
            }
        });

        document.getElementById('cssContent').addEventListener('blur', function () {
            const errors = validateCSS(this.value);
            const status = document.getElementById('cssStatus');
            if (errors.length === 0) {
                status.className = 'badge bg-success ms-2';
                status.textContent = '✓ Valid';
            } else {
                status.className = 'badge bg-warning ms-2';
                status.textContent = errors.length + ' issues';
            }
        });

        // Form submit validation
        document.getElementById('templateForm').addEventListener('submit', function (e) {
            const name = document.getElementById('templateName').value.trim();
            if (!name) {
                e.preventDefault();
                alert('Template name is required');
                return false;
            }

            const html = document.getElementById('htmlContent').value.trim();
            if (!html) {
                e.preventDefault();
                alert('HTML content is required');
                return false;
            }

            // Warn but don't block on syntax errors
            if (!validateTemplate()) {
                if (!confirm('There are syntax issues in your template. Save anyway?')) {
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });
    </script>
</body>

</html>