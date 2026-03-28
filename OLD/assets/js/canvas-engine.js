/**
 * Advanced Canvas Engine
 * Handles Image Loading, Scaling, Drawing (Circle, Rect, Arrow, Text)
 * and Relative Coordinate Persistence (0.0 - 1.0)
 */
class CanvasEngine {
    constructor(canvasId, imageSrc, savedData = []) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.image = new Image();
        this.image.crossOrigin = 'anonymous'; // Fix CORS for Save
        this.image.src = imageSrc;
        this.objects = savedData || []; // { type, x, y, w, h, color, text }
        this.currentTool = 'select'; // select, circle, rect, arrow, text
        this.isDrawing = false;
        this.startX = 0;
        this.startY = 0;

        this.rotation = 0; // 0, 90, 180, 270
        this.selectedObjectIndex = -1; // -1 none

        this.init();
    }

    init() {
        this.image.onload = () => {
            this.resizeCanvas();
            this.draw();
        };
        // If image cached
        if (this.image.complete) {
            this.resizeCanvas();
            this.draw();
        }

        window.addEventListener('resize', () => {
            this.resizeCanvas();
            this.draw();
        });

        this.canvas.addEventListener('mousedown', (e) => this.onMouseDown(e));
        this.canvas.addEventListener('mousemove', (e) => this.onMouseMove(e));
        this.canvas.addEventListener('mouseup', (e) => this.onMouseUp(e));
        this.canvas.addEventListener('dblclick', (e) => this.onDoubleClick(e));
        // Touch support skipped for brevity but recommended
    }

    onDoubleClick(e) {
        e.preventDefault();
        const pos = this.getMouseInImageSpace(e);

        // Hit test
        for (let i = this.objects.length - 1; i >= 0; i--) {
            const o = this.objects[i];
            const ox = o.w < 0 ? o.x + o.w : o.x;
            const oy = o.h < 0 ? o.y + o.h : o.y;
            const ow = Math.abs(o.w);
            const oh = Math.abs(o.h);
            const margin = 0.02;

            if (pos.x >= ox - margin && pos.x <= ox + ow + margin && pos.y >= oy - margin && pos.y <= oy + oh + margin) {
                if (o.type === 'text') {
                    this.startTextEditing(i);
                }
                break;
            }
        }
    }

    resizeCanvas() {
        // Fit to container while maintaining aspect ratio
        const container = this.canvas.parentElement;
        if (!container) return;

        const maxWidth = container.clientWidth;
        const maxHeight = container.clientHeight || 600;

        // Effective dimensions based on rotation
        let imgW = this.image.width;
        let imgH = this.image.height;
        if (this.rotation === 90 || this.rotation === 270) {
            imgW = this.image.height;
            imgH = this.image.width;
        }

        // Calculate aspect ratios
        const imgRatio = imgW / imgH;
        const containerRatio = maxWidth / maxHeight;

        let finalW, finalH;

        if (imgRatio > containerRatio) {
            // Limited by width
            finalW = maxWidth;
            finalH = finalW / imgRatio;
        } else {
            // Limited by height
            finalH = maxHeight;
            finalW = finalH * imgRatio;
        }

        this.canvas.width = finalW;
        this.canvas.height = finalH;
        this.scale = finalW / imgW; // Store global scale
    }

    // Convert screen pixel coords to Normalized Image Coords (0..1) taking rotation into account
    // This is tricky. simpler: Draw everything in "Image Space" then transforming the context?
    // No, mouse events come in screen space.
    // Let's stick to "Objects are 0..1 relative to the *Original unrotated Image*".

    // Helper to wrap text
    wrapText(ctx, text, maxWidth) {
        const words = text.split(' ');
        let lines = [];
        let currentLine = words[0];

        for (let i = 1; i < words.length; i++) {
            const word = words[i];
            const width = ctx.measureText(currentLine + " " + word).width;
            if (width < maxWidth) {
                currentLine += " " + word;
            } else {
                lines.push(currentLine);
                currentLine = word;
            }
        }
        lines.push(currentLine);
        return lines;
    }

    draw() {
        // Clear
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.save();

        // 1. Center and Rotate Context
        this.ctx.translate(this.canvas.width / 2, this.canvas.height / 2);
        this.ctx.rotate((this.rotation * Math.PI) / 180);

        // Use global scale calculated in resizeCanvas
        let scale = this.scale || 1;

        // 2. Draw Image (centered)
        if (this.image && this.image.complete && this.image.naturalHeight !== 0) {
            this.ctx.drawImage(
                this.image,
                -this.image.width / 2 * scale,
                -this.image.height / 2 * scale,
                this.image.width * scale,
                this.image.height * scale
            );
        } else {
            this.ctx.fillStyle = '#cc0000';
            this.ctx.textAlign = 'center';
            this.ctx.fillText('Image Failed to Load', 0, 0);
        }

        // 3. Draw Objects
        this.objects.forEach((obj, index) => {
            this.drawObject(obj, scale, index === this.selectedObjectIndex);
        });

        if (this.currentObject) {
            this.drawObject(this.currentObject, scale, true);
        }

        this.ctx.restore();
    }

    drawObject(obj, scale, isSelected) {
        const imgW = this.image.width;
        const imgH = this.image.height;

        const x = (-imgW / 2 + obj.x * imgW) * scale;
        const y = (-imgH / 2 + obj.y * imgH) * scale;
        const w = (obj.w * imgW) * scale;
        const h = (obj.h * imgH) * scale;

        // Scale properties to match image scale
        // This ensures exportHighRes (scale=1) looks proportional to Editor (scale < 1)
        const scaledLineWidth = (obj.lineWidth || 3) * scale;
        const scaledFontSize = parseInt(obj.fontSize || 24) * scale;

        this.ctx.beginPath();
        this.ctx.strokeStyle = obj.color || 'red';
        this.ctx.lineWidth = Math.max(1, scaledLineWidth); // Ensure at least 1px visible

        if (isSelected) {
            this.ctx.strokeStyle = '#00ff00';
            this.ctx.setLineDash([5, 5]);
        } else {
            this.ctx.setLineDash([]);
        }

        if (obj.type === 'rect') {
            if (obj.backgroundColor) {
                this.ctx.save();
                this.ctx.fillStyle = obj.backgroundColor;
                this.ctx.globalAlpha = obj.backgroundOpacity !== undefined ? parseFloat(obj.backgroundOpacity) : 1.0;
                this.ctx.fillRect(x, y, w, h);
                this.ctx.restore();
            }
            this.ctx.strokeRect(x, y, w, h);
        } else if (obj.type === 'circle') {
            this.ctx.beginPath(); // New path for circle
            this.ctx.ellipse(x + w / 2, y + h / 2, Math.abs(w / 2), Math.abs(h / 2), 0, 0, 2 * Math.PI);
            if (obj.backgroundColor) {
                this.ctx.save();
                this.ctx.fillStyle = obj.backgroundColor;
                this.ctx.globalAlpha = obj.backgroundOpacity !== undefined ? parseFloat(obj.backgroundOpacity) : 1.0;
                this.ctx.fill();
                this.ctx.restore();
            }
            this.ctx.stroke();
        } else if (obj.type === 'text') {

            // Draw Background if set
            if (obj.backgroundColor) {
                this.ctx.save();
                this.ctx.fillStyle = obj.backgroundColor;
                if (obj.backgroundOpacity !== undefined) {
                    this.ctx.globalAlpha = parseFloat(obj.backgroundOpacity);
                } else {
                    this.ctx.globalAlpha = 1.0;
                }

                // Recalculate wrapping with scaled font
                this.ctx.font = 'bold ' + (scaledFontSize) + 'px Arial';
                const lines = this.wrapText(this.ctx, obj.text || '', w);
                const lineHeight = scaledFontSize * 1.2;
                const totalH = Math.max(h, lines.length * lineHeight);

                // Add some padding
                const pad = 5;
                this.ctx.fillRect(x - pad, y - pad, w + (pad * 2), totalH + (pad * 2));
                this.ctx.restore();
            }

            this.ctx.fillStyle = obj.color || 'red';
            this.ctx.font = 'bold ' + (scaledFontSize) + 'px Arial';
            this.ctx.textBaseline = 'top';

            // Wrap Text
            const lines = this.wrapText(this.ctx, obj.text || '', w);
            const lineHeight = scaledFontSize * 1.2;

            lines.forEach((line, i) => {
                this.ctx.fillText(line, x, y + (i * lineHeight));
            });

            if (isSelected) {
                // Resize selection box to fit text content if it grew
                const totalH = Math.max(h, lines.length * lineHeight);
                this.ctx.strokeRect(x - 5, y - 5, w + 10, totalH + 10);
            }
        } else if (obj.type === 'arrow') {
            this.drawArrow(x, y, x + w, y + h);
            this.ctx.stroke();
            if (isSelected) {
                this.ctx.strokeRect(Math.min(x, x + w) - 5, Math.min(y, y + h) - 5, Math.abs(w) + 10, Math.abs(h) + 10);
            }
        }

        // Draw selection handles if selected
        if (isSelected) {
            this.ctx.setLineDash([]);
            this.ctx.fillStyle = '#00ff00';
            this.ctx.strokeStyle = '#000';
            this.ctx.lineWidth = 1;
            const handleSize = 8;
            const corners = [
                { x: x, y: y, id: 'tl' },
                { x: x + w, y: y, id: 'tr' },
                { x: x, y: y + h, id: 'bl' },
                { x: x + w, y: y + h, id: 'br' }
            ];
            corners.forEach(c => {
                this.ctx.fillRect(c.x - handleSize / 2, c.y - handleSize / 2, handleSize, handleSize);
                this.ctx.strokeRect(c.x - handleSize / 2, c.y - handleSize / 2, handleSize, handleSize);
            });
        }
    }

    drawArrow(fromx, fromy, tox, toy) {
        const headlen = 15;
        const dx = tox - fromx;
        const dy = toy - fromy;
        const angle = Math.atan2(dy, dx);
        this.ctx.moveTo(fromx, fromy);
        this.ctx.lineTo(tox, toy);
        this.ctx.lineTo(tox - headlen * Math.cos(angle - Math.PI / 6), toy - headlen * Math.sin(angle - Math.PI / 6));
        this.ctx.moveTo(tox, toy);
        this.ctx.lineTo(tox - headlen * Math.cos(angle + Math.PI / 6), toy - headlen * Math.sin(angle + Math.PI / 6));
    }

    getMouseInImageSpace(e) {
        const rect = this.canvas.getBoundingClientRect();
        const cx = (e.clientX - rect.left);
        const cy = (e.clientY - rect.top);

        const centerX = this.canvas.width / 2;
        const centerY = this.canvas.height / 2;

        let dx = cx - centerX;
        let dy = cy - centerY;

        const rad = -(this.rotation * Math.PI) / 180;
        const rdx = dx * Math.cos(rad) - dy * Math.sin(rad);
        const rdy = dx * Math.sin(rad) + dy * Math.cos(rad);

        let scale = 1;
        if (this.rotation === 0 || this.rotation === 180) {
            scale = this.canvas.width / this.image.width;
        } else {
            scale = this.canvas.width / this.image.height;
        }

        const imgX = rdx / scale + this.image.width / 2;
        const imgY = rdy / scale + this.image.height / 2;

        return {
            x: imgX / this.image.width,
            y: imgY / this.image.height,
            rawX: rdx,
            rawY: rdy,
            scale: scale
        };
    }

    onMouseDown(e) {
        e.preventDefault();
        e.stopPropagation();

        const pos = this.getMouseInImageSpace(e);
        this.startX = pos.x;
        this.startY = pos.y;

        // Stop any active text editing
        // this.stopTextEditing(); // Don't stop on click if we want to click the textarea? 
        // No, stopTextEditing hides it. If we click canvas, we should hide it.
        // If we click textarea (which is above canvas), this event won't see it (stopPropagation on textarea).
        this.stopTextEditing();

        if (this.currentTool === 'select') {
            // Check if clicking handle of selected object
            if (this.selectedObjectIndex !== -1) {
                const obj = this.objects[this.selectedObjectIndex];
                const imgW = this.image.width;
                const scale = this.scale;

                const handleTolerance = 15 / (scale * imgW);

                const corners = [
                    { x: obj.x, y: obj.y, id: 'tl' },
                    { x: obj.x + obj.w, y: obj.y, id: 'tr' },
                    { x: obj.x, y: obj.y + obj.h, id: 'bl' },
                    { x: obj.x + obj.w, y: obj.y + obj.h, id: 'br' }
                ];

                for (let c of corners) {
                    if (Math.abs(pos.x - c.x) < handleTolerance && Math.abs(pos.y - c.y) < handleTolerance) {
                        this.isResizing = true;
                        this.resizeHandle = c.id;
                        return;
                    }
                }
            }

            // Hit Test for Objects
            this.selectedObjectIndex = -1;
            for (let i = this.objects.length - 1; i >= 0; i--) {
                const o = this.objects[i];
                // basic hit test
                const ox = o.w < 0 ? o.x + o.w : o.x;
                const oy = o.h < 0 ? o.y + o.h : o.y;
                const ow = Math.abs(o.w);
                const oh = Math.abs(o.h);

                const margin = 0.02;

                if (pos.x >= ox - margin && pos.x <= ox + ow + margin && pos.y >= oy - margin && pos.y <= oy + oh + margin) {
                    this.selectedObjectIndex = i;
                    this.isDragging = true;
                    this.dragOffsetX = pos.x - o.x;
                    this.dragOffsetY = pos.y - o.y;

                    // removed single click text edit trigger

                    // Update header inputs to match selected object
                    if (document.querySelector('input[title="Skriftstørrelse"]')) {
                        document.querySelector('input[title="Skriftstørrelse"]').value = o.fontSize || 24;
                        document.querySelector('input[title="Stregtykkelse"]').value = o.lineWidth || 3;
                        document.querySelector('input[type="color"]').value = o.color || '#ff0000';

                        // Background controls
                        const bgCheck = document.getElementById('canvas-bg-check');
                        const bgColor = document.getElementById('canvas-bg-color');
                        const bgOpacity = document.getElementById('canvas-bg-opacity');

                        if (bgCheck) {
                            bgCheck.checked = !!o.backgroundColor;
                            if (o.backgroundColor) {
                                if (bgColor) bgColor.value = o.backgroundColor;
                                if (bgOpacity) bgOpacity.value = (o.backgroundOpacity !== undefined ? o.backgroundOpacity : 1) * 100;
                            }
                            // Trigger visibility update manually or via event?
                            // Just set values. UI toggling logic should be in the UI event handler or here.
                            if (bgColor) bgColor.style.display = o.backgroundColor ? 'inline-block' : 'none';
                            if (bgOpacity) bgOpacity.style.display = o.backgroundColor ? 'inline-block' : 'none';
                        }
                    }

                    break;
                }
            }
            this.draw();
            return;
        }

        this.isDrawing = true;
        this.currentObject = {
            type: this.currentTool,
            x: pos.x,
            y: pos.y,
            w: 0,
            h: 0,
            color: this.currentColor || 'red',
            fontSize: this.currentFontSize || 24,
            lineWidth: this.currentLineWidth || 3
        };
    }

    onMouseMove(e) {
        if (!this.isDrawing && !this.isDragging && !this.isResizing) return;

        // If editing text, do NOT allow dragging/resizing from canvas interactions
        if (this.editingIndex !== undefined) return;

        e.preventDefault();
        e.stopPropagation();

        const pos = this.getMouseInImageSpace(e);
        // ... existing resizing logic ...
        if (this.isResizing && this.selectedObjectIndex !== -1) {
            const obj = this.objects[this.selectedObjectIndex];
            if (this.resizeHandle === 'br') {
                obj.w = pos.x - obj.x;
                obj.h = pos.y - obj.y;
            } else if (this.resizeHandle === 'tr') {
                obj.w = pos.x - obj.x;
                obj.h = obj.h + (obj.y - pos.y);
                obj.y = pos.y;
            } else if (this.resizeHandle === 'bl') {
                obj.w = obj.w + (obj.x - pos.x);
                obj.h = pos.y - obj.y;
                obj.x = pos.x;
            } else if (this.resizeHandle === 'tl') {
                obj.w = obj.w + (obj.x - pos.x);
                obj.h = obj.h + (obj.y - pos.y);
                obj.x = pos.x;
                obj.y = pos.y;
            }
            this.draw();
            return;
        }

        if (this.isDragging && this.selectedObjectIndex !== -1) {
            const obj = this.objects[this.selectedObjectIndex];
            obj.x = pos.x - this.dragOffsetX;
            obj.y = pos.y - this.dragOffsetY;
            this.draw();
            return;
        }

        if (this.isDrawing && this.currentObject) {
            this.currentObject.w = pos.x - this.startX;
            this.currentObject.h = pos.y - this.startY;
            this.draw();
        }
    }

    onMouseUp(e) {
        if (!this.isDrawing && !this.isDragging && !this.isResizing) return;

        e.preventDefault();
        e.stopPropagation();

        const wasDrawing = this.isDrawing;
        const tool = this.currentTool;
        const obj = this.currentObject;

        this.isDrawing = false;
        this.isDragging = false;
        this.isResizing = false;
        this.currentObject = null;

        if (wasDrawing && obj) {
            if (tool === 'text') {
                obj.text = 'Skriv her...';
                obj.w = 0.2; // Default width
                obj.h = 0.05;
                this.objects.push(obj);
                this.selectedObjectIndex = this.objects.length - 1;
                this.draw();
                // We no longer auto-start editing on creation for text tool?
                // Wait, if I create text, I usually want to edit it immediately.
                // The user said "double click to edit".
                // But for NEW text, it feels weird to create it then double click.
                // Lets keep auto-edit for NEW text.
                this.startTextEditing(this.selectedObjectIndex);
            } else if (Math.abs(obj.w) > 0.005 || Math.abs(obj.h) > 0.005) {
                this.objects.push(obj);
                // Select newly drawn object
                this.selectedObjectIndex = this.objects.length - 1;
            }
        }
        this.draw();
    }

    // Direct Text Editing Support
    startTextEditing(index) {
        const obj = this.objects[index];
        if (obj.type !== 'text') return;

        // Force stop dragging if it was somehow active
        this.isDragging = false;
        this.isResizing = false;

        let input = document.getElementById('canvas-text-input');
        // ... logic continues ...
        if (!input) {
            input = document.createElement('textarea');
            input.id = 'canvas-text-input';
            input.style.position = 'absolute';
            input.style.border = '1px dashed #000';
            input.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
            input.style.color = 'red';
            input.style.font = 'bold 24px Arial';
            input.style.zIndex = '10000';
            input.style.resize = 'both';
            input.style.padding = '5px';
            input.style.margin = '0';

            // Stop propagation to prevent canvas events
            input.addEventListener('mousedown', (e) => e.stopPropagation());
            input.addEventListener('click', (e) => e.stopPropagation());

            document.body.appendChild(input);

            input.addEventListener('input', () => {
                if (this.editingIndex !== undefined && this.objects[this.editingIndex]) {
                    this.objects[this.editingIndex].text = input.value;
                    this.draw();
                }
            });

            // Sync size on mouse up (after resize)
            input.addEventListener('mouseup', (e) => {
                if (this.editingIndex !== undefined) {
                    e.stopPropagation();
                    const obj = this.objects[this.editingIndex];
                    const imgW = this.image.width;
                    const imgH = this.image.height;
                    const scale = this.scale || 1;

                    // Convert pixels back to normalized coords
                    obj.w = parseFloat(input.style.width) / scale / imgW;
                    obj.h = parseFloat(input.style.height) / scale / imgH;

                    this.draw(); // Update canvas (text wrapping changes)
                }
            });

            input.addEventListener('blur', () => {
                this.stopTextEditing();
            });
        }

        this.editingIndex = index;

        // Position Input over Canvas Object
        const imgW = this.image.width;
        const imgH = this.image.height;
        let scale = this.scale || 1;

        const rect = this.canvas.getBoundingClientRect();

        let screenX = ((-imgW / 2 + obj.x * imgW) * scale) + (this.canvas.width / 2);
        let screenY = ((-imgH / 2 + obj.y * imgH) * scale) + (this.canvas.height / 2);

        // Add canvas offset
        screenX += rect.left;
        screenY += rect.top;

        let screenW = (obj.w * imgW) * scale;
        let screenH = (obj.h * imgH) * scale;

        input.value = obj.text || '';
        input.style.display = 'block';
        input.style.left = screenX + 'px';
        input.style.top = screenY + 'px';
        input.style.width = Math.abs(screenW) + 'px';
        input.style.height = Math.max(30, Math.abs(screenH)) + 'px';
        input.style.color = obj.color || 'red';
        const fs = obj.fontSize || 24;
        input.style.fontSize = fs + 'px';

        setTimeout(() => input.focus(), 10);
    }

    stopTextEditing() {
        const input = document.getElementById('canvas-text-input');
        if (input) {
            input.style.display = 'none';
        }
        this.editingIndex = undefined;
    }

    setTool(tool) {
        this.currentTool = tool;
        this.selectedObjectIndex = -1;
        this.stopTextEditing();
        this.draw();
    }

    setColor(color) {
        this.currentColor = color;
        // If object selected, update its color
        if (this.selectedObjectIndex !== -1) {
            this.objects[this.selectedObjectIndex].color = color;
            this.draw();
        }
        const input = document.getElementById('canvas-text-input');
        if (input && input.style.display !== 'none') {
            input.style.color = color;
        }
    }

    setFontSize(size) {
        this.currentFontSize = parseInt(size);
        if (this.selectedObjectIndex !== -1) {
            this.objects[this.selectedObjectIndex].fontSize = this.currentFontSize;
            this.draw();
            // Update input font size if visible
            const input = document.getElementById('canvas-text-input');
            if (input && input.style.display !== 'none') {
                input.style.fontSize = this.currentFontSize + 'px';
            }
        }
    }

    setBackgroundColor(color) {
        if (this.selectedObjectIndex !== -1) {
            // If null/false passed, remove background
            if (!color) {
                delete this.objects[this.selectedObjectIndex].backgroundColor;
            } else {
                this.objects[this.selectedObjectIndex].backgroundColor = color;
                // Ensure opacity is set if missing
                if (this.objects[this.selectedObjectIndex].backgroundOpacity === undefined) {
                    this.objects[this.selectedObjectIndex].backgroundOpacity = 1;
                }
            }
            this.draw();
        }
    }

    setBackgroundOpacity(opacity) {
        if (this.selectedObjectIndex !== -1) {
            this.objects[this.selectedObjectIndex].backgroundOpacity = parseFloat(opacity);
            this.draw();
        }
    }

    setLineWidth(width) {
        this.currentLineWidth = parseInt(width);
        if (this.selectedObjectIndex !== -1) {
            this.objects[this.selectedObjectIndex].lineWidth = this.currentLineWidth;
            this.draw();
        }
    }

    rotate(angle) {
        this.rotation = (this.rotation + angle) % 360;
        this.resizeCanvas();
        this.draw();
    }

    undo() {
        this.objects.pop();
        this.draw();
    }

    clear() {
        // If object selected, remove ONLY that
        if (this.selectedObjectIndex !== -1) {
            this.objects.splice(this.selectedObjectIndex, 1);
            this.selectedObjectIndex = -1;
        } else {
            if (confirm('Ryd alt?')) this.objects = [];
        }
        this.draw();
    }

    // Export current view as image
    exportImage(format = 'image/jpeg', quality = 0.8) {
        return this.canvas.toDataURL(format, quality);
    }

    // Export High Res Image (Original Resolution)
    exportHighRes(format = 'image/jpeg', quality = 0.6) {
        // Create an offscreen canvas
        const offCanvas = document.createElement('canvas');
        const offCtx = offCanvas.getContext('2d');

        // Match original image dimensions (or rotate if needed)
        // If rotation is 90/270, swap w/h
        let w = this.image.naturalWidth;
        let h = this.image.naturalHeight;

        if (this.rotation === 90 || this.rotation === 270) {
            w = this.image.naturalHeight;
            h = this.image.naturalWidth;
        }

        offCanvas.width = w;
        offCanvas.height = h;

        // Draw logic adapted for offscreen (SCALE = 1.0 but relative to natural dimensions)
        offCtx.save();
        offCtx.translate(w / 2, h / 2);
        offCtx.rotate((this.rotation * Math.PI) / 180);

        // Draw Image
        offCtx.drawImage(
            this.image,
            -this.image.naturalWidth / 2,
            -this.image.naturalHeight / 2
        );

        // Draw Objects
        // We need to scale objects by Natural Width
        // The objects are stored as normalized 0..1 coords relative to image
        // So simply multiply by naturalWidth/Height

        // BUT logic in drawObject uses a 'scale' param which scales from IMAGE SPACE to CANVAS SPACE
        // Here CANVAS SPACE == IMAGE SPACE. So scale SHOULD BE 1.

        // However, drawObject relies on this classes 'drawObject' method which uses 'this.ctx'
        // We need 'drawObject' to accept a context!
        // Or we temporarily swap contexts? No, that's messy.
        // Let's replicate the relevant drawing logic or modify drawObject to take a ctx.
        // Actually, drawObject calls this.ctx. Let's redirect this.ctx for a moment?
        // Risky if async. But we are single threaded.

        const originalCtx = this.ctx;
        this.ctx = offCtx; // Swap context

        this.objects.forEach(obj => {
            // Scale = 1 because we are drawing 1:1 map to image
            this.drawObject(obj, 1, false);
        });

        this.ctx = originalCtx; // Restore
        offCtx.restore();

        return offCanvas.toDataURL(format, quality);
    }

    setMode(mode, options = {}) {
        this.setTool(mode);
        if (options.color) this.setColor(options.color);
        if (options.width) this.setLineWidth(options.width);
        if (options.fontSize) this.setFontSize(options.fontSize);
    }

    save() {
        return JSON.stringify(this.objects);
    }
}
