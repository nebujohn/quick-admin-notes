<?php
/**
 * Plugin Name: Quick Admin Notes
 * Plugin URI: https://breathwp.com/quick-admin-notes
 * Description: Add multiple note cards to your WordPress dashboard with add/edit/delete support. Perfect for quick reminders, to-dos, and team messages.
 * Version: 1.2.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Nebu John
 * Author URI: https://breathwp.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quick-admin-notes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path(__FILE__) . 'qanmc-users-helper.php';

// Register dashboard widget
add_action('wp_dashboard_setup', 'qanmc_register_dashboard_widget');
function qanmc_register_dashboard_widget() {
    if ( current_user_can( 'manage_options' ) ) {
        wp_add_dashboard_widget(
            'qanmc_dashboard_notes',
            __('Quick Admin Notes', 'quick-admin-notes'),
            'qanmc_render_widget'
        );
    }
}

// Enqueue JS and CSS
add_action('admin_enqueue_scripts', 'qanmc_enqueue_assets');
function qanmc_enqueue_assets($hook) {



    if ( 'index.php' !== $hook ) return; // dashboard only
    
    wp_enqueue_script(
        'qanmc-js',
        plugin_dir_url(__FILE__) . 'qanmc-script.js?ver=1.1',
        ['jquery'],
        '1.1.0',
        true
    );
    
    wp_localize_script('qanmc-js', 'qanmc_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('qanmc_nonce'),
        'i18n' => [
            'adding' => __('Adding...', 'quick-admin-notes'),
            'add_new' => __('Add New Note', 'quick-admin-notes'),
            'saving' => __('Saving...', 'quick-admin-notes'),
            'delete_confirm' => __('Are you sure you want to delete this note?', 'quick-admin-notes'),
            'network_error' => __('Network error. Please try again.', 'quick-admin-notes'),
            'add_item' => __('Add item', 'quick-admin-notes'),
            'placeholder' => __('Type your note here...', 'quick-admin-notes'),
            'share_title' => __('Share with...', 'quick-admin-notes'),
            'save_sharing' => __('Save Sharing', 'quick-admin-notes'),
        ],
        'users' => qanmc_get_users_for_sharing()
    ]);
    
    wp_enqueue_style(
        'qanmc-css',
        plugin_dir_url(__FILE__) . 'qanmc-style.css?ver=1.2',
        [],
        '1.1.0'
    );

}

// Render widget HTML
function qanmc_render_widget() {


    // Query notes stored as custom post type 'qanmc_note'
    // Always show one permanent todo card
    $todo_post = null;
    $posts = get_posts([
        'post_type' => 'qanmc_note',
        'post_status' => 'private',
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    foreach ($posts as $post) {
        $type = get_post_meta($post->ID, 'qanmc_note_type', true);
        if ($type === 'todo') {
            $todo_post = $post;
            break;
        }
    }
    // If no todo card exists, create one
    if (!$todo_post) {
        $todo_id = wp_insert_post([
            'post_type' => 'qanmc_note',
            'post_status' => 'private',
            'post_title' => '',
            'post_content' => '',
        ]);
        update_post_meta($todo_id, 'qanmc_note_type', 'todo');
        update_post_meta($todo_id, 'qanmc_todo_items', []);
        $todo_post = get_post($todo_id);
    }

    echo '<div id="qanmc-notes-container">';

    // Render permanent todo card
    if ($todo_post) {
        $note_id = $todo_post->ID;
        $items = get_post_meta($note_id, 'qanmc_todo_items', true);
        if (!is_array($items)) $items = [];
        echo '<div class="qanmc-note" data-id="' . esc_attr($note_id) . '" data-type="todo">';
        echo '<ul class="qanmc-todo-list">';
        foreach ($items as $idx => $item) {
            $checked = !empty($item['checked']) ? 'checked' : '';
            $text = isset($item['text']) ? esc_html($item['text']) : '';
            echo '<li class="qanmc-todo-item" data-idx="' . intval($idx) . '">';
            echo '<input type="checkbox" class="qanmc-todo-checkbox" ' . checked( $value, 1, false ) . '> ';
            echo '<span class="qanmc-todo-text" contenteditable="true">' . esc_html( $text ) . '</span> ';
            echo '<button class="qanmc-delete-todo-item button-link">' . esc_html__('Delete', 'quick-admin-notes') . '</button>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<button class="qanmc-add-todo-item button">' . esc_html__('Add item', 'quick-admin-notes') . '</button>';
        echo '</div>';
    }

    // Render all text notes
    $text_notes = array_filter($posts, function($post) {
        return get_post_meta($post->ID, 'qanmc_note_type', true) !== 'todo';
    });
    // Sort by post_date ascending (oldest first, newest last)
    usort($text_notes, function($a, $b) {
        return strtotime($a->post_date) <=> strtotime($b->post_date);
    });
    if (empty($text_notes)) {
        echo '<p class="qanmc-empty-state">' . esc_html__('No notes yet. Click "Add New Note" to get started!', 'quick-admin-notes') . '</p>';
    }
    foreach ($text_notes as $post) {
        // Visibility check
        $current_user_id = get_current_user_id();
        $author_id = (int) $post->post_author;
        $shared_with = get_post_meta($post->ID, 'qanmc_shared_with', true);
        if (!is_array($shared_with)) $shared_with = [];
        
        // Show if: Author OR in shared list
        if ($author_id !== $current_user_id && !in_array($current_user_id, $shared_with)) {
            continue;
        }
        
        $note_id = $post->ID;
        // serialize shared_with for JS data attr
        $shared_json = esc_attr(json_encode($shared_with));
        
        echo '<div class="qanmc-note" data-id="' . esc_attr($note_id) . '" data-type="text" data-shared="' . esc_attr( $shared_json ). '">';
        echo '<textarea class="qanmc-note-text" placeholder="' . esc_attr__('Type your note here...', 'quick-admin-notes') . '">' . esc_textarea($post->post_content) . '</textarea>';
        echo '<div class="qanmc-note-actions">';
        // Only author can share/delete
        if ($author_id === $current_user_id) {
            echo '<button class="qanmc-share-note button button-link" title="' . esc_attr__('Share this note', 'quick-admin-notes') . '">' . esc_html__('Share', 'quick-admin-notes') . '</button>';
            echo '<button class="qanmc-delete-note button button-link" title="' . esc_attr__('Delete this note', 'quick-admin-notes') . '">' . esc_html__('Delete', 'quick-admin-notes') . '</button>';
        } else {
             echo '<span class="qanmc-shared-badge" title="' . esc_attr__('Shared by ', 'quick-admin-notes') . esc_attr( get_the_author_meta('display_name', $author_id) ) . '">Using Shared Note</span>';
        }
        echo '</div>'; // end actions
        echo '</div>';
    }

    echo '</div>';
    echo '<p class="qanmc-actions">';
    echo '<button id="qanmc-add-note" class="button button-primary">' . esc_html__('Add New Note', 'quick-admin-notes') . '</button> ';
    echo '<span id="qanmc-status"></span>';
    echo '</p>';
}

// AJAX: Save all notes
// AJAX: Save a single note (per-note autosave)
add_action('wp_ajax_qanmc_save_note', 'qanmc_save_note');
function qanmc_save_note() {
    check_ajax_referer('qanmc_nonce', 'security');

    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(__('Permission denied', 'quick-admin-notes'));
    }

    $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
    $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'text';

    if ( $note_id <= 0 ) {
        wp_send_json_error(__('Invalid note ID', 'quick-admin-notes'));
    }



    if ( 'todo' === $type ) {
        $items = [];
        if ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) {
            $items = array_map(
                'sanitize_text_field',
                wp_unslash( $_POST['items'] )
            );
        }

        $sanitized = [];
        if ( is_array($items) ) {
            foreach ( $items as $item ) {
                $sanitized[] = [
                    'text' => isset($item['text']) ? sanitize_text_field($item['text']) : '',
                    'checked' => ! empty($item['checked']) ? 1 : 0,
                ];
            }
        }

        update_post_meta($note_id, 'qanmc_todo_items', $sanitized);
        wp_send_json_success(__('To-do saved', 'quick-admin-notes'));
    } else {
        $text = isset($_POST['text']) ? sanitize_textarea_field(wp_unslash( $_POST['text'])): '';
        $post = [
            'ID' => $note_id,
            'post_content' => $text,
        ];
        wp_update_post($post);
        wp_send_json_success(__('Note saved', 'quick-admin-notes'));
    }
}

// AJAX: Add new note
add_action('wp_ajax_qanmc_add_note', 'qanmc_add_note');
function qanmc_add_note() {
    check_ajax_referer('qanmc_nonce', 'security');
    
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(__('Permission denied', 'quick-admin-notes'));
    }

    $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'text';

    $postarr = [
        'post_type' => 'qanmc_note',
        'post_status' => 'private',
        'post_title' => '',
        'post_content' => '',
    ];

    $new_id = wp_insert_post($postarr);
    if ( is_wp_error($new_id) || ! $new_id ) {
        wp_send_json_error(__('Could not create note', 'quick-admin-notes'));
    }

    update_post_meta($new_id, 'qanmc_note_type', $type);
    if ( 'todo' === $type ) {
        update_post_meta($new_id, 'qanmc_todo_items', []);
    }



    wp_send_json_success(['id' => $new_id]);
}

// AJAX: Delete note
add_action('wp_ajax_qanmc_delete_note', 'qanmc_delete_note');
function qanmc_delete_note() {
    check_ajax_referer('qanmc_nonce', 'security');
    
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(__('Permission denied', 'quick-admin-notes'));
    }
    $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
    if ( $note_id <= 0 ) {
        wp_send_json_error(__('Invalid note ID', 'quick-admin-notes'));
    }

    $deleted = wp_delete_post($note_id, true);
    if ( $deleted ) {
        wp_send_json_success(__('Note deleted', 'quick-admin-notes'));
    }

    wp_send_json_error(__('Note not found', 'quick-admin-notes'));
}

// AJAX: Update sharing
add_action('wp_ajax_qanmc_update_sharing', 'qanmc_update_sharing');
function qanmc_update_sharing() {
    check_ajax_referer('qanmc_nonce', 'security');
    
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(__('Permission denied', 'quick-admin-notes'));
    }
    
    $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
    if ( $note_id <= 0 ) {
        wp_send_json_error(__('Invalid note ID', 'quick-admin-notes'));
    }
    
    // Verify ownership
    $post = get_post($note_id);
    if ( ! $post || $post->post_author != get_current_user_id() ) {
        wp_send_json_error(__('You cannot share this note', 'quick-admin-notes'));
    }
    
    $shared_ids = isset($_POST['shared_ids']) ? array_map('intval', $_POST['shared_ids']) : [];
    
    update_post_meta($note_id, 'qanmc_shared_with', $shared_ids);
    
    wp_send_json_success(__('Sharing updated', 'quick-admin-notes'));
}

// Cleanup on uninstall
register_uninstall_hook(__FILE__, 'qanmc_uninstall');
function qanmc_uninstall() {
    delete_option('qanmc_notes');
}