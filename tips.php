<?php
/*
Plugin Name: Admin Panel Tips
Plugin URI: https://github.com/wpmudev/admin-tips
Description: Provide your users with helpful random tips (or promotions/news) in their admin panels.
Author: WPMU DEV
Version: 1.0.8
Author URI: http://premium.wpmudev.org/
Network: true
WDP ID: 61


Copyright 2007-2014 Incsub (http://incsub.com)
Author - S H Mohanjith
Contributors - Ivan Shaovchev, Andrew Billits, Aaron Edwards

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//define( ''tips'', 'tips' );


/* Get admin page location */
if ( is_multisite() ) {
	$tips_menu_slug = 'settings.php';
	$tips_admin_url = network_admin_url( 'settings.php?page=manage-tips' );
	add_action( 'network_admin_menu', 'tips_plug_pages' );
} else {
	$tips_menu_slug = 'options-general.php';
	$tips_admin_url = admin_url( 'options-general.php?page=manage-tips' );
	add_action( 'admin_menu', 'tips_plug_pages' );
}

global $tips_current_version;
$tips_current_version = '1.0.8';
$tmp_tips_prefix      = '';
$tmp_tips_suffix      = '';

register_activation_hook( __FILE__, 'tips_make_current' );

if ( ! isset( $_GET['updated'] ) || ! isset( $_GET['activated'] ) || ( 'true' !== $_GET['updated'] && 'true' !== $_GET['activated'] ) ) {
	add_action( 'admin_notices', 'tips_output' );
	//add_action('network_admin_notices', 'tips_output');
}

add_action( 'init', 'tips_global_init' );
add_action( 'profile_personal_options', 'tips_profile_option_output' );
add_action( 'personal_options_update', 'tips_profile_option_update' );
add_action( 'wp_ajax_tips_dismiss', 'tips_dismiss' );
add_action( 'wp_ajax_tips_hide', 'tips_hide' );
add_action( 'admin_enqueue_scripts', 'tips_enqueue_scripts' );

function tips_dismiss() {
	$tip_ids = isset( $_COOKIE['tips_dismissed'] ) ? maybe_unserialize( stripslashes( $_COOKIE['tips_dismissed'] ) ) : array();
	if ( isset( $_REQUEST['tid'] ) ) {
		$tip_ids[ $_REQUEST['tid'] ] = $_REQUEST['tid'];
	}
	setcookie( 'tips_dismissed', serialize( $tip_ids ), time() + 3600 * 24 * 365, admin_url( '/' ) );
	wp_safe_redirect( wp_get_referer() );
	exit();
}

function tips_hide() {
	global $current_user;

	update_user_meta( $current_user->ID, 'show_tips', 'no' );
	wp_safe_redirect( wp_get_referer() );
	exit();
}

function tips_enqueue_scripts() {
	wp_enqueue_script( 'jquery' );
}

function tips_make_current() {
	global  $tips_current_version;

	if ( get_site_option( 'tips_version' ) === $tips_current_version ) {
		// do nothing
	} else {
		tips_global_install();
		//update to current version

	}
}



function tips_global_init() {
	if ( preg_match( '/mu\-plugin/', PLUGINDIR ) > 0 ) {
		load_muplugin_textdomain( 'tips', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	} else {
		load_plugin_textdomain( 'tips', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}

/**
 *
 * @global type $wpdb
 * @global string $tips_current_version
 *
 * @version 2.0
 */
function tips_global_install() {
	global $wpdb, $tips_current_version;

	// Get the correct character collate
	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
	}
	if ( ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE $wpdb->collate";
	}

	if ( ! ( get_site_option( 'tips_installed' ) ) ) {
		add_site_option( 'tips_installed', 'no' );
	}

	if ( 'yes' === get_site_option( 'tips_installed' ) ) {
		if ( version_compare( '1.0.4', get_site_option( 'tips_version' ) ) >= 0 ) {
			$tips_table1 = 'ALTER TABLE `' . $wpdb->base_prefix . "tips` ADD `tip_status` INT( 1 ) NOT NULL DEFAULT '1' AFTER `tip_added` ;";
			$wpdb->query( $tips_table1 );
		}
	} else {
		$tips_table1 = 'CREATE TABLE IF NOT EXISTS `' . $wpdb->base_prefix . "tips` (
  `tip_ID` bigint(20) unsigned NOT NULL auto_increment,
  `tip_site_ID` int(20) NOT NULL default '0',
  `tip_content` TEXT  CHARACTER SET utf8,
  `tip_added` varchar(255),
  `tip_status` int(1) NOT NULL default '1',
  PRIMARY KEY  (`tip_ID`)
)  {$charset_collate};";
		$wpdb->query( $tips_table1 );
		update_site_option( 'tips_installed', 'yes' );
		update_site_option( 'tips_version', $tips_current_version );
	}
}

/**
 *
 * @global type $tips_menu_slug
 * @version 2.0
 */
function tips_plug_pages() {
	global $tips_menu_slug;

	if ( is_super_admin() ) {
		add_submenu_page( $tips_menu_slug, __( 'Tips', 'tips' ), __( 'Tips', 'tips' ), 'manage_options', 'manage-tips', 'tips_manage_output' );
	}
}

/**
 *
 * @global type $user_id
 *
 * @version 2.0
 */
function tips_profile_option_update() {
	global $user_id;
	if ( '' !== $_POST['show_tips'] ) {
		update_user_meta( $user_id, 'show_tips', $_POST['show_tips'] );
	}
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function tips_output() {
	global $wpdb,  $tmp_tips_prefix, $tmp_tips_suffix, $current_user;

	//hide if turned off
	$show_tips = get_user_meta( $current_user->ID, 'show_tips', true );
	if ( 'no' === $show_tips ) {
		return;
	}

	$dismissed_tips = isset( $_COOKIE['tips_dismissed'] ) ? maybe_unserialize( stripslashes( $_COOKIE['tips_dismissed'] ) ) : array();
	if ( 0 < count( $dismissed_tips ) ) {
			$dismissed_tips_sql = 'AND tip_ID NOT IN(' . join( ',', $dismissed_tips ) . ')';
	} else {
			$dismissed_tips_sql = '';
	}
	$tmp_tips_count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $wpdb->siteid . "' AND tip_status = 1 {$dismissed_tips_sql} " );
	if ( $tmp_tips_count > 0 ) {
		$tmp_tip         = $wpdb->get_row( 'SELECT tip_ID, tip_content FROM ' . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $wpdb->siteid . "' AND tip_status = 1 {$dismissed_tips_sql} ORDER BY RAND() LIMIT 1" );
		$tmp_tip_content = $tmp_tips_prefix . $tmp_tip->tip_content . $tmp_tips_suffix;
		?>
		<div id="message" class="updated admin-panel-tips"><p class="apt-dismiss">[ <a href="<?php echo admin_url( 'admin-ajax.php' ); ?>?action=tips_dismiss&tid=<?php echo $tmp_tip->tip_ID; ?>" ><?php _e( 'Dismiss', 'tips' ); ?></a> ]</p> <p class="apt-hide">[ <a href="<?php echo admin_url( 'admin-ajax.php' ); ?>?action=tips_hide&tid=<?php echo $tmp_tip->tip_ID; ?>" ><?php _e( 'Hide', 'tips' ); ?></a> ]</p> <p class="apt-content"><?php echo $tmp_tip_content; ?></p></div>
		<style type="text/css">
				.apt-dismiss, .apt-hide {
			float: right;
			font-size: 0.9em;
				}
		</style>
		<script type="text/javascript">
				jQuery(document).ready(function () {
						jQuery('p.apt-dismiss a').click(function () {
					var tips_dismiss_data = {
				action: 'tips_dismiss',
				tid: <?php echo $tmp_tip->tip_ID; ?>
					};
					jQuery('#message.admin-panel-tips p.apt-dismiss').html('<?php _e( 'Saving...', 'tips' ); ?>');
					jQuery.post(ajaxurl, tips_dismiss_data, function(response) {
				jQuery('#message.admin-panel-tips').hide();
					});
					return false;
			});
			jQuery('p.apt-hide a').click(function () {
					var tips_dismiss_data = {
				action: 'tips_hide',
				tid: <?php echo $tmp_tip->tip_ID; ?>
					};
					jQuery('#message.admin-panel-tips p.apt-hide').html('<?php _e( 'Saving...', 'tips' ); ?>');
					jQuery.post(ajaxurl, tips_dismiss_data, function(response) {
				jQuery('#message.admin-panel-tips').hide();
					});
					return false;
			});
				});
		</script>
		<?php
	}
}

function tips_profile_option_output() {
	global $user_id;
	$show_tips = get_user_meta( $user_id, 'show_tips', true );
	?>
	<h3><?php _e( 'Tips', 'tips' ); ?></h3>
	<table class="form-table">
	<tr>
		<th><label for="show_tips"><?php _e( 'Show Tips', 'tips' ); ?></label></th>
		<td>
			<select name="show_tips" id="show_tips">
				<option value="yes" 
				<?php
				if ( '' === $show_tips || 'yes' === $show_tips ) {
					echo 'selected="selected"'; }
				?>
				><?php _e( 'Yes', 'tips' ); ?></option>
				<option value="no" 
				<?php
				if ( 'no' === $show_tips ) {
					echo 'selected="selected"'; }
				?>
				><?php _e( 'No', 'tips' ); ?></option>
			</select>
		</td>
	</tr>
	</table>
	<?php
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

/**
 *
 * @global type $wpdb
 * @global type $tips_admin_url
 * @return type
 *
 * @version 2.0
 */
function tips_manage_output() {
	global $wpdb,  $tips_admin_url;

	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<p>' . __( 'Nice Try...', 'tips' ) . '</p>';  //If accessed properly, this message doesn't appear.
		return;
	}
	if ( isset( $_GET['updated'] ) ) {
		?>
		<div id="message" class="updated fade"><p><?php echo '' . urldecode( $_GET['updatedmsg'] ) . ''; ?></p></div>
		<?php
	}
	echo '<div class="wrap">';
	if ( ! isset( $_GET['action'] ) ) {
		$_GET['action'] = '';
	}
	switch ( $_GET['action'] ) {
		//---------------------------------------------------//
		default:
			$tmp_tips_count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $wpdb->siteid . "'" );
			?>

			<h2><?php _e( 'Manage Tips', 'tips' ); ?> (<a href="<?php echo $tips_admin_url; ?>&action=new_tip"><?php _e( 'New', 'tips' ); ?></a>):</h2>
			<?php
			if ( 0 === $tmp_tips_count ) {
				?>
			<p><?php _e( 'Click ', 'tips' ); ?><a href="<?php echo $tips_admin_url; ?>&action=new_tip"><?php _e( 'here', 'tips' ); ?></a><?php _e( ' to add a new tip.', 'tips' ); ?></p>
				<?php
			} else {
				$query    = 'SELECT tip_ID, tip_content, tip_added, tip_status FROM ' . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $wpdb->siteid . "' ORDER BY tip_status, tip_ID DESC";
				$tmp_tips = $wpdb->get_results( $query, ARRAY_A );
				echo "
			<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
			<thead><tr>
			<th scope='col'>" . __( 'Content', 'tips' ) . "</th>
			<th scope='col'>" . __( 'Added', 'tips' ) . "</th>
			<th scope='col'>" . __( 'Status', 'tips' ) . "</th>
			<th scope='col'>" . __( 'Actions', 'tips' ) . "</th>
			<th scope='col'></th>
			<th scope='col'></th>
			</tr></thead>
			<tbody id='the-list'>
			";
				$class = '';
				if ( count( $tmp_tips ) > 0 ) {
					$class = ( 'alternate' === $class ) ? '' : 'alternate';
					foreach ( $tmp_tips as $tmp_tip ) {
						//=========================================================//
						echo "<tr class='" . $class . "'>";
						echo "<td valign='top'>" . $tmp_tip['tip_content'] . '</td>';
						echo "<td valign='top'>" . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $tmp_tip['tip_added'] ) . '</td>';
						echo "<td valign='top'>" . ( ( '1' === $tmp_tip['tip_status'] ) ? __( 'Published', 'tips' ) : __( 'Draft', 'tips' ) ) . '</td>';
						echo '<td valign="top"><a href=' . $tips_admin_url . '&action=edit_tip&tid=' . $tmp_tip['tip_ID'] . " rel='permalink' class='edit'>" . __( 'Edit', 'tips' ) . '</a></td>';
						echo '<td valign="top"><a href=' . $tips_admin_url . '&action=delete_tip&tid=' . $tmp_tip['tip_ID'] . " rel='permalink' class='delete'>" . __( 'Remove', 'tips' ) . '</a></td>';
						if ( '1' === $tmp_tip['tip_status'] ) {
							echo '<td valign="top"><a href=' . $tips_admin_url . '&action=unpublish_tip&tid=' . $tmp_tip['tip_ID'] . " rel='permalink' class='delete'>" . __( 'Un-publish', 'tips' ) . '</a></td>';
						} else {
							echo '<td valign="top"><a href=' . $tips_admin_url . '&action=publish_tip&tid=' . $tmp_tip['tip_ID'] . " rel='permalink' class='delete'>" . __( 'Publish', 'tips' ) . '</a></td>';
						}
						echo '</tr>';
						$class = ( 'alternate' === $class ) ? '' : 'alternate';
						//=========================================================//
					}
				}
				?>
			</tbody></table>
				<?php
			}
			break;
		//---------------------------------------------------//
		case 'new_tip':
			$_POST['tip_content'] = isset( $_POST['tip_content'] ) ? $_POST['tip_content'] : '';
			?>
			<h2><?php _e( 'New Tip', 'tips' ); ?></h2>
			<form name="form1" method="POST" action="<?php echo $tips_admin_url; ?>&action=new_tip_process">
				<table class="form-table">
				<tr valign="top">
				<th scope="row"><?php _e( 'Tip:', 'tips' ); ?></th>
				<td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $_POST['tip_content']; ?></textarea>
				<br /></td> 
				</tr>
		<tr valign="top">
					<th scope="row"><?php _e( 'Status', 'tips' ); ?></th>
					<td><label><input type="radio" id="tip_status_published" name="tip_status" value="1" <?php echo ( '1' === $_POST['tip_content'] ) ? 'checked="checked"' : ''; ?> /> <?php _e( 'Published', 'tips' ); ?></label>
			<label><input type="radio" id="tip_status_draft" name="tip_status" value="0" <?php echo ( '1' !== $_POST['tip_content'] ) ? 'checked="checked"' : ''; ?> /> <?php _e( 'Draft', 'tips' ); ?></label>
					<br /></td> 
					</tr>
				</table>
			<p class="submit"> 
			<input class="button button-primary" type="submit" name="Submit" value="<?php _e( 'Save', 'tips' ); ?>" /> 
			</p> 
			</form>
			<?php
			break;
		//---------------------------------------------------//
		case 'new_tip_process':
			if ( '' === $_POST['tip_content'] ) {
				?>
				<h2><?php _e( 'New Tip', 'tips' ); ?></h2>
				<form name="form1" method="POST" action="<?php echo $tips_admin_url; ?>&action=new_tip_process">
					<table class="form-table">
					<tr valign="top">
					<th scope="row"><?php _e( 'Tip', 'tips' ); ?></th>
					<td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $_POST['tip_content']; ?></textarea>
					<br /></td> 
					</tr>
			<tr valign="top">
					<th scope="row"><?php _e( 'Status', 'tips' ); ?></th>
					<td><label><input type="radio" id="tip_status_published" name="tip_status" value="1" <?php echo ( '1' === $_POST['tip_content'] ) ? 'checked="checked"' : ''; ?> /> <?php _e( 'Published', 'tips' ); ?></label>
			<label><input type="radio" id="tip_status_draft" name="tip_status" value="0" <?php echo ( '0' === $_POST['tip_content'] ) ? 'checked="checked"' : ''; ?> /> <?php _e( 'Draft', 'tips' ); ?></label>
					<br /></td> 
					</tr>
					</table>
				<p class="submit"> 
				<input class="button button-primary" type="submit" name="Submit" value="<?php _e( 'Save', 'tips' ); ?>" /> 
				</p> 
				</form>
				<?php
			} else {
				$wpdb->query( 'INSERT INTO ' . $wpdb->base_prefix . "tips (tip_site_ID, tip_content, tip_added, tip_status) VALUES ( '" . $wpdb->siteid . "', '" . $_POST['tip_content'] . "' , '" . time() . "', '" . $_POST['tip_status'] . "')" );
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='" . $tips_admin_url . '&updated=true&updatedmsg=' . urlencode( __( 'Tip Added!', 'tips' ) ) . "';
				</script>
				";
			}
			break;
		//---------------------------------------------------//
		case 'edit_tip':
			$tmp_tip = $wpdb->get_row( 'SELECT tip_content, tip_status FROM ' . $wpdb->base_prefix . "tips WHERE tip_ID = '" . $_GET['tid'] . "' AND tip_site_ID = '" . $wpdb->siteid . "'" );
			?>
			<h2><?php _e( 'Edit Tip', 'tips' ); ?></h2>
			<form name="form1" method="POST" action="<?php echo $tips_admin_url; ?>&action=edit_tip_process">
			<input type="hidden" name="tid" value="<?php echo $_GET['tid']; ?>" />
				<table class="form-table">
				<tr valign="top">
				<th scope="row"><?php _e( 'Tip', 'tips' ); ?></th>
				<td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $tmp_tip->tip_content; ?></textarea>
				<br /></td> 
				</tr>
		<tr valign="top">
					<th scope="row"><?php _e( 'Status', 'tips' ); ?></th>
					<td><label><input type="radio" id="tip_status_published" name="tip_status" value="1" <?php echo ( '1' === $tmp_tip->tip_status ) ? 'checked="checked"' : ''; ?> /> <?php _e( 'Published', 'tips' ); ?></label>
			<label><input type="radio" id="tip_status_draft" name="tip_status" value="0" <?php echo ( '0' === $tmp_tip->tip_status ) ? 'checked="checked"' : ''; ?> /> <?php _e( 'Draft', 'tips' ); ?></label>
					<br /></td> 
					</tr>
				</table>
			<p class="submit"> 
			<input class="button button-primary" type="submit" name="Submit" value="<?php _e( 'Save Changes', 'tips' ); ?>" /> 
			</p> 
			</form>
			<?php
			break;
		//---------------------------------------------------//
		case 'edit_tip_process':
			$tmp_tip_count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->base_prefix . "tips WHERE tip_ID = '" . $_POST['tid'] . "' AND tip_site_ID = '" . $wpdb->siteid . "'" );
			if ( $tmp_tip_count > 0 ) {
				if ( '' === $_POST['tip_content'] ) {
					?>
					<h2><?php _e( 'Edit Tip', 'tips' ); ?></h2>
					<form name="form1" method="POST" action="<?php echo $tips_admin_url; ?>&action=edit_tip_process">
					<input type="hidden" name="tid" value="<?php echo $_POST['tid']; ?>" />
						<table class="form-table">
						<tr valign="top">
						<th scope="row"><?php _e( 'Tip', 'tips' ); ?></th>
						<td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $tmp_tip_content; ?></textarea>
						<br /></td> 
						</tr>
						</table>
					<p class="submit"> 
					<input class="button button-primary" type="submit" name="Submit" value="<?php _e( 'Save Changes', 'tips' ); ?>" /> 
					</p> 
					</form>
					<?php
				} else {
					$wpdb->query( 'UPDATE ' . $wpdb->base_prefix . "tips SET tip_content = '" . $_POST['tip_content'] . "', tip_status = " . $_POST['tip_status'] . " WHERE tip_ID = '" . $_POST['tid'] . "' AND tip_site_ID = '" . $wpdb->siteid . "'" );
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='" . $tips_admin_url . '&updated=true&updatedmsg=' . urlencode( __( 'Settings saved.', 'tips' ) ) . "';
					</script>
					";
				}
			}
			break;
		case 'unpublish_tip':
				$wpdb->query( 'UPDATE ' . $wpdb->base_prefix . "tips SET tip_status = 0 WHERE tip_ID = '" . $_GET['tid'] . "' AND tip_site_ID = '" . $wpdb->siteid . "'" );
				echo "
				<script type='text/javascript'>
				window.location='" . $tips_admin_url . '&updated=true&updatedmsg=' . urlencode( __( 'Tip Un-published.', 'tips' ) ) . "';
				</script>
				";
			break;
		case 'publish_tip':
				$wpdb->query( 'UPDATE ' . $wpdb->base_prefix . "tips SET tip_status = 1 WHERE tip_ID = '" . $_GET['tid'] . "' AND tip_site_ID = '" . $wpdb->siteid . "'" );
				echo "
				<script type='text/javascript'>
				window.location='" . $tips_admin_url . '&updated=true&updatedmsg=' . urlencode( __( 'Tip Published.', 'tips' ) ) . "';
				</script>
				";
			break;
		//---------------------------------------------------//
		case 'delete_tip':
				$wpdb->query( 'DELETE FROM ' . $wpdb->base_prefix . "tips WHERE tip_ID = '" . $_GET['tid'] . "' AND tip_site_ID = '" . $wpdb->siteid . "'" );
				echo "
				<script type='text/javascript'>
				window.location='" . $tips_admin_url . '&updated=true&updatedmsg=' . urlencode( __( 'Tip Removed.', 'tips' ) ) . "';
				</script>
				";
			break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

/**
 * Add settings link on plugin page
 * @param type $links
 * @param type $file
 * @return array
 *
 * @since 1.0.8
 */
function tips_settings_link( $links, $file ) {
	if ( is_multisite() ) {
		$location = 'settings.php';
	} else {
		$location = 'options-general.php';
	}
	return array_merge(
		$links,
		array(
			'settings' => '<a href="' . esc_url( add_query_arg( array( 'page' => 'manage-tips' ), $location ) ) . '">' . esc_html__( 'Settings', 'bp-group-documents' ) . '</a>',
		)
	);

}

/// Add link to settings page
add_filter( 'plugin_action_links_tips/tips.php', 'tips_settings_link', 10, 2 );
add_filter( 'network_admin_plugin_action_links_tips/tips.php', 'tips_settings_link', 10, 2 );
