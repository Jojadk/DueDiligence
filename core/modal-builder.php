<?php
/**
 * Central Modal Builder
 *
 * Provides standardized modal HTML generation with consistent structure
 * Ensures uniform styling, button placement, and behavior across all modals
 *
 * Usage:
 *   $modal = ModalBuilder::create('project-form')
 *       ->title('Opret Projekt')
 *       ->body($formHtml)
 *       ->footer([
 *           ['text' => 'Annuller', 'class' => 'btn-secondary', 'action' => 'close'],
 *           ['text' => 'Gem', 'class' => 'btn-primary', 'action' => 'submit', 'form' => 'project-form']
 *       ])
 *       ->size('large')
 *       ->build();
 */

class ModalBuilder {
    private string $id;
    private string $title = '';
    private string $body = '';
    private array $footer = [];
    private string $size = 'medium'; // small, medium, large, xlarge
    private bool $closeButton = true;
    private bool $backdrop = true;
    private bool $keyboard = true;
    private array $headerButtons = [];
    private array $attributes = [];

    /**
     * Create new modal builder
     *
     * @param string $id Unique modal ID
     * @return ModalBuilder
     */
    public static function create(string $id): ModalBuilder {
        return new self($id);
    }

    private function __construct(string $id) {
        $this->id = $id;
    }

    /**
     * Set modal title
     *
     * @param string $title Modal title
     * @return $this
     */
    public function title(string $title): self {
        $this->title = $title;
        return $this;
    }

    /**
     * Set modal body content
     *
     * @param string $content Body HTML content
     * @return $this
     */
    public function body(string $content): self {
        $this->body = $content;
        return $this;
    }

    /**
     * Set modal footer buttons
     *
     * @param array $buttons Array of button configs
     * @return $this
     *
     * Button format:
     * [
     *   'text' => 'Button Text',
     *   'class' => 'btn-primary',
     *   'action' => 'submit|close|custom',
     *   'form' => 'form-id',  // Optional: associate with form
     *   'icon' => '<svg>...</svg>',  // Optional
     *   'data' => ['key' => 'value']  // Optional data attributes
     * ]
     */
    public function footer(array $buttons): self {
        $this->footer = $buttons;
        return $this;
    }

    /**
     * Set modal size
     *
     * @param string $size Size: small, medium, large, xlarge
     * @return $this
     */
    public function size(string $size): self {
        $this->size = $size;
        return $this;
    }

    /**
     * Enable/disable close button
     *
     * @param bool $enabled
     * @return $this
     */
    public function closeButton(bool $enabled): self {
        $this->closeButton = $enabled;
        return $this;
    }

    /**
     * Enable/disable backdrop click to close
     *
     * @param bool $enabled
     * @return $this
     */
    public function backdrop(bool $enabled): self {
        $this->backdrop = $enabled;
        return $this;
    }

    /**
     * Enable/disable ESC key to close
     *
     * @param bool $enabled
     * @return $this
     */
    public function keyboard(bool $enabled): self {
        $this->keyboard = $enabled;
        return $this;
    }

    /**
     * Add custom header buttons (e.g., help, settings)
     *
     * @param array $buttons Array of button configs
     * @return $this
     */
    public function headerButtons(array $buttons): self {
        $this->headerButtons = $buttons;
        return $this;
    }

    /**
     * Add custom data attributes to modal
     *
     * @param array $attributes Associative array of attributes
     * @return $this
     */
    public function attributes(array $attributes): self {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Build modal HTML
     *
     * @return string Modal HTML
     */
    public function build(): string {
        $sizeClass = $this->getSizeClass();
        $dataAttrs = $this->buildDataAttributes();

        $html = '<div class="modal-content ' . htmlspecialchars($sizeClass) . '" ' . $dataAttrs . '>';

        // Header
        if ($this->title || $this->closeButton || !empty($this->headerButtons)) {
            $html .= $this->buildHeader();
        }

        // Body
        $html .= '<div class="modal-body">';
        $html .= $this->body;
        $html .= '</div>';

        // Footer
        if (!empty($this->footer)) {
            $html .= $this->buildFooter();
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build modal header
     *
     * @return string Header HTML
     */
    private function buildHeader(): string {
        $html = '<div class="modal-header">';

        if ($this->title) {
            $html .= '<h2 class="modal-title">' . htmlspecialchars($this->title) . '</h2>';
        }

        // Header buttons (help, settings, etc.)
        if (!empty($this->headerButtons)) {
            $html .= '<div class="modal-header-buttons">';
            foreach ($this->headerButtons as $button) {
                $html .= $this->buildButton($button, true);
            }
            $html .= '</div>';
        }

        // Close button
        if ($this->closeButton) {
            $html .= '
                <button type="button" class="btn-modal-close" data-modal-close aria-label="Luk">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            ';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build modal footer
     *
     * @return string Footer HTML
     */
    private function buildFooter(): string {
        $html = '<div class="modal-footer">';

        // Buttons are displayed left-to-right in order
        // Typically: [Cancel/Secondary] [Primary/Submit]
        foreach ($this->footer as $button) {
            $html .= $this->buildButton($button);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build individual button
     *
     * @param array $button Button configuration
     * @param bool $isHeaderButton Is this a header button?
     * @return string Button HTML
     */
    private function buildButton(array $button, bool $isHeaderButton = false): string {
        $text = $button['text'] ?? '';
        $class = $button['class'] ?? 'btn';
        $action = $button['action'] ?? '';
        $form = $button['form'] ?? '';
        $icon = $button['icon'] ?? '';
        $data = $button['data'] ?? [];

        $attrs = [];

        // Add classes
        if ($isHeaderButton) {
            $attrs[] = 'class="btn-icon ' . htmlspecialchars($class) . '"';
        } else {
            $attrs[] = 'class="btn ' . htmlspecialchars($class) . '"';
        }

        // Add action attribute
        if ($action === 'close') {
            $attrs[] = 'data-modal-close';
        } elseif ($action === 'submit' && $form) {
            $attrs[] = 'type="submit"';
            $attrs[] = 'form="' . htmlspecialchars($form) . '"';
        } elseif ($action) {
            $attrs[] = 'data-action="' . htmlspecialchars($action) . '"';
        }

        // Add custom data attributes
        foreach ($data as $key => $value) {
            $attrs[] = 'data-' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        $html = '<button ' . implode(' ', $attrs) . '>';

        if ($icon) {
            $html .= $icon;
        }

        if ($text) {
            $html .= htmlspecialchars($text);
        }

        $html .= '</button>';

        return $html;
    }

    /**
     * Get CSS size class
     *
     * @return string Size class
     */
    private function getSizeClass(): string {
        return match($this->size) {
            'small' => 'modal-sm',
            'large' => 'modal-lg',
            'xlarge' => 'modal-xl',
            default => 'modal-md'
        };
    }

    /**
     * Build data attributes string
     *
     * @return string Data attributes
     */
    private function buildDataAttributes(): string {
        $attrs = [];

        $attrs[] = 'data-modal-id="' . htmlspecialchars($this->id) . '"';

        if (!$this->backdrop) {
            $attrs[] = 'data-backdrop="false"';
        }

        if (!$this->keyboard) {
            $attrs[] = 'data-keyboard="false"';
        }

        foreach ($this->attributes as $key => $value) {
            $attrs[] = 'data-' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return implode(' ', $attrs);
    }

    /**
     * Create standard form modal
     *
     * @param string $id Modal ID
     * @param string $title Modal title
     * @param string $formHtml Form HTML content
     * @param string $formId Form ID
     * @param array $options Additional options
     * @return string Modal HTML
     */
    public static function form(
        string $id,
        string $title,
        string $formHtml,
        string $formId,
        array $options = []
    ): string {
        $size = $options['size'] ?? 'medium';
        $submitText = $options['submitText'] ?? 'Gem';
        $cancelText = $options['cancelText'] ?? 'Annuller';
        $submitClass = $options['submitClass'] ?? 'btn-primary';

        return self::create($id)
            ->title($title)
            ->body($formHtml)
            ->footer([
                [
                    'text' => $cancelText,
                    'class' => 'btn-secondary',
                    'action' => 'close'
                ],
                [
                    'text' => $submitText,
                    'class' => $submitClass,
                    'action' => 'submit',
                    'form' => $formId
                ]
            ])
            ->size($size)
            ->build();
    }

    /**
     * Create confirmation modal
     *
     * @param string $id Modal ID
     * @param string $title Modal title
     * @param string $message Confirmation message
     * @param array $options Additional options
     * @return string Modal HTML
     */
    public static function confirm(
        string $id,
        string $title,
        string $message,
        array $options = []
    ): string {
        $confirmText = $options['confirmText'] ?? 'Bekræft';
        $cancelText = $options['cancelText'] ?? 'Annuller';
        $confirmClass = $options['confirmClass'] ?? 'btn-danger';
        $icon = $options['icon'] ?? '';

        $body = '<div class="modal-confirm-message">';
        if ($icon) {
            $body .= '<div class="modal-confirm-icon">' . $icon . '</div>';
        }
        $body .= '<p>' . htmlspecialchars($message) . '</p>';
        $body .= '</div>';

        return self::create($id)
            ->title($title)
            ->body($body)
            ->footer([
                [
                    'text' => $cancelText,
                    'class' => 'btn-secondary',
                    'action' => 'cancel'
                ],
                [
                    'text' => $confirmText,
                    'class' => $confirmClass,
                    'action' => 'confirm'
                ]
            ])
            ->size('small')
            ->build();
    }

    /**
     * Create info/alert modal
     *
     * @param string $id Modal ID
     * @param string $title Modal title
     * @param string $message Message content
     * @param array $options Additional options
     * @return string Modal HTML
     */
    public static function alert(
        string $id,
        string $title,
        string $message,
        array $options = []
    ): string {
        $buttonText = $options['buttonText'] ?? 'OK';
        $buttonClass = $options['buttonClass'] ?? 'btn-primary';
        $icon = $options['icon'] ?? '';
        $type = $options['type'] ?? 'info'; // info, success, warning, error

        $body = '<div class="modal-alert modal-alert-' . htmlspecialchars($type) . '">';
        if ($icon) {
            $body .= '<div class="modal-alert-icon">' . $icon . '</div>';
        }
        $body .= '<p>' . htmlspecialchars($message) . '</p>';
        $body .= '</div>';

        return self::create($id)
            ->title($title)
            ->body($body)
            ->footer([
                [
                    'text' => $buttonText,
                    'class' => $buttonClass,
                    'action' => 'close'
                ]
            ])
            ->size('small')
            ->build();
    }

    /**
     * Create table/list modal
     *
     * @param string $id Modal ID
     * @param string $title Modal title
     * @param string $tableHtml Table HTML content
     * @param array $options Additional options
     * @return string Modal HTML
     */
    public static function table(
        string $id,
        string $title,
        string $tableHtml,
        array $options = []
    ): string {
        $size = $options['size'] ?? 'large';
        $showActions = $options['showActions'] ?? true;

        $body = '<div class="modal-table-wrapper">';
        $body .= $tableHtml;
        $body .= '</div>';

        $footer = [];
        if ($showActions) {
            $footer[] = [
                'text' => 'Luk',
                'class' => 'btn-secondary',
                'action' => 'close'
            ];
        }

        return self::create($id)
            ->title($title)
            ->body($body)
            ->footer($footer)
            ->size($size)
            ->build();
    }
}

/**
 * Global helper function for quick modal creation
 *
 * @param string $id Modal ID
 * @return ModalBuilder
 */
function modal(string $id): ModalBuilder {
    return ModalBuilder::create($id);
}
