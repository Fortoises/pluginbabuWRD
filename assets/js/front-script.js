(function () {
    // Load fallback if CDN fails
    function loadScript(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        script.onload = callback;
        script.onerror = () => console.error(`Failed to load script: ${src}`);
        document.head.appendChild(script);
    }

    if (typeof Toastify === 'undefined' && window.babuSettings && window.babuSettings.toastify_fallback) {
        console.warn('Toastify CDN failed, loading fallback');
        loadScript(window.babuSettings.toastify_fallback, () => console.log('Toastify fallback loaded'));
    }

    function showToast(message, type) {
        if (typeof Toastify !== 'undefined') {
            let backgroundColor;
            switch (type.toLowerCase()) {
                case 'success':
                    backgroundColor = 'linear-gradient(to right, #28a745, #2ecc71)';
                    break;
                case 'error':
                    backgroundColor = 'linear-gradient(to right, #dc3545, #ff6b6b)';
                    break;
                default:
                    backgroundColor = 'linear-gradient(to right, #6c757d, #adb5bd)';
            }
            Toastify({
                text: message,
                duration: 3500,
                gravity: 'top',
                position: 'right',
                backgroundColor: backgroundColor,
                stopOnFocus: false,
                style: {
                    borderRadius: '8px',
                    padding: '12px 20px',
                    fontSize: '14px',
                    fontWeight: '500',
                    fontFamily: '-apple-system, BlinkMacSystemFont, sans-serif',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                }
            }).showToast();
            console.log(`Toast shown: [${type}] ${message}`);
        } else {
            console.warn('Toastify not loaded, using inline fallback');
            const toastDiv = document.createElement('div');
            toastDiv.className = `babu-toast babu-toast-${type.toLowerCase()}`;
            toastDiv.textContent = message;
            document.body.appendChild(toastDiv);
            setTimeout(() => toastDiv.remove(), 3500);
        }
    }

    window.babuCopyList = function () {
        const items = document.querySelectorAll('.babu-list .babu-item');
        let text = '';
        items.forEach(item => text += item.textContent + '\n');
        navigator.clipboard.writeText(text).then(() => {
            showToast('Daftar disalin!', 'success');
        }).catch(() => {
            showToast('Gagal menyalin daftar.', 'error');
        });
    };

    window.babuSearch = function () {
        const input = document.getElementById('babu-search').value.toLowerCase();
        const items = document.querySelectorAll('.babu-list .babu-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(input) ? '' : 'none';
        });
    };
})();