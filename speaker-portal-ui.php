<?php
/**
 * Plugin Name: Speaker Portal – Frontend UI (ACF)
 * Description: Single shortcode [speaker_portal] with Profile / Sessions / Files using ACF forms.
 */
if (!defined('ABSPATH')) exit;

/** Map current user → their Speaker post (create if missing) */
function spk_get_or_create_speaker() {
  $u = get_current_user_id(); if (!$u) return 0;
  $id = get_posts(['post_type'=>'speaker','author'=>$u,'numberposts'=>1,'fields'=>'ids','post_status'=>['publish','draft','pending']]);
  if ($id) return $id[0];
  return wp_insert_post([
    'post_type'=>'speaker','post_status'=>'draft','post_author'=>$u,
    'post_title'=>wp_get_current_user()->display_name ?: 'Speaker'
  ]);
}

/** Shortcode */
add_shortcode('speaker_portal', function () {
  if (!is_user_logged_in()) {
    return '<div class="spk-alert">Please <a href="'.esc_url(wp_login_url(get_permalink())).'">log in</a> to access the Speaker Portal.</div>';
  }
  if (!function_exists('acf_form')) {
    return '<div class="spk-alert">ACF Pro is required.</div>';
  }

  // Handle delete (sessions/files) author-only
  if (!empty($_GET['spk_del']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'spk_del_'.(int)$_GET['spk_del'])) {
    $pid = (int) $_GET['spk_del'];
    if ((int)get_post_field('post_author',$pid) === get_current_user_id()) wp_trash_post($pid);
    wp_safe_redirect(remove_query_arg(['spk_del','_wpnonce'])); exit;
  }

  $speaker_id = spk_get_or_create_speaker();

  ob_start(); ?>
  <style>
    .spk-wrap{max-width:1100px;margin:0 auto;font:400 16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
    .spk-tabs{display:flex;gap:.5rem;border-bottom:1px solid #e5e7eb;margin-bottom:1rem;position:sticky;top:0;background:#fff;padding-top:.5rem;z-index:1}
    .spk-tab{border:1px solid #e5e7eb;border-bottom:none;padding:.55rem .9rem;border-radius:.5rem .5rem 0 0;background:#f8fafc;cursor:pointer}
    .spk-tab[aria-selected="true"]{background:#fff;border-color:#cbd5e1;font-weight:600}
    .spk-pane{display:none}.spk-pane[data-active="true"]{display:block}
    .spk-card{border:1px solid #e5e7eb;border-radius:.75rem;padding:1rem;background:#fff;margin-bottom:1rem}
    .spk-list{list-style:none;margin:0;padding:0}
    .spk-row{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9;padding:.5rem .25rem}
    .spk-row:last-child{border-bottom:none}
    .spk-alert{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:.6rem .8rem;border-radius:.5rem;margin:.5rem 0}
  </style>

  <div class="spk-wrap" id="speaker-portal">
    <div class="spk-tabs" role="tablist">
      <button class="spk-tab" role="tab" aria-selected="true"  data-target="#spk-profile">Profile</button>
      <button class="spk-tab" role="tab" aria-selected="false" data-target="#spk-sessions">Sessions</button>
      <button class="spk-tab" role="tab" aria-selected="false" data-target="#spk-files">Files</button>
    </div>

    <!-- PROFILE -->
    <section id="spk-profile" class="spk-pane" data-active="true" role="tabpanel">
      <div class="spk-card">
        <?php acf_form([
          'post_id' => $speaker_id,
          'field_groups' => ['group_speaker_profile'], // keep these keys
          'submit_value' => 'Save Profile',
          'updated_message' => 'Profile saved.',
          'uploader' => 'basic'
        ]); ?>
      </div>
    </section>

    <!-- SESSIONS -->
    <section id="spk-sessions" class="spk-pane" role="tabpanel">
      <div class="spk-card">
        <h3 style="margin-top:0">Your Sessions</h3>
        <?php
          $q = new WP_Query([
            'post_type'=>'session','author'=>get_current_user_id(),
            'post_status'=>['publish','draft','pending'],
            'orderby'=>'date','order'=>'DESC','posts_per_page'=>50
          ]);
          if ($q->have_posts()) {
            echo '<ul class="spk-list">';
            while ($q->have_posts()) { $q->the_post();
              $id = get_the_ID();
              $edit = esc_url( add_query_arg(['edit_session'=>$id], get_permalink()) );
              $del  = wp_nonce_url( add_query_arg(['spk_del'=>$id], get_permalink()), 'spk_del_'.$id );
              echo '<li class="spk-row"><span>'.esc_html(get_the_title()).' — <em>'.esc_html(get_post_status()).'</em></span><span><a href="'.$edit.'">Edit</a> <a href="'.$del.'" onclick="return confirm(\'Delete this session?\')">Delete</a></span></li>';
            }
            echo '</ul>'; wp_reset_postdata();
          } else {
            echo '<div class="spk-alert">No sessions yet. Use the form below to add one.</div>';
          }

          $editing = (isset($_GET['edit_session']) && (int)get_post_field('post_author',(int)$_GET['edit_session'])===get_current_user_id())
            ? (int)$_GET['edit_session'] : 0;

          if ($editing) {
            acf_form([
              'post_id'=>$editing,
              'field_groups'=>['group_session'],
              'submit_value'=>'Save Session',
              'uploader'=>'basic'
            ]);
          } else {
            acf_form([
              'new_post'=>['post_type'=>'session','post_status'=>'draft'],
              'field_groups'=>['group_session'],
              'submit_value'=>'Create Session',
              'uploader'=>'basic'
            ]);
          }
        ?>
      </div>
    </section>

    <!-- FILES -->
    <section id="spk-files" class="spk-pane" role="tabpanel">
      <div class="spk-card">
        <h3 style="margin-top:0">Your Files</h3>
        <?php
          $fq = new WP_Query([
            'post_type'=>'speaker_file','author'=>get_current_user_id(),
            'post_status'=>['publish','draft','pending'],
            'orderby'=>'date','order'=>'DESC','posts_per_page'=>50
          ]);
          if ($fq->have_posts()) {
            echo '<ul class="spk-list">';
            while ($fq->have_posts()) { $fq->the_post();
              $fid = get_the_ID();
              $edit = esc_url( add_query_arg(['edit_file'=>$fid], get_permalink()) );
              $del  = wp_nonce_url( add_query_arg(['spk_del'=>$fid], get_permalink()), 'spk_del_'.$fid );
              echo '<li class="spk-row"><span>'.esc_html(get_the_title() ?: 'Untitled File').'</span><span><a href="'.$edit.'">Edit</a> <a href="'.$del.'" onclick="return confirm(\'Delete this file?\')">Delete</a></span></li>';
            }
            echo '</ul>'; wp_reset_postdata();
          } else {
            echo '<div class="spk-alert">No files yet. Use the form below to upload.</div>';
          }

          $editing_file = (isset($_GET['edit_file']) && (int)get_post_field('post_author',(int)$_GET['edit_file'])===get_current_user_id())
            ? (int)$_GET['edit_file'] : 0;

          if ($editing_file) {
            acf_form([
              'post_id'=>$editing_file,
              'field_groups'=>['group_speaker_file'],
              'submit_value'=>'Save File',
              'uploader'=>'basic'
            ]);
          } else {
            acf_form([
              'new_post'=>['post_type'=>'speaker_file','post_status'=>'draft'],
              'field_groups'=>['group_speaker_file'],
              'submit_value'=>'Upload File',
              'uploader'=>'basic'
            ]);
          }
        ?>
      </div>
    </section>
  </div>

  <script>
    (function(){
      const tabs=[...document.querySelectorAll('.spk-tab')];
      const panes=[...document.querySelectorAll('.spk-pane')];
      function openTab(btn){
        tabs.forEach(b=>b.setAttribute('aria-selected','false'));
        btn.setAttribute('aria-selected','true');
        panes.forEach(p=>p.dataset.active='false');
        const t=document.querySelector(btn.dataset.target);
        if(t) t.dataset.active='true';
        document.getElementById('speaker-portal').scrollIntoView({behavior:'smooth',block:'start'});
      }
      tabs.forEach(b=>b.addEventListener('click',()=>openTab(b)));
    })();
  </script>
  <?php
  return ob_get_clean();
});
