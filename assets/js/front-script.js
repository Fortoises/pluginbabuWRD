function babuCopyList() {
    const list = document.querySelectorAll('.babu-list .babu-item');
    let text = '';
    list.forEach(item => text += item.textContent + '\n');
    navigator.clipboard.writeText(text).then(() => {
        alert('Daftar Babu disalin!');
    });
}

function babuSearch() {
    const input = document.getElementById('babu-search').value.toLowerCase();
    const items = document.querySelectorAll('.babu-list .babu-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(input) ? '' : 'none';
    });
}