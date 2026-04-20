document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('lfe_json_input');
    const validateBtn = document.getElementById('lfe-validate');
    const prettifyBtn = document.getElementById('lfe-prettify');
    const fixBtn = document.getElementById('lfe-fix');
    const form = document.getElementById('lfe-generate-form');
    const submitBtn = document.getElementById('lfe-submit');
    const copyHistoryBtns = document.querySelectorAll('.lfe-copy-history');

    if (prettifyBtn) {
        prettifyBtn.addEventListener('click', function() {
            if (!textarea) {
                return;
            }

            try {
                const obj = JSON.parse(textarea.value);
                textarea.value = JSON.stringify(obj, null, 4);
            } catch (e) {
                alert('Invalid JSON. Try "Fix JSON" first.');
            }
        });
    }

    if (validateBtn) {
        validateBtn.addEventListener('click', function() {
            if (!textarea) {
                return;
            }

            try {
                JSON.parse(textarea.value);
                alert('JSON is valid.');
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
            }
        });
    }

    if (fixBtn) {
        fixBtn.addEventListener('click', function() {
            if (!textarea) {
                return;
            }

            let content = textarea.value.trim();
            if (!content) {
                return;
            }

            // Basic common fixes for AI output.
            content = content.replace(/,(\s*[\]}])/g, '$1');

            if (content.startsWith('{') && content.endsWith('}') && !content.includes('\n[')) {
                if (content.match(/\}\s*\{/)) {
                    content = '[' + content.replace(/\}\s*\{/g, '},{') + ']';
                }
            }

            textarea.value = content;

            try {
                const obj = JSON.parse(content);
                textarea.value = JSON.stringify(obj, null, 4);
                alert('JSON fixed and formatted.');
            } catch (e) {
                alert('Could not fully fix JSON automatically.');
            }
        });
    }

    if (copyHistoryBtns.length) {
        copyHistoryBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const json = this.getAttribute('data-json');

                if (!navigator.clipboard || !navigator.clipboard.writeText) {
                    alert('Clipboard access is not available in this browser.');
                    return;
                }

                navigator.clipboard.writeText(json).then(() => {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';

                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);
                }).catch(() => {
                    alert('Could not copy JSON to the clipboard.');
                });
            });
        });
    }

    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<span class="dashicons dashicons-update lfe-spin"></span> Generating...';
        });
    }
});
