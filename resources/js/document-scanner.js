import { jsPDF } from 'jspdf';

const MAX_EDGE_PX = 2000;

/**
 * Converts a camera photo to a single-page A4 PDF in the browser, so the server
 * never needs image tooling (Imagick/Ghostscript) and only ever receives PDFs.
 */
async function imageToPdf(file) {
    const bitmap = await createImageBitmap(file);

    const scale = Math.min(1, MAX_EDGE_PX / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    const orientation = canvas.width > canvas.height ? 'landscape' : 'portrait';
    const pdf = new jsPDF({ orientation, unit: 'mm', format: 'a4' });

    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const ratio = Math.min(pageWidth / canvas.width, pageHeight / canvas.height);
    const width = canvas.width * ratio;
    const height = canvas.height * ratio;

    pdf.addImage(
        canvas.toDataURL('image/jpeg', 0.85),
        'JPEG',
        (pageWidth - width) / 2,
        (pageHeight - height) / 2,
        width,
        height,
    );

    return new File([pdf.output('blob')], `skan-${Date.now()}.pdf`, { type: 'application/pdf' });
}

export function initDocumentScanner(form) {
    const input = form.querySelector('input[type="file"]');
    const statusEl = form.querySelector('[data-scanner-status]');

    form.addEventListener('submit', async (event) => {
        const file = input.files?.[0];

        if (!file || file.type === 'application/pdf') {
            return;
        }

        event.preventDefault();
        statusEl.textContent = 'Przetwarzanie skanu…';

        try {
            const pdf = await imageToPdf(file);
            const transfer = new DataTransfer();
            transfer.items.add(pdf);
            input.files = transfer.files;
            statusEl.textContent = '';
            form.submit();
        } catch (error) {
            statusEl.textContent = 'Nie udało się przetworzyć zdjęcia. Spróbuj ponownie lub wybierz plik PDF.';
        }
    });
}
