import * as XLSX from 'xlsx';

/**
 * @param {unknown[][]} rows
 * @returns {unknown[][]}
 */
function normalizeRows(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
        return [];
    }
    let maxCols = 0;
    for (const r of rows) {
        if (Array.isArray(r)) {
            maxCols = Math.max(maxCols, r.length);
        }
    }
    return rows.map((r) => {
        const arr = Array.isArray(r)
            ? r.map((c) => (c === undefined || c === null ? '' : c))
            : [];
        while (arr.length < maxCols) {
            arr.push('');
        }
        return arr;
    });
}

function initProductImport() {
    const form = document.getElementById('product-import-form');
    const input = document.getElementById('product-import-file');
    const btn = form?.querySelector('button[type="submit"]');
    if (!form || !input || !btn) {
        return;
    }

    const url = form.dataset.importUrl;
    if (!url) {
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const file = input.files?.[0];
        if (!file) {
            window.alert('Choisissez un fichier.');
            return;
        }

        const maxBytes = 10 * 1024 * 1024;
        if (file.size > maxBytes) {
            window.alert('Fichier trop volumineux (max. 10 Mo).');
            return;
        }

        const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
        const allowed = ['xlsx', 'csv', 'txt'];
        if (!allowed.includes(ext)) {
            window.alert('Formats acceptés : .xlsx, .csv, .txt');
            return;
        }

        const token = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');
        if (!token) {
            window.alert('Jeton CSRF introuvable. Rechargez la page.');
            return;
        }

        btn.disabled = true;
        const label = btn.textContent;
        btn.textContent = 'Importation…';

        try {
            const buf = await file.arrayBuffer();
            const wb = XLSX.read(buf, { type: 'array', cellDates: true });
            const sheetName = wb.SheetNames[0];
            if (!sheetName) {
                throw new Error('Aucune feuille dans le classeur.');
            }
            const sheet = wb.Sheets[sheetName];
            const rows = XLSX.utils.sheet_to_json(sheet, {
                header: 1,
                defval: '',
                raw: true,
            });
            const normalized = normalizeRows(rows);
            if (normalized.length === 0) {
                throw new Error('Fichier vide.');
            }

            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/html,application/xhtml+xml',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ rows: normalized }),
                redirect: 'manual',
                credentials: 'same-origin',
            });

            if (res.status === 419) {
                window.alert('Session expirée. Rechargez la page puis réessayez.');
                return;
            }

            if (res.status === 302 || res.status === 303) {
                const loc = res.headers.get('Location');
                if (loc) {
                    window.location.href = loc;
                } else {
                    window.location.reload();
                }
                return;
            }

            if (res.ok) {
                window.location.reload();
                return;
            }

            throw new Error(`Réponse du serveur inattendue (${res.status}).`);
        } catch (err) {
            console.error(err);
            window.alert(
                err instanceof Error
                    ? err.message
                    : 'Impossible de lire ou d’importer ce fichier.',
            );
        } finally {
            btn.disabled = false;
            btn.textContent = label;
        }
    });
}

initProductImport();
