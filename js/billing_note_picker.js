/**
 * Billing note picker (includes/billing_note_picker.php) - the list beside the Notes box of an invoice, a quote or a
 * recurring invoice. Picking a note puts it into the box, after what is there and a blank line, and saves it the way
 * typing does (js/inline_notes.js saves when the box loses focus). Typing in the search narrows the list.
 */
function itflowBillingNotePicker(picker) {
    if (!picker || picker.dataset.notePickerReady) {
        return;
    }
    picker.dataset.notePickerReady = '1';

    const editor = document.getElementById(picker.dataset.editor);
    const search = picker.querySelector('[data-note-search]');
    const items = Array.prototype.slice.call(picker.querySelectorAll('[data-note-body]'));
    const none = picker.querySelector('[data-note-none]');
    const toggle = picker.querySelector('[data-bs-toggle="dropdown"]');

    function close() {
        if (toggle && window.bootstrap && bootstrap.Dropdown) {
            bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        }
    }

    if (search) {
        search.addEventListener('input', function () {
            const needle = search.value.trim().toLowerCase();
            let shown = 0;
            items.forEach(function (item) {
                const match = needle === '' || (item.dataset.noteSearchText || '').indexOf(needle) !== -1;
                item.hidden = !match;
                if (match) {
                    shown++;
                }
            });
            if (none) {
                none.hidden = shown !== 0;
            }
        });
    }

    const cancel = picker.querySelector('[data-note-close]');
    if (cancel) {
        cancel.addEventListener('click', close);
    }

    items.forEach(function (item) {
        item.addEventListener('click', function () {
            if (!editor) {
                return;
            }
            const existing = editor.value.replace(/\r\n?/g, '\n').replace(/\s+$/, '');
            const body = (item.dataset.noteBody || '').trim();
            editor.value = existing === '' ? body : existing + '\n\n' + body;
            close();
            editor.focus();
            editor.setSelectionRange(editor.value.length, editor.value.length);
            // js/inline_notes.js saves on blur - the text changed without a key pressed, so say it did
            editor.dispatchEvent(new Event('blur'));
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-note-picker]').forEach(itflowBillingNotePicker);
});
