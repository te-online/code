<?php
/*
Plugin Name: Re-send Welcome Email
Description: Re-send welcome email to specific users
Version: 0.4.2
Author: Andreas Baumgartner

$Id: re-send-welcome-email.php 38:f90deffb2524 2012-02-28 13:21 +0100 Andreas Baumgartner $
$Tag: tip $

*/

/*  Copyright YEAR  PLUGIN_AUTHOR_NAME  (email : PLUGIN AUTHOR EMAIL)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('Resend_Welcom_Email')) {
 
    class Resend_Welcome_Email {    

        /*  Add a bulk action for resending welcome emails on userlistpage 
        *
        *   Based on an example licensend like that:
        *   Copyright: © 2012 Justin Stern (email : justin@foxrunsoftware.net)
        *   License: GNU General Public License v3.0
        *   License URI: http://www.gnu.org/licenses/gpl-3.0.html
        *
        *   Changes made by, Thomas Ebert (thomas.ebert@te-online.net), te-online.net, 2013
        *   License: GNU General Public License v3.0
        *   License URI: http://www.gnu.org/licenses/gpl-3.0.html
        */

        public function __construct() {

            // i18n
            if(!load_plugin_textdomain('resend_welcome_email','/wp-content/languages/'))
                load_plugin_textdomain('resend_welcome_email','/wp-content/plugins/re-send-welcome-email/translations/');
                    
            if(is_admin()) {
                // admin actions/filters
                add_action('admin_menu',            array(&$this, 'resend_welcome_admin'));

                add_action('admin_footer-users.php', array(&$this, 'resend_welcome_bulk_admin_footer'));
                add_action('load-users.php',         array(&$this, 'resend_welcome_bulk_action'));
                add_action('admin_notices',         array(&$this, 'resend_welcome_bulk_admin_notices'));
            }
        }
        
                
        /**
         * Step 1: add the custom Bulk Action to the select menus
         */
        function resend_welcome_bulk_admin_footer() {
            ?>
                <script type="text/javascript">
                    jQuery(document).ready(function() {
                        jQuery('<option>').val('resend_welcome_email').text('<?php _e("Resend welcome-mail (resets password!)", "resend_welcome_email")?>').appendTo("select[name='action']");
                        jQuery('<option>').val('resend_welcome_email').text('<?php _e("Resend welcome-mail (resets password!)", "resend_welcome_email")?>').appendTo("select[name='action2']");
                    });
                </script>
            <?php
        }
        
        
        /**
         * Step 2: handle the custom Bulk Action
         * 
         * Based on the post http://wordpress.stackexchange.com/questions/29822/custom-bulk-action
         */
        function resend_welcome_bulk_action() {            
                
            // get the action
            $wp_list_table = _get_list_table('WP_Users_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
            $action = $wp_list_table->current_action();
            
            $allowed_actions = array("resend_welcome_email");
            if(!in_array($action, $allowed_actions)) return;
            
            // security check
            check_admin_referer('bulk-users');
            
            // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
            if(isset($_REQUEST['users'])) {
                $user_ids = array_map('intval', $_REQUEST['users']);
            }
            
            if(empty($user_ids)) return;
            
            // this is based on wp-admin/edit.php
            $sendback = remove_query_arg( array('mailed', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
            if ( ! $sendback )
                $sendback = admin_url( "users.php" );
            
            $pagenum = $wp_list_table->get_pagenum();
            $sendback = add_query_arg( 'paged', $pagenum, $sendback );
            
            switch($action) {
                case 'resend_welcome_email':
                    
                    // if we set up user permissions/capabilities, the code might look like:
                    if ( !current_user_can( 'edit_users' ) )
                      wp_die( __('You are not allowed to change user-options.', 'resend_welcome_email') );
                    
                    $mailed = 0;
                    foreach( $user_ids as $user_id ) {
                        
                        if ( !$this->resend_mail( $user_id, true ) )
                            wp_die( __('Error resending mails.', 'resend_welcome_email') );
        
                        $mailed++;
                    }
                    
                    $sendback = add_query_arg( array('mailed' => $mailed, 'ids' => join(',', $user_ids) ), $sendback );
                break;
                
                default: return;
            }
            
            $sendback = remove_query_arg( array('action', 'action2', 'bulk_edit'), $sendback );
            
            wp_redirect($sendback);
            exit();
        }
        
        
        /**
         * Step 3: display an admin notice on the Posts page after exporting
         */
        function resend_welcome_bulk_admin_notices() {
            global $post_type, $pagenow;
            
            if($pagenow == 'users.php' && isset($_REQUEST['mailed']) && (int) $_REQUEST['mailed']) {
                $message = sprintf( _n( __('User has got mail.', 'resend_welcome_email'), __('%s mails to users resent.', 'resend_welcome_email'), $_REQUEST['mailed'] ), number_format_i18n( $_REQUEST['mailed'] ) );
                echo "<div class=\"updated\"><p>{$message}</p></div>";
            }
        }
        
        function resend_welcome_admin() {
        	add_users_page(__('Resend Welcome', 'resend_welcome_email'), __('Resend Welcome Email', 'resend_welcome_email'), 'manage_options', __FILE__, array(&$this, 'resend_welcome_settings_page'));
        }

        function resend_welcome_settings_page() { ?>

            <div class="wrap">
                <h2><?php _e('Resend Welcome Email', 'resend_welcome_email'); ?></h2>
                <p><?php _e("This will reset the user's password and re-send their &quot;Welcome&quot; email with username and password.", 'resend_welcome_email'); ?></p>
                <form method="POST">

                    <p><?php _e('Re-send welcome email for this user (<b>note: the user\'s password will be reset</b>):', 'resend_welcome_email');?> <?php wp_dropdown_users(array('orderby' => 'user_nicename', 'show' => 'user_login')); ?></p>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Send e-mail', 'resend_welcome_email') ?>" />
                    </p>

                </form>
                <?php
                    if (isset($_POST['user'])) {
                        $uid = $_POST['user'];
                        $this->resend_mail( $uid );                        
                    }
                ?>

            </div>
        <?php }

        function resend_mail( $uid, $bulk=false ) {
            // Generate a password
            $password = wp_generate_password(); // use standard_function instead
            $user_info = get_userdata( $uid );
            
            if( !wp_update_user( array( 'ID' => $uid, 'user_pass' => $password ) ) ) return false;

            // Send welcome email (there might be a better function for this, I didn't check)
            wp_new_user_notification( $uid, $password ); // does not return a value, so we can't check

            if( !$bulk ) {
                $message = sprintf(__('E-mail sent for user %s.', 'resend_welcome_email'), $user_info->user_login);
                printf('<div id="message" class="updated fade"><p>%s</p></div>', $message);
            }

            return true;
        }

    }

}

new Resend_Welcome_Email();
