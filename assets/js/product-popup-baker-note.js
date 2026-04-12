/**
 * Product popup: baker note line — same UX as checkout order notes (inline add / edit).
 * Syncs visible line with hidden #popup-product-note (product_note for add-to-cart).
 */
(function () {
    'use strict';

    const ADD_LINK_HTML = 'הוספת הערה לקצב &gt;';

    function getHidden(popupRoot) {
        return popupRoot ? popupRoot.querySelector('#popup-product-note') : null;
    }

    function isEditing(popupRoot) {
        const valueEl = popupRoot.querySelector('.js-popup-product-notes');
        return !!(valueEl && valueEl.querySelector('textarea.popup-product-notes-inline-input'));
    }

    /**
     * Refresh the summary line from the hidden textarea (source of truth for product_note).
     */
    function syncFromHidden(popupRoot) {
        if (!popupRoot) return;
        const hidden = getHidden(popupRoot);
        const valueEl = popupRoot.querySelector('.js-popup-product-notes');
        const editBtn = popupRoot.querySelector('.js-popup-product-notes-edit');
        if (!hidden || !valueEl) return;
        if (isEditing(popupRoot)) return;

        const notes = (hidden.value || '').trim();
        if (notes) {
            const shortNotes = notes.length > 50 ? notes.substring(0, 50) + '...' : notes;
            if (editBtn) {
                editBtn.hidden = false;
            }
            valueEl.classList.remove('is-add-note');
            valueEl.innerHTML = '';
            valueEl.appendChild(document.createTextNode(shortNotes));
        } else {
            if (editBtn) {
                editBtn.hidden = true;
            }
            valueEl.classList.add('is-add-note');
            valueEl.innerHTML = ADD_LINK_HTML;
        }
    }

    function beginInline(popupRoot, initialValue) {
        const valueEl = popupRoot.querySelector('.js-popup-product-notes');
        const hidden = getHidden(popupRoot);
        if (!valueEl || !hidden) return;

        valueEl.classList.remove('is-add-note');
        valueEl.innerHTML = '';
        const ta = document.createElement('textarea');
        ta.className = 'popup-product-notes-inline-input checkout-notes-inline-input';
        ta.setAttribute('rows', '2');
        ta.placeholder = 'הוספת הערה לקצב';
        ta.value = initialValue != null ? initialValue : '';
        valueEl.appendChild(ta);

        const editBtn = popupRoot.querySelector('.js-popup-product-notes-edit');
        if (editBtn) {
            editBtn.hidden = true;
        }

        requestAnimationFrame(function () {
            try {
                ta.focus();
                const len = ta.value.length;
                ta.setSelectionRange(len, len);
            } catch (e) { /* ignore */ }
        });
    }

    function commitInline(popupRoot, ta) {
        const hidden = getHidden(popupRoot);
        if (!hidden || !ta) return;
        hidden.value = (ta.value || '').trim();
        syncFromHidden(popupRoot);
    }

    document.addEventListener('click', function (e) {
        const popup = e.target.closest('#ed-product-popup');
        if (!popup) return;

        const editHit = e.target.closest('.js-popup-product-notes-edit');
        if (editHit) {
            e.preventDefault();
            const hidden = getHidden(popup);
            beginInline(popup, hidden ? hidden.value : '');
            return;
        }

        const addHit = e.target.closest('.js-popup-product-notes.is-add-note');
        if (addHit && !e.target.closest('textarea')) {
            if (isEditing(popup)) return;
            const hidden = getHidden(popup);
            beginInline(popup, hidden ? hidden.value : '');
        }
    });

    document.addEventListener(
        'blur',
        function (e) {
            const ta = e.target.closest && e.target.closest('#ed-product-popup textarea.popup-product-notes-inline-input');
            if (!ta) return;
            const popup = ta.closest('#ed-product-popup');
            if (!popup) return;
            commitInline(popup, ta);
        },
        true
    );

    document.addEventListener('keydown', function (e) {
        const ta = e.target.closest && e.target.closest('#ed-product-popup textarea.popup-product-notes-inline-input');
        if (!ta || !ta.closest('#ed-product-popup')) return;
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            ta.blur();
        }
    });

    document.addEventListener('keydown', function (e) {
        const valueEl = e.target.closest && e.target.closest('.js-popup-product-notes.is-add-note');
        if (!valueEl || !valueEl.closest('#ed-product-popup')) return;
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const popup = valueEl.closest('#ed-product-popup');
            if (popup && !isEditing(popup)) {
                const hidden = getHidden(popup);
                beginInline(popup, hidden ? hidden.value : '');
            }
        }
    });

    window.EDProductPopupBakerNote = {
        syncFromHidden: syncFromHidden
    };

    document.addEventListener('DOMContentLoaded', function () {
        const p = document.getElementById('ed-product-popup');
        if (p) {
            syncFromHidden(p);
        }
    });
})();
