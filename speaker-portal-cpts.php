<?php
/**
 * Plugin Name: Speaker Portal – CPTs & Taxonomies
 * Description: Registers speaker/session/file post types and event/track taxonomies.
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
  // ----- Taxonomies -----
  register_taxonomy('event', ['speaker','session'], [
    'labels' => ['name' => 'Events', 'singular_name' => 'Event'],
    'public' => true,
    'show_ui' => true,
    'show_in_rest' => true,
    'hierarchical' => false,
    'rewrite' => ['slug' => 'event'],
  ]);

  register_taxonomy('track', ['session'], [
    'labels' => ['name' => 'Tracks', 'singular_name' => 'Track'],
    'public' => true,
    'show_ui' => true,
    'show_in_rest' => true,
    'hierarchical' => false,
    'rewrite' => ['slug' => 'track'],
  ]);

  // Common args helpers
  $supports_title_editor_author_rev = ['title','editor','author','revisions'];

  // Map core caps to custom caps (note create_posts → create_{plural})
  $cap_base = function($sing, $plur) {
    return [
      'edit_post'              => "edit_{$sing}",
      'read_post'              => "read_{$sing}",
      'delete_post'            => "delete_{$sing}",
      'edit_posts'             => "edit_{$plur}",
      'edit_others_posts'      => "edit_others_{$plur}",
      'publish_posts'          => "publish_{$plur}",
      'read_private_posts'     => "read_private_{$plur}",
      'delete_posts'           => "delete_{$plur}",
      'delete_private_posts'   => "delete_private_{$plur}",
      'delete_published_posts' => "delete_published_{$plur}",
      'delete_others_posts'    => "delete_others_{$plur}",
      'edit_private_posts'     => "edit_private_{$plur}",
      'edit_published_posts'   => "edit_published_{$plur}",
      'create_posts'           => "create_{$plur}",   // <-- important
    ];
  };

  // ----- speaker -----
  register_post_type('speaker', [
    'labels' => ['name'=>'Speakers','singular_name'=>'Speaker'],
    'public' => true,
    'show_ui' => true,
    'show_in_rest' => true,
    'has_archive' => false,
    'rewrite' => ['slug'=>'speaker'],
    'menu_position' => 20,
    'menu_icon' => 'dashicons-microphone',
    'supports' => array_merge($supports_title_editor_author_rev, ['thumbnail']),
    'taxonomies' => ['event'],
    'capability_type' => ['speaker','speakers'],
    'map_meta_cap' => true,
    'capabilities' => $cap_base('speaker','speakers'),
  ]);

  // ----- session -----
  register_post_type('session', [
    'labels' => ['name'=>'Sessions','singular_name'=>'Session'],
    'public' => true,
    'show_ui' => true,
    'show_in_rest' => true,
    'has_archive' => false,
    'rewrite' => ['slug'=>'session'],
    'menu_position' => 21,
    'menu_icon' => 'dashicons-groups',
    'supports' => $supports_title_editor_author_rev,
    'taxonomies' => ['event','track'],
    'capability_type' => ['session','sessions'],
    'map_meta_cap' => true,
    'capabilities' => $cap_base('session','sessions'),
  ]);

  // ----- speaker_file -----
  register_post_type('speaker_file', [
    'labels' => ['name'=>'Speaker Files','singular_name'=>'Speaker File'],
    'public' => false, // not publicly queryable
    'show_ui' => true,
    'show_in_rest' => true,
    'has_archive' => false,
    'rewrite' => false,
    'menu_position' => 22,
    'menu_icon' => 'dashicons-open-folder',
    'supports' => ['title','author','revisions'],
    'capability_type' => ['speaker_file','speaker_files'],
    'map_meta_cap' => true,
    'capabilities' => $cap_base('speaker_file','speaker_files'),
  ]);
});

// Speaker role with self-service caps (includes create_{plural})
add_action('init', function () {
  if (!get_role('speaker_role')) add_role('speaker_role', 'Speaker', ['read'=>true]);
  $role = get_role('speaker_role'); if (!$role) return;

  foreach (['speaker','session','speaker_file'] as $pt) {
    foreach ([
      "read_{$pt}", "edit_{$pt}", "delete_{$pt}",
      "edit_{$pt}s", "publish_{$pt}s",
      "read_private_{$pt}s", "delete_{$pt}s", "delete_published_{$pt}s",
      "edit_published_{$pt}s", "create_{$pt}s"
    ] as $cap) { $role->add_cap($cap); }
    // Do NOT grant edit_others_* or delete_others_* to speakers.
  }
}, 20);

// Ensure Administrators get all custom caps by default
add_action('admin_init', function () {
  $admin = get_role('administrator'); if (!$admin) return;
  foreach (['speaker','session','speaker_file'] as $pt) {
    foreach ([
      "read_{$pt}", "edit_{$pt}", "delete_{$pt}",
      "edit_{$pt}s", "edit_others_{$pt}s", "publish_{$pt}s",
      "read_private_{$pt}s", "delete_{$pt}s", "delete_private_{$pt}s",
      "delete_published_{$pt}s", "delete_others_{$pt}s",
      "edit_private_{$pt}s", "edit_published_{$pt}s",
      "create_{$pt}s"
    ] as $cap) { $admin->add_cap($cap); }
  }
}, 20);
