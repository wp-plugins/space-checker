<?php
/**
 * Plugin Name: Space Checker
 * Plugin URI: http://prestongarrison.com
 * Description: Space Checker. The plugin that checks used and free space, and lets you know when there is no free space on the server by email. Prevent blog and database corruption
 * Version: 1.0.1
 * Author: Code Orange Inc
 * Author URI: http://prestongarrison.com
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) )
    exit;

add_action('admin_menu', 'scp_checker_menu');
add_action('wp_loaded', 'scp_checker_loaded');
add_action('admin_enqueue_scripts', 'scp_checker_admin_scripts');
add_action('admin_init', 'scp_checker_admin_init' );
add_action('init', 'scp_checker_init' );
add_action('wp_dashboard_setup', 'scp_add_dashboard_widget_sc' );

register_activation_hook( __FILE__, 'scp_activate_spacechaker'  );
register_deactivation_hook( __FILE__, 'scp_deactivate_spacechaker'  );

function scp_activate_spacechaker() { 
    add_option( 'sc_free_space', '95', '', 'yes' );
    add_option( 'sc_email', get_option('admin_email'), '', 'yes' );
    add_option( 'sc_send_mail', 0, '', 'yes' );
    add_option( 'sc_on', 0, '', 'yes' );
    add_option( 'sc_maintenance', '', '', 'yes' );
}

function scp_deactivate_spacechaker() {
    delete_option( 'sc_free_space');
    delete_option( 'sc_email');
    delete_option( 'sc_send_mail');
    delete_option( 'sc_on');
    delete_option( 'sc_maintenance');
}

$scp_diskPath = './';

function scp_totalSpace($rawOutput = false) {
    global $scp_diskPath;

    $diskTotalSpace = @disk_total_space($scp_diskPath);

    if ($diskTotalSpace === FALSE) {
      throw new Exception('totalSpace(): Invalid disk path.');
    }

    return $rawOutput ? $diskTotalSpace : scp_addUnits($diskTotalSpace);
}

function scp_freeSpace($rawOutput = false) {
    global $scp_diskPath;

    $diskFreeSpace = @disk_free_space($scp_diskPath);

    if ($diskFreeSpace === FALSE) {
      throw new Exception('freeSpace(): Invalid disk path.');
    }

    return $rawOutput ? $diskFreeSpace : scp_addUnits($diskFreeSpace);
}


function scp_usedSpace($precision = 1) {
    try {
      return round((100 - (scp_freeSpace(1) / scp_totalSpace(1)) * 100), $precision);
    } catch (Exception $e) {
      throw $e;
    }
}

function scp_addUnits($bytes) {
    $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

    for($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++ ) {
      $bytes /= 1024;
    }
 
    return round($bytes, 1).' '.$units[$i];
}


function scp_wp_maintenance_mode(){
    if(!current_user_can('edit_themes') || !is_user_logged_in()){
        wp_die('<h1 style="color:red">Website under Maintenance</h1><br />We are performing scheduled maintenance. We will be back on-line shortly!');
    }
}
function scp_checker_menu() {
    add_menu_page('Space Checker', 'Space Checker', 'administrator', 'space-checker', 'scp_checker_settings_page', 'dashicons-admin-generic');
    add_action( 'admin_init', 'scp_register_mysettings' );
} 

function scp_checker_disable_all($arg){
    unset( $arg['trash'] );
    unset( $arg['edit'] );
    unset( $arg['delete'] );
    unset( $arg['inline hide-if-no-js'] );
        
    foreach ($arg as $k => $v) {
        if (strpos($v, 'class="install-now') ) {
            unset ($arg[$k]);
        }
    }

    return $arg;
}

function scp_checker_del_widgets(){
    global $wp_meta_boxes;

    $wp_meta_boxes['dashboard']['normal']['core'] = array();
    $wp_meta_boxes['dashboard']['side']['core'] = array(
        'dashboard_widget_sc'=>array(
            'id'=>'dashboard_widget_sc','title'=>'Space Checker Widget','callback'=>'scp_dashboard_widget_sc','args'=>''));
}

function scp_checker_init(){
     if(scp_usedSpace() < get_option('sc_free_space') || get_option('sc_on') == 0 )
        return;

    $editor = get_role( 'administrator' );

    $caps = array(
        'moderate_comments',
        'manage_categories',
        'manage_links',
        'edit_others_posts',
        'edit_others_pages',
        'delete_posts',
        'edit_posts',
        'publish_posts',
    );

    foreach ( $caps as $cap )
        $editor->remove_cap( $cap );
}
function scp_checker_admin_init(){
    if(scp_usedSpace() < get_option('sc_free_space') || get_option('sc_on') == 0 )
        return;
    
    add_filter('show_admin_bar', '__return_false');
    show_admin_bar(false);

    remove_menu_page('edit.php'); 
    remove_menu_page('plugins.php'); 
    remove_menu_page('post-new.php'); 
    remove_menu_page('post-new.php?post_type=page'); 
    remove_menu_page('edit.php?post_type=page'); 
    remove_menu_page('upload.php'); 
    remove_menu_page('options-general.php'); 
    remove_menu_page('tools.php'); 
    remove_menu_page('users.php'); 
    remove_menu_page('themes.php'); 
    remove_menu_page('edit-comments.php' ); 

    add_filter( 'post_row_actions', 'scp_checker_disable_all', 10, 1 );
    add_filter( 'page_row_actions', 'scp_checker_disable_all', 10, 1 );
    add_action( 'wp_dashboard_setup', 'scp_checker_del_widgets', 9999 );

    if (
        stripos($_SERVER['REQUEST_URI'], 'post-new.php') !==false ||
        stripos($_SERVER['REQUEST_URI'], 'post.php') !==false ||
        stripos($_SERVER['REQUEST_URI'], 'edit.php') !==false
        ) 
        {
            wp_redirect(admin_url( 'index.php'));
        }

}

function scp_checker_loaded() { 
    
    if($_GET['space_checker'] == 'on'){
        update_option( 'sc_on', '1');
        wp_redirect(admin_url( 'index.php'));
    } elseif($_GET['space_checker'] == 'off') {
        update_option( 'sc_on', '0');
        $editor = get_role( 'administrator' );

    $caps = array(
        'moderate_comments',
        'manage_categories',
        'manage_links',
        'edit_others_posts',
        'edit_others_pages',
        'delete_posts',
        'edit_posts',
        'publish_posts',
    );

    foreach ( $caps as $cap )
        $editor->add_cap( $cap );
        wp_redirect(admin_url( 'index.php'));
    }    

    if(get_option('sc_free_space') && scp_usedSpace() > get_option('sc_free_space')){
        if(get_option('sc_email') && !get_option('sc_send_mail')){
            wp_mail( get_option('sc_email'), 'Space Ckecker', 'Hi! Used space - '.scp_usedSpace().'%. Website under Maintenance.' );
            update_option( 'sc_send_mail', '1');
            update_option( 'sc_on', '1');
        }

        if(get_option('sc_maintenance') && get_option('sc_on') == 1)
            scp_wp_maintenance_mode(); 
        
    }
}

function scp_checker_admin_scripts( $hook ) {
    if($hook != 'index.php') return;

    wp_enqueue_script( 'checker_script_js', plugins_url( '/assets/jquery.circliful.min.js', __FILE__ ) );
    wp_register_style( 'checker_script_css', plugins_url( '/assets/jquery.circliful.css', __FILE__ ) );
    wp_enqueue_style( 'checker_script_css' );
}

function scp_dashboard_widget_sc( $post, $callback_args ) {
    if(get_option('sc_on') == 1) $off_on = '<a href="'.admin_url( 'index.php?space_checker=off').'">Deactivate Read-only mode</a>';
        else $off_on = '<a href="'.admin_url( 'index.php?space_checker=on').'">Activate Read-only mode</a>';

    echo'<div class=\'feature_post_class_wrap\'><center><table>'.
        '<tr><td>'.
        '   <table>'.
                '<tr valign="top">
                    <th scope="row">Total Space: </th>
                    <td>'.scp_totalSpace().'</td>
                </tr>
                <tr valign="top"> 
                    <th scope="row">Free Space: </th>
                    <td>'.scp_freeSpace().'</td>
                </tr>
                <tr valign="top">
                    <th scope="row">Used Space: </th>
                    <td>'.scp_addUnits(scp_totalSpace(true)-scp_freeSpace(true)).'</td>
                </tr>
                <tr valign="top">
                    <td scope="row" rowspan="2">'.$off_on.'</td>
                </tr>'.
        '   </table>'.
        '</td>'.
        '<td>'.
        '<div class="circlestat" data-dimension="150" data-text="'.scp_usedSpace().'%" data-width="30" data-fontsize="22" data-percent="'.scp_usedSpace().'" data-fgcolor="#61a9dc" data-bgcolor="#eee" data-fill="#ddd">'.
        '</td></tr>'.
        '</table></center></div>';
    echo '<script>jQuery( document ).ready(function() {jQuery(\'.circlestat\').circliful();});</script>';
}

function scp_add_dashboard_widget_sc() {
    wp_add_dashboard_widget('dashboard_widget_sc', 'Space Checker Widget', 'scp_dashboard_widget_sc');
}

function scp_register_mysettings(){
    register_setting( 'scp_checker-group', 'sc_free_space' );
    register_setting( 'scp_checker-group', 'sc_email' );
    register_setting( 'scp_checker-group', 'sc_send_mail' );
    register_setting( 'scp_checker-group', 'sc_on' );
    register_setting( 'scp_checker-group', 'sc_maintenance' );
}
function scp_checker_settings_page() {

    ?>
        <div class="wrap">
        <h2>Space Checker Details</h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'scp_checker-group' ); ?>
            <?php do_settings_sections( 'scp_checker-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Total Space: </th>
                    <td><?=scp_totalSpace()?></td>
                </tr>
                <tr valign="top"> 
                    <th scope="row">Free Space: </th>
                    <td><?=scp_freeSpace()?></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Used Space: </th>
                    <td><?=scp_addUnits(scp_totalSpace(true)-scp_freeSpace(true))?> (<?=scp_usedSpace()?>%)</td>
                </tr>
                <tr valign="top">
                    <th scope="row">Percent of used space to activate</th>
                    <td>
                        <input style="width: 50px;" type="text" name="sc_free_space" value="<?php echo esc_attr( get_option('sc_free_space') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show maintenance message </th>
                    <td>
                        <input name="sc_maintenance" type="checkbox" value="1" <?php checked( '1', get_option( 'sc_maintenance' ) ); ?> />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Email to notify</th>
                    <td>
                        <input style="width: 250px;" type="text" name="sc_email" value="<?php echo esc_attr( get_option('sc_email') ); ?>" />
                        <input type="hidden" name="sc_send_mail" value="0" />                
                        <input type="hidden" name="sc_on" value="0" />                
                    </td>
                </tr>
            </table>
             
            <?php submit_button(); ?>

        </form>
        </div>
    <?  

}
