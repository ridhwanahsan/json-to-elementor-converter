/**
 * LFE Editor Script - Diagnostic Edition
 * Improved error handling for the Connection Issue.
 */

(function($) {
    'use strict';

    const LFE_ICON_SVG = '<svg viewBox="0 0 24 24" width="24" height="24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L14.4 7.6L20 10L14.4 12.4L12 18L9.6 12.4L4 10L9.6 7.6L12 2Z"/><path d="M19 16L19.8 17.8L21.6 18.6L19.8 19.4L19 21.2L18.2 19.4L16.4 18.6L18.2 17.8L19 16Z"/><path d="M6 14L6.6 15.4L8 16L6.6 16.6L6 18L5.4 16.6L4 16L5.4 15.4L6 14Z"/></svg>';

    const LFE_UI = {
        showToast: function(message, type = 'success') {
            const $container = $('#lfe-toast-container');
            const icon = type === 'success' ? 'yes' : 'warning';
            const $toast = $('<div class="lfe-toast lfe-toast-' + type + '"><i class="dashicons dashicons-' + icon + '"></i><span>' + message + '</span></div>');
            $container.append($toast);
            setTimeout(() => { $toast.fadeOut(400, function() { $(this).remove(); }); }, 3000);
        },

        requestConfirm: function(title, desc, callback) {
            const $overlay = $('#lfe-confirm-overlay');
            $('#lfe-confirm-title').text(title);
            $('#lfe-confirm-desc').text(desc);
            $overlay.fadeIn(200).css('display', 'flex');
            $('#lfe-confirm-yes').off('click').on('click', () => { $overlay.fadeOut(100); callback(true); });
            $('#lfe-confirm-no').off('click').on('click', () => { $overlay.fadeOut(100); callback(false); });
        }
    };

    const LayoutForElementor = {
        insertAt: null,

        init: function() {
            if (typeof elementor === 'undefined') return;
            this.bindModalEvents();
            elementor.on('preview:loaded', () => { this.runInjection(); this.setupObserver(); });
            setTimeout(() => { this.runInjection(); this.setupObserver(); }, 1000);
            setInterval(() => this.runInjection(), 3000);
        },

        getPreviewContainer: function() {
            if (typeof elementor.getPreviewContainer === 'function') {
                return elementor.getPreviewContainer();
            }

            return null;
        },

        getInsertIndex: function($addSection) {
            const parentContainer = this.getPreviewContainer();

            if (!parentContainer || !parentContainer.children) {
                return 0;
            }

            const $preview = elementor.$previewContents;

            if (!$preview || !$preview.length) {
                return parentContainer.children.length || 0;
            }

            const index = $preview.find('.elementor-add-new-section:visible').index($addSection);

            if (index >= 0) {
                return index;
            }

            return parentContainer.children.length || 0;
        },

        generateElementId: function() {
            return Math.random().toString(36).slice(2, 9);
        },

        cloneElementModel: function(model) {
            if (Array.isArray(model)) {
                return model.map((item) => this.cloneElementModel(item));
            }

            if (!model || typeof model !== 'object') {
                return model;
            }

            const cloned = {};

            Object.keys(model).forEach((key) => {
                cloned[key] = this.cloneElementModel(model[key]);
            });

            if (Object.prototype.hasOwnProperty.call(model, 'elType') && Object.prototype.hasOwnProperty.call(cloned, 'id')) {
                cloned.id = this.generateElementId();
            }

            return cloned;
        },

        startHistoryLog: function() {
            if (typeof $e === 'undefined' || typeof $e.internal !== 'function') {
                return null;
            }

            return $e.internal('document/history/start-log', {
                type: 'import',
                title: 'JSON to Elementor Converter',
            });
        },

        endHistoryLog: function(historyId) {
            if (!historyId || typeof $e === 'undefined' || typeof $e.internal !== 'function') {
                return;
            }

            $e.internal('document/history/end-log', {
                id: historyId,
            });
        },

        insertLayoutIntoEditor: function(models) {
            const parentContainer = this.getPreviewContainer();

            if (!parentContainer) {
                throw new Error('Preview container not found.');
            }

            if (typeof $e === 'undefined' || typeof $e.run !== 'function') {
                throw new Error('Elementor editor commands are unavailable.');
            }

            const templates = Array.isArray(models) ? models : [models];

            if (!templates.length) {
                throw new Error('No importable layout was returned.');
            }

            const startAt = Number.isInteger(this.insertAt) ? this.insertAt : (parentContainer.children.length || 0);
            const historyId = this.startHistoryLog();

            templates.forEach((template, index) => {
                $e.run('document/elements/create', {
                    container: parentContainer,
                    model: this.cloneElementModel(template),
                    options: {
                        at: startAt + index,
                        edit: index === templates.length - 1,
                    },
                });
            });

            this.endHistoryLog(historyId);
        },

        bindModalEvents: function() {
            const $modal = $('#lfe-editor-modal-root');
            const $textarea = $('#lfe-modal-json');
            $modal.find('.lfe-close, .lfe-modal-overlay').on('click', () => { $modal.fadeOut(200); });

            $('#lfe-modal-prettify').on('click', () => {
                try {
                    const obj = JSON.parse($textarea.val());
                    $textarea.val(JSON.stringify(obj, null, 4));
                    LFE_UI.showToast('JSON Beautified');
                } catch (e) { LFE_UI.showToast('Invalid JSON!', 'error'); }
            });

            $('#lfe-modal-fix').on('click', () => {
                let raw = $textarea.val();
                raw = raw.replace(/,\s*([\]}])/g, '$1');
                $textarea.val(raw);
                LFE_UI.showToast('Syntax Cleaned');
            });

            $('#lfe-modal-history-list').on('click', '.lfe-history-item', function() {
                $textarea.val($(this).attr('data-json'));
                LFE_UI.showToast('Layout Loaded');
            });

            $('#lfe-modal-generate').on('click', () => {
                const jsonContent = $textarea.val();
                if (!jsonContent) { LFE_UI.showToast('Please paste JSON', 'error'); return; }

                // Check if elementor ID exists
                if (!elementor.config.document.id) {
                    LFE_UI.showToast('Editor Error: Post ID missing. Refresh editor.', 'error');
                    return;
                }

                const $btn = $('#lfe-modal-generate');
                const oldHtml = $btn.html();
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Inserting...');

                $.ajax({
                    url: lfe_editor_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'lfe_import_to_post',
                        post_id: elementor.config.document.id,
                        json_content: jsonContent,
                        nonce: lfe_editor_vars.nonce
                    },
                    success: (res) => {
                        if (res.success) {
                            try {
                                this.insertLayoutIntoEditor(res.data.normalized);
                                $modal.fadeOut(100);
                                LFE_UI.showToast('Layout inserted. Click Update to save.');
                            } catch (e) {
                                LFE_UI.showToast(e.message || 'Could not insert the layout.', 'error');
                                console.error('LFE INSERT ERROR:', e);
                            }
                        } else {
                            LFE_UI.showToast('Server: ' + res.data, 'error');
                        }

                        $btn.prop('disabled', false).html(oldHtml);
                    },
                    error: (xhr) => {
                        let message = 'Connection: ' + xhr.status + ' ' + xhr.statusText;

                        if (xhr.responseJSON && xhr.responseJSON.data) {
                            message = 'Server: ' + xhr.responseJSON.data;
                        }

                        LFE_UI.showToast(message, 'error');
                        $btn.prop('disabled', false).html(oldHtml);
                        console.error('LFE ERROR:', xhr);
                    }
                });
            });
        },

        runInjection: function() {
            const $preview = elementor.$previewContents;
            if (!$preview || !$preview.length) return;
            const $container = $preview.find('.elementor-add-new-section');
            $container.each((index, el) => {
                const $this = $(el);
                if ($this.find('.lfe-ai-btn').length > 0) return;
                const $btn = $('<button type="button" class="elementor-add-section-area-button lfe-ai-btn e-ai-layout-button e-button-primary" title="Generate Layout">' + LFE_ICON_SVG + '</button>');
                const $templateBtn = $this.find('.elementor-add-template-button');
                if ($templateBtn.length) { $templateBtn.after($btn); } else { $this.append($btn); }
                $btn.on('click', (e) => {
                    e.preventDefault(); e.stopPropagation();
                    this.insertAt = this.getInsertIndex($this);
                    $('#lfe-editor-modal-root').css('display', 'flex').hide().fadeIn(200);
                });
            });
        },

        setupObserver: function() {
            const $preview = elementor.$previewContents;
            if (!$preview || !$preview.length) return;
            if (this.observer) this.observer.disconnect();
            this.observer = new MutationObserver(() => this.runInjection());
            const previewDoc = document.getElementById('elementor-preview-iframe').contentDocument;
            if (previewDoc && previewDoc.body) { this.observer.observe(previewDoc.body, { childList: true, subtree: true }); }
        }
    };

    $(window).on('elementor:init', () => LayoutForElementor.init());

})(jQuery);
