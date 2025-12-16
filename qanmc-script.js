/**
 * Quick Admin Notes - JavaScript
 * Handles note card interactions and AJAX operations
 */

jQuery(document).ready(function ($) {

    const container = $('#qanmc-notes-container');

    // Delete note with confirmation
    container.on('click', '.qanmc-delete-note', function (e) {
        e.preventDefault();

        if (!confirm(qanmc_ajax.i18n.delete_confirm || 'Are you sure you want to delete this note?')) {
            return;
        }

        const noteDiv = $(this).closest('.qanmc-note');
        const noteId = noteDiv.data('id');

        $.post(qanmc_ajax.ajax_url, {
            action: 'qanmc_delete_note',
            security: qanmc_ajax.nonce,
            note_id: noteId
        }, function (response) {
            if (response.success) {
                noteDiv.fadeOut(300, function () {
                    $(this).remove();

                    // Show empty state if no notes left
                    if (container.find('.qanmc-note[data-type="text"]').length === 0) {
                        container.append('<p class="qanmc-empty-state">No notes yet. Click "Add New Note" to get started!</p>');
                    }
                });
                showStatus(response.data, '#0073aa');
            } else {
                alert('Error deleting note: ' + response.data);
            }
        }).fail(function () {
            alert(qanmc_ajax.i18n.network_error);
        });
    });


    //const container = $('#qanmc-notes-container');
    const status = $('#qanmc-status');
    const emptyState = container.find('.qanmc-empty-state');
    // Helper to show status
    function showStatus(text, color) {
        status.stop(true, true).css('color', color || '#0073aa').text(text).show();
        setTimeout(() => status.fadeOut(300, function () { $(this).empty().show(); }), 2000);
    }

    // Per-note save (debounced per note)
    const saveTimers = {};
    function saveNoteDebounced($note) {
        const id = $note.data('id');
        clearTimeout(saveTimers[id]);
        saveTimers[id] = setTimeout(() => saveNote($note), 1200);
    }



    function saveNote($note) {
        const id = $note.data('id');
        const type = $note.data('type') || 'text';
        showStatus(qanmc_ajax.i18n.saving, '#0073aa');



        if (type === 'todo') {
            // collect items
            const items = [];
            $note.find('.qanmc-todo-item').each(function () {
                const $li = $(this);
                const text = $li.find('.qanmc-todo-text').text();
                const checked = $li.find('.qanmc-todo-checkbox').is(':checked') ? 1 : 0;
                items.push({ text: text, checked: checked });
            });

            $.post(qanmc_ajax.ajax_url, {
                action: 'qanmc_save_note',
                security: qanmc_ajax.nonce,
                note_id: id,
                type: 'todo',
                items: items
            }, function (response) {
                if (response.success) {
                    showStatus(response.data, '#0073aa');
                } else {
                    showStatus('Error: ' + response.data, '#d63638');
                }
            }).fail(function () {
                showStatus(qanmc_ajax.i18n.network_error, '#d63638');
            });
        } else {
            const text = $note.find('textarea').val();
            $.post(qanmc_ajax.ajax_url, {
                action: 'qanmc_save_note',
                security: qanmc_ajax.nonce,
                note_id: id,
                type: 'text',
                text: text
            }, function (response) {
                if (response.success) {
                    showStatus(response.data, '#0073aa');
                } else {
                    showStatus('Error: ' + response.data, '#d63638');
                }
            }).fail(function () {
                showStatus(qanmc_ajax.i18n.network_error, '#d63638');
            });
        }
    }

    // Save when text changes (per-note)
    container.on('input', '.qanmc-note-text', function () {
        const $note = $(this).closest('.qanmc-note');
        saveNoteDebounced($note);
    });

    // Add new note
    $('#qanmc-add-note').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);
        const type = $('#qanmc-new-note-type').val() || 'text';
        $button.prop('disabled', true).text(qanmc_ajax.i18n.adding);

        $.post(qanmc_ajax.ajax_url, {
            action: 'qanmc_add_note',
            security: qanmc_ajax.nonce,
            type: type
        }, function (response) {
            if (response.success) {
                const newId = parseInt(response.data.id, 10) || 0;
                if (newId <= 0) {
                    alert('Failed to create note (invalid id)');
                    return;
                }
                emptyState.remove();

                let $new;
                if (type === 'todo') {
                    $new = $('<div/>', { 'class': 'qanmc-note qanmc-note-new', 'data-id': newId, 'data-type': 'todo' });
                    $new.append($('<ul/>', { 'class': 'qanmc-todo-list' }));
                    $new.append($('<button/>', { 'class': 'qanmc-add-todo-item button', text: qanmc_ajax.i18n.add_item || 'Add item' }));
                    $new.append($('<button/>', { 'class': 'qanmc-delete-note button button-link', title: 'Delete this note', text: 'Delete' }));
                } else {
                    $new = $('<div/>', { 'class': 'qanmc-note qanmc-note-new', 'data-id': newId, 'data-type': 'text' });
                    $new.append($('<textarea/>', { 'class': 'qanmc-note-text', placeholder: qanmc_ajax.i18n.placeholder || 'Type your note here...' }));

                    const $actions = $('<div class="qanmc-note-actions"></div>');
                    $actions.append($('<button/>', { 'class': 'qanmc-share-note button button-link', title: 'Share this note', text: 'Share' }));
                    $actions.append($('<button/>', { 'class': 'qanmc-delete-note button button-link', title: 'Delete this note', text: 'Delete' }));
                    $new.append($actions);
                }

                container.append($new);

                // Focus and remove marker
                setTimeout(() => {
                    const $el = container.find('.qanmc-note-new').removeClass('qanmc-note-new');
                    if (type === 'text') $el.find('textarea').focus();
                }, 100);
            } else {
                alert('Failed to add note: ' + response.data);
            }
        }).fail(function () {
            alert(qanmc_ajax.i18n.network_error);
        }).always(function () {
            $button.prop('disabled', false).text(qanmc_ajax.i18n.add_new || 'Add New Note');
        });
    });


    // Todo item interactions: add, delete, toggle
    container.on('click', '.qanmc-add-todo-item', function (e) {
        e.preventDefault();
        const $note = $(this).closest('.qanmc-note');
        const $list = $note.find('.qanmc-todo-list');
        const idx = $list.find('.qanmc-todo-item').length;
        // No label: checkbox and text are siblings
        const $li = $('<li/>', { 'class': 'qanmc-todo-item', 'data-idx': idx });
        $li.append($('<input/>', { type: 'checkbox', 'class': 'qanmc-todo-checkbox' }));
        $li.append($('<span/>', { 'class': 'qanmc-todo-text', contenteditable: true, text: 'New item' }));
        $li.append($('<button/>', { 'class': 'qanmc-delete-todo-item button-link', text: 'Delete' }));
        $list.append($li);
        saveNoteDebounced($note);
    });

    container.on('click', '.qanmc-delete-todo-item', function (e) {
        e.preventDefault();
        const $note = $(this).closest('.qanmc-note');
        $(this).closest('.qanmc-todo-item').remove();
        saveNoteDebounced($note);
    });

    container.on('change', '.qanmc-todo-checkbox', function () {
        const $note = $(this).closest('.qanmc-note');
        saveNoteDebounced($note);
    });

    // Prevent clicking the todo text from toggling the checkbox, but allow editing
    container.on('click', '.qanmc-todo-text', function (e) {
        e.stopPropagation();
    });

    container.on('input', '.qanmc-todo-text', function () {
        const $note = $(this).closest('.qanmc-note');
        saveNoteDebounced($note);
    });

    // Sharing Functionality
    container.on('click', '.qanmc-share-note', function (e) {
        e.preventDefault();
        const $note = $(this).closest('.qanmc-note');
        const noteId = $note.data('id');
        let currentShared = $note.data('shared') || [];

        // Build Modal
        const $overlay = $('<div class="qanmc-modal-overlay"></div>');
        const $modal = $('<div class="qanmc-modal"></div>');
        $modal.append('<h3>' + (qanmc_ajax.i18n.share_title || 'Share with...') + '</h3>');

        const $list = $('<div class="qanmc-user-list"></div>');

        if (qanmc_ajax.users && qanmc_ajax.users.length) {
            qanmc_ajax.users.forEach(function (u) {
                const isChecked = currentShared.includes(parseInt(u.id)) ? 'checked' : '';
                const $item = $('<div class="qanmc-user-item"></div>');
                $item.append('<input type="checkbox" id="u-' + u.id + '" value="' + u.id + '" ' + isChecked + '>');
                $item.append('<label for="u-' + u.id + '">' + u.name + '</label>');
                $list.append($item);
            });
        } else {
            $list.append('<p>No other admins found.</p>');
        }

        $modal.append($list);

        const $actions = $('<div class="qanmc-modal-actions"></div>');
        const $cancel = $('<button class="button">Cancel</button>');
        const $save = $('<button class="button button-primary">' + (qanmc_ajax.i18n.save_sharing || 'Save Sharing') + '</button>');

        $actions.append($cancel).append($save);
        $modal.append($actions);
        $overlay.append($modal);
        $('body').append($overlay);

        // Modal Events
        $cancel.on('click', function () { $overlay.remove(); });

        $save.on('click', function () {
            const selectedIds = [];
            $list.find('input:checked').each(function () {
                selectedIds.push($(this).val());
            });

            $save.text('Saving...').prop('disabled', true);

            $.post(qanmc_ajax.ajax_url, {
                action: 'qanmc_update_sharing',
                security: qanmc_ajax.nonce,
                note_id: noteId,
                shared_ids: selectedIds
            }, function (response) {
                if (response.success) {
                    $note.data('shared', selectedIds.map(Number));
                    $overlay.remove();
                    showStatus(response.data, '#0073aa');
                } else {
                    alert('Error: ' + response.data);
                    $save.text(qanmc_ajax.i18n.save_sharing).prop('disabled', false);
                }
            }).fail(function () {
                alert(qanmc_ajax.i18n.network_error);
                $save.text(qanmc_ajax.i18n.save_sharing).prop('disabled', false);
            });
        });

        // Close on overlay click
        $overlay.on('click', function (e) {
            if ($(e.target).is($overlay)) $overlay.remove();
        });
    });

});