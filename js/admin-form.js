function formatChange() {
    if (cj('#pdf_attach').val() === '0') {
        cj('span.preview-text').show();
        cj('span.preview-link').hide();
    } else {
        cj('span.preview-text').hide();
        cj('span.preview-link').show();
    }
}
