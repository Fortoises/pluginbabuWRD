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

    if (typeof Swal === 'undefined' && window.babuSettings && window.babuSettings.sweetalert2_fallback) {
        console.warn('SweetAlert2 CDN failed, loading fallback');
        loadScript(window.babuSettings.sweetalert2_fallback, () => console.log('SweetAlert2 fallback loaded'));
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
                case 'warning':
                    backgroundColor = 'linear-gradient(to right, #ffc107, #ffca28)';
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

    document.addEventListener('DOMContentLoaded', function () {
        // Log library status
        console.log('SweetAlert2 status:', typeof Swal !== 'undefined' ? 'Loaded' : 'Not loaded');
        console.log('Toastify status:', typeof Toastify !== 'undefined' ? 'Loaded' : 'Not loaded');

        // Handle PHP notifications
        const notifications = document.querySelectorAll('#babu-notification');
        if (notifications.length) {
            notifications.forEach(notification => {
                const message = notification.getAttribute('data-message');
                const type = notification.getAttribute('data-type');
                console.log('Notification detected:', { message, type });
                if (message && type) {
                    showToast(message, type);
                    notification.style.display = 'none';
                }
            });
        } else {
            console.log('No PHP notifications found');
        }

        // Drag-and-drop upload with file name display
        const uploadArea = document.getElementById('babu-upload-area');
        const fileInput = document.getElementById('babu_file');
        if (uploadArea && fileInput) {
            const uploadText = uploadArea.querySelector('p');
            uploadArea.addEventListener('click', () => fileInput.click());
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('babu-upload-area-active');
            });
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('babu-upload-area-active');
            });
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('babu-upload-area-active');
                fileInput.files = e.dataTransfer.files;
                if (fileInput.files.length) {
                    uploadText.textContent = `File: ${fileInput.files[0].name}`;
                    console.log('File dropped:', fileInput.files[0].name);
                }
            });
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) {
                    uploadText.textContent = `File: ${fileInput.files[0].name}`;
                    console.log('File selected:', fileInput.files[0].name);
                } else {
                    uploadText.textContent = 'Drag & drop file TXT di sini atau klik untuk memilih';
                }
            });
            console.log('Upload area initialized');
        } else {
            console.error('Upload area or file input not found');
        }

        // Delete all babu
        const deleteAllForm = document.getElementById('delete-all-babu');
        if (deleteAllForm) {
            deleteAllForm.addEventListener('submit', function (e) {
                e.preventDefault();
                console.log('Delete all form triggered');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Hapus Semua Babu?',
                        text: 'Semua data babu akan dihapus permanen. Lanjutkan?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Hapus',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        customClass: {
                            popup: 'swal-modal',
                            title: 'swal-title',
                            content: 'swal-content',
                            confirmButton: 'swal-button-confirm',
                            cancelButton: 'swal-button-cancel'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            console.log('Submitting delete all form');
                            const formData = new FormData(this);
                            formData.append('delete_all', '1');
                            console.log('Delete all FormData:', [...formData.entries()]);
                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            }).then(response => {
                                console.log('Delete all response status:', response.status);
                                return response.text();
                            }).then(html => {
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                const notification = doc.querySelector('#babu-notification');
                                if (notification) {
                                    const message = notification.getAttribute('data-message');
                                    const type = notification.getAttribute('data-type');
                                    console.log('Delete all response notification:', { message, type });
                                    if (message && type) {
                                        showToast(message, type);
                                        if (type === 'success') {
                                            // Clear table instantly
                                            const tbody = document.querySelector('#babu-list');
                                            if (tbody) {
                                                tbody.innerHTML = '';
                                                console.log('Table cleared');
                                            }
                                        }
                                    }
                                } else {
                                    console.warn('No notification in delete all response');
                                    showToast('Aksi selesai', 'success');
                                }
                            }).catch(error => {
                                console.error('Delete all error:', error);
                                showToast('Error saat menghapus semua babu!', 'error');
                            });
                        }
                    });
                } else {
                    console.warn('SweetAlert2 not loaded, using confirm');
                    if (confirm('Hapus semua babu?')) {
                        const formData = new FormData(this);
                        formData.append('delete_all', '1');
                        console.log('Delete all FormData (fallback):', [...formData.entries()]);
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        }).then(response => response.text())
                          .then(html => {
                              const parser = new DOMParser();
                              const doc = parser.parseFromString(html, 'text/html');
                              const notification = doc.querySelector('#babu-notification');
                              if (notification) {
                                  const message = notification.getAttribute('data-message');
                                  const type = notification.getAttribute('data-type');
                                  console.log('Delete all response notification (fallback):', { message, type });
                                  if (message && type) {
                                      showToast(message, type);
                                      if (type === 'success') {
                                          const tbody = document.querySelector('#babu-list');
                                          if (tbody) {
                                              tbody.innerHTML = '';
                                              console.log('Table cleared (fallback)');
                                          }
                                      }
                                  }
                              } else {
                                  console.warn('No notification in delete all response (fallback)');
                                  showToast('Aksi selesai', 'success');
                              }
                          }).catch(error => {
                              console.error('Delete all error (fallback):', error);
                              showToast('Error saat menghapus semua babu!', 'error');
                          });
                    }
                }
            });
            console.log('Delete all form initialized');
        } else {
            console.error('Delete all form not found');
        }

        // Delete single babu
        const deleteForms = document.querySelectorAll('.delete-babu-form');
        if (deleteForms.length) {
            deleteForms.forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const babuName = this.getAttribute('data-babu-name');
                    const babuId = this.querySelector('input[name="babu_id"]').value;
                    console.log('Delete form triggered for:', { babuName, babuId });
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Hapus Babu?',
                            text: `Hapus '${babuName}' dari daftar?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Hapus',
                            cancelButtonText: 'Batal',
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            customClass: {
                                popup: 'swal-modal',
                                title: 'swal-title',
                                content: 'swal-content',
                                confirmButton: 'swal-button-confirm',
                                cancelButton: 'swal-button-cancel'
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                console.log('Submitting delete for:', babuName);
                                const formData = new FormData(this);
                                formData.append('delete_by_id', '1');
                                console.log('Delete single FormData:', [...formData.entries()]);
                                fetch(window.location.href, {
                                    method: 'POST',
                                    body: formData
                                }).then(response => {
                                    console.log('Delete single response status:', response.status);
                                    return response.text();
                                }).then(html => {
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(html, 'text/html');
                                    const notification = doc.querySelector('#babu-notification');
                                    if (notification) {
                                        const message = notification.getAttribute('data-message');
                                        const type = notification.getAttribute('data-type');
                                        console.log('Delete response notification:', { message, type });
                                        if (message && type) {
                                            showToast(message, type);
                                            if (type === 'success') {
                                                // Remove row instantly
                                                const row = form.closest('tr.babu-item');
                                                if (row) {
                                                    row.remove();
                                                    console.log(`Row removed for babu: ${babuName}`);
                                                }
                                            }
                                        }
                                    } else {
                                        console.warn('No notification in delete response');
                                        showToast('Aksi selesai', 'success');
                                    }
                                }).catch(error => {
                                    console.error('Delete error:', error);
                                    showToast(`Error saat menghapus ${babuName}!`, 'error');
                                });
                            }
                        });
                    } else {
                        console.warn('SweetAlert2 not loaded, using confirm');
                        if (confirm(`Hapus '${babuName}' dari daftar?`)) {
                            const formData = new FormData(this);
                            formData.append('delete_by_id', '1');
                            console.log('Delete single FormData (fallback):', [...formData.entries()]);
                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            }).then(response => response.text())
                              .then(html => {
                                  const parser = new DOMParser();
                                  const doc = parser.parseFromString(html, 'text/html');
                                  const notification = doc.querySelector('#babu-notification');
                                  if (notification) {
                                      const message = notification.getAttribute('data-message');
                                      const type = notification.getAttribute('data-type');
                                      console.log('Delete response notification (fallback):', { message, type });
                                      if (message && type) {
                                          showToast(message, type);
                                          if (type === 'success') {
                                              const row = form.closest('tr.babu-item');
                                              if (row) {
                                                  row.remove();
                                                  console.log(`Row removed for babu (fallback): ${babuName}`);
                                              }
                                          }
                                      }
                                  } else {
                                      console.warn('No notification in delete response (fallback)');
                                      showToast('Aksi selesai', 'success');
                                  }
                              }).catch(error => {
                                  console.error('Delete error (fallback):', error);
                                  showToast(`Error saat menghapus ${babuName}!`, 'error');
                              });
                        }
                    }
                });
            });
            console.log('Delete forms initialized:', deleteForms.length);
        } else {
            console.error('No delete forms found');
        }

        // Copy list
        window.babuCopyList = function () {
            const list = document.querySelectorAll('.babu-list .babu-item');
            let text = '';
            list.forEach(item => text += item.textContent + '\n');
            navigator.clipboard.writeText(text).then(() => {
                showToast('Daftar disalin!', 'success');
            }).catch(() => {
                showToast('Gagal menyalin daftar.', 'error');
            });
        };

        // Search
        window.babuSearch = function () {
            const input = document.getElementById('babu-search').value.toLowerCase();
            const items = document.querySelectorAll('#babu-list .babu-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name').toLowerCase();
                item.style.display = name.includes(input) ? '' : 'none';
            });
        };
    });
})();