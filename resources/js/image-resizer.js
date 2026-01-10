/**
 * Image Resizer for Filament RichEditor (TipTap)
 * 
 * Adds drag-to-resize functionality to images in the WYSIWYG editor
 */

document.addEventListener('DOMContentLoaded', function () {
    initImageResizer();

    // Re-init when Livewire updates (for Filament admin)
    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:navigated', initImageResizer);
    }
});

function initImageResizer() {
    // Wait for RichEditor to be available
    setTimeout(() => {
        setupImageResizers();
    }, 1000);

    // Also observe for dynamically added editors
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.addedNodes.length) {
                setupImageResizers();
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

function setupImageResizers() {
    // Target the TipTap/RichEditor content areas
    const editors = document.querySelectorAll('.tiptap, [contenteditable="true"]');

    editors.forEach(editor => {
        if (editor.dataset.imageResizerInit) return;
        editor.dataset.imageResizerInit = 'true';

        // Add resize functionality to all images
        editor.addEventListener('click', function (e) {
            if (e.target.tagName === 'IMG') {
                makeImageResizable(e.target, editor);
            } else {
                // Remove resize UI from other images
                removeResizeHandles();
            }
        });

        // Handle pasted/uploaded images
        editor.addEventListener('paste', () => {
            setTimeout(setupImagesInEditor.bind(null, editor), 100);
        });
    });
}

function setupImagesInEditor(editor) {
    const images = editor.querySelectorAll('img');
    images.forEach(img => {
        if (!img.style.maxWidth) {
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
        }
        img.style.cursor = 'pointer';
    });
}

function makeImageResizable(img, editor) {
    removeResizeHandles();

    // Create wrapper if not exists
    let wrapper = img.parentElement;
    if (!wrapper.classList.contains('image-resize-wrapper')) {
        wrapper = document.createElement('span');
        wrapper.className = 'image-resize-wrapper';
        wrapper.style.cssText = 'display: inline-block; position: relative;';
        img.parentNode.insertBefore(wrapper, img);
        wrapper.appendChild(img);
    }

    // Add visual selection indicator
    wrapper.classList.add('image-selected');

    // Create resize handle
    const handle = document.createElement('div');
    handle.className = 'image-resize-handle';
    handle.style.cssText = `
        position: absolute;
        bottom: 0;
        right: 0;
        width: 16px;
        height: 16px;
        background: linear-gradient(135deg, transparent 50%, #3b82f6 50%);
        cursor: nwse-resize;
        z-index: 10;
        border-radius: 0 0 4px 0;
    `;
    wrapper.appendChild(handle);

    // Create size display
    const sizeDisplay = document.createElement('div');
    sizeDisplay.className = 'image-size-display';
    sizeDisplay.style.cssText = `
        position: absolute;
        bottom: -24px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        white-space: nowrap;
        z-index: 10;
    `;
    sizeDisplay.textContent = `${img.offsetWidth} × ${img.offsetHeight}`;
    wrapper.appendChild(sizeDisplay);

    // Handle resizing
    let startX, startY, startWidth, startHeight;

    handle.addEventListener('mousedown', function (e) {
        e.preventDefault();
        e.stopPropagation();

        startX = e.clientX;
        startY = e.clientY;
        startWidth = img.offsetWidth;
        startHeight = img.offsetHeight;

        document.addEventListener('mousemove', resize);
        document.addEventListener('mouseup', stopResize);
    });

    function resize(e) {
        const deltaX = e.clientX - startX;
        const aspectRatio = startWidth / startHeight;
        const newWidth = Math.max(50, startWidth + deltaX);
        const newHeight = newWidth / aspectRatio;

        img.style.width = newWidth + 'px';
        img.style.height = newHeight + 'px';
        sizeDisplay.textContent = `${Math.round(newWidth)} × ${Math.round(newHeight)}`;
    }

    function stopResize() {
        document.removeEventListener('mousemove', resize);
        document.removeEventListener('mouseup', stopResize);

        // Trigger input event for Livewire
        editor.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function removeResizeHandles() {
    document.querySelectorAll('.image-resize-handle, .image-size-display').forEach(el => el.remove());
    document.querySelectorAll('.image-selected').forEach(el => el.classList.remove('image-selected'));
}

// Keyboard shortcuts
document.addEventListener('keydown', function (e) {
    const selectedWrapper = document.querySelector('.image-selected');
    if (!selectedWrapper) return;

    const img = selectedWrapper.querySelector('img');
    if (!img) return;

    // Delete/Backspace to remove image
    if (e.key === 'Delete' || e.key === 'Backspace') {
        selectedWrapper.remove();
        e.preventDefault();
    }

    // Escape to deselect
    if (e.key === 'Escape') {
        removeResizeHandles();
    }
});
