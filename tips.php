<?php
/*
Plugin Name: Tips
Plugin URI: 
Description:
Author: Andrew Billits
Version: 1.0.1
Author URI:
WDP ID: 61
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

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

$tips_current_version = '1.0.1';
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$tmp_tips_prefix = "";
$tmp_tips_suffix = "";
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
//check for activating
if ($_GET['key'] == '' || $_GET['key'] === ''){
	add_action('admin_head', 'tips_make_current');
}

add_action('admin_menu', 'tips_plug_pages');
if ($_GET['updated'] != 'true' && $_GET['activated'] != 'true'){
	add_action('admin_notices', 'tips_output');
}
add_action('profile_personal_options', 'tips_profile_option_output');
add_action('personal_options_update', 'tips_profile_option_update');
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function tips_make_current() {
	global $wpdb, $tips_current_version;
	if (get_site_option( "tips_version" ) == '') {
		add_site_option( 'tips_version', '0.0.0' );
	}
	
	if (get_site_option( "tips_version" ) == $tips_current_version) {
		// do nothing
	} else {
		//update to current version
		update_site_option( "tips_installed", "no" );
		update_site_option( "tips_version", $tips_current_version );
	}
	tips_global_install();
	//--------------------------------------------------//
	if (get_option( "tips_version" ) == '') {
		add_option( 'tips_version', '0.0.0' );
	}
	
	if (get_option( "tips_version" ) == $tips_current_version) {
		// do nothing
	} else {
		//update to current version
		update_option( "tips_version", $tips_current_version );
		tips_blog_install();
	}
}

function tips_blog_install() {
	global $wpdb, $tips_current_version;
	//$tips_table1 = "";
	//$wpdb->query( $tips_table1 );
}

function tips_global_install() {
	global $wpdb, $tips_current_version;
	if (get_site_option( "tips_installed" ) == '') {
		add_site_option( 'tips_installed', 'no' );
	}
	
	if (get_site_option( "tips_installed" ) == "yes") {
		// do nothing
	} else {
	
		$tips_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "tips` (
  `tip_ID` bigint(20) unsigned NOT NULL auto_increment,
  `tip_site_ID` int(20) NOT NULL default '0',
  `tip_content` TEXT,
  `tip_added` varchar(255),
  PRIMARY KEY  (`tip_ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=43 ;";
		$tips_table2 = "";
		$tips_table3 = "";
		$tips_table4 = "";
		$tips_table5 = "";

		$wpdb->query( $tips_table1 );
		//$wpdb->query( $tips_table2 );
		//$wpdb->query( $tips_table3 );
		//$wpdb->query( $tips_table4 );
		//$wpdb->query( $tips_table5 );
		update_site_option( "tips_installed", "yes" );
	}
}

function tips_plug_pages() {
	if ( is_site_admin() ){
		add_submenu_page('ms-admin.php', 'Tips', 'Tips', 10, 'manage-tips', 'tips_manage_output' );
	}
}

function tips_profile_option_update() {
	global $user_id;
	if ( $_POST['show_tips'] != '' ) {
		update_usermeta($user_id,'show_tips',$_POST['show_tips']);
	}
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function tips_output() {
	global $wpdb, $current_site, $tmp_tips_prefix, $tmp_tips_suffix, $user_ID;
	$show_tips = get_usermeta($user_ID,'show_tips');
	if ( $show_tips != 'no' ) {
		$tmp_tips_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $current_site->id . "'");
		if ($tmp_tips_count > 0){
			$tmp_tip_content = $wpdb->get_var("SELECT tip_content FROM " . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $current_site->id . "' ORDER BY RAND() LIMIT 1");
			$tmp_tip_content = $tmp_tips_prefix . $tmp_tip_content . $tmp_tips_suffix;
			?>
			<div id="message" class="updated"><p><?php echo $tmp_tip_content; ?></p></div>
			<?php
		}
	}
}

function tips_profile_option_output() {
	global $user_id;
	$show_tips = get_usermeta($user_id,'show_tips');
	?>
	<h3><?php _e('Tips') ?></h3>
	<table class="form-table">
	<tr>
		<th><label for="show_tips"><?php _e('Show Tips') ?></label></th>
		<td>
			<select name="show_tips" id="show_tips">
				<option value="yes" <?php if ( $show_tips == '' || $show_tips == 'yes' ) { echo 'selected="selected"'; } ?> ><?php _e('Yes'); ?></option>
				<option value="no" <?php if ( $show_tips == 'no' ) { echo 'selected="selected"'; } ?> ><?php _e('No'); ?></option>
			</select>
		</td>
	</tr>
	</table>
	<?php
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function tips_manage_output() {
	global $wpdb, $wp_roles, $current_site;
	
	if(!current_user_can('manage_options')) {
		echo "<p>" . __('Nice Try...') . "</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
		$tmp_tips_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $current_site->id . "'");
			?>

            <h2><?php _e('Manage Tips') ?> (<a href="ms-admin.php?page=manage-tips&action=new_tip"><?php _e('New') ?></a>):</h2>
            <?php
			if ($tmp_tips_count == 0){
			?>
            <p><?php _e('Click ') ?><a href="ms-admin.php?page=manage-tips&action=new_tip"><?php _e('here') ?></a><?php _e(' to add a new tip.') ?></p>
            <?php
			} else {
			$query = "SELECT tip_ID, tip_content, tip_added FROM " . $wpdb->base_prefix . "tips WHERE tip_site_ID = '" . $current_site->id . "' ORDER BY tip_ID DESC";
			$tmp_tips = $wpdb->get_results( $query, ARRAY_A );
			echo "
			<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
			<thead><tr>
			<th scope='col'>Content</th>
			<th scope='col'>Added</th>
			<th scope='col'>Actions</th>
			<th scope='col'></th>
			</tr></thead>
			<tbody id='the-list'>
			";
			if (count($tmp_tips) > 0){
				$class = ('alternate' == $class) ? '' : 'alternate';
				foreach ($tmp_tips as $tmp_tip){
				//=========================================================//
				echo "<tr class='" . $class . "'>";
				echo "<td valign='top'>" . $tmp_tip['tip_content'] . "</td>";
				echo "<td valign='top'>" . date(get_option('date_format') . ' ' . get_option('time_format'),$tmp_tip['tip_added']) . "</td>";
				echo "<td valign='top'><a href='ms-admin.php?page=manage-tips&action=edit_tip&tid=" . $tmp_tip['tip_ID'] . "' rel='permalink' class='edit'>" . __('Edit') . "</a></td>";
				echo "<td valign='top'><a href='ms-admin.php?page=manage-tips&action=delete_tip&tid=" . $tmp_tip['tip_ID'] . "' rel='permalink' class='delete'>" . __('Remove') . "</a></td>";
				echo "</tr>";
				$class = ('alternate' == $class) ? '' : 'alternate';
				//=========================================================//
				}
			}
			?>
			</tbody></table>
            <?php
			}
		break;
		//---------------------------------------------------//
		case "new_tip":
		?>
			<h2><?php _e('New Tip') ?></h2>
            <form name="form1" method="POST" action="ms-admin.php?page=manage-tips&action=new_tip_process"> 
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Tip:') ?></th>
				<td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $_POST['tip_content']; ?></textarea>
                <br />
                <?php //_e('') ?></td> 
                </tr>
                </table>
            <p class="submit"> 
            <input type="submit" name="Submit" value="<?php _e('Save') ?>" /> 
            </p> 
            </form>
        <?php
		break;
		//---------------------------------------------------//
		case "new_tip_process":
			if ($_POST['tip_content'] == ''){
				?>
                <h2><?php _e('New Tip') ?></h2>
                <form name="form1" method="POST" action="ms-admin.php?page=manage-tips&action=new_tip_process"> 
                    <table class="form-table">
                    <tr valign="top">
                    <th scope="row"><?php _e('Tip') ?></th>
                    <td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $_POST['tip_content']; ?></textarea>
                    <br />
                    <?php //_e('') ?></td> 
                    </tr>
                    </table>
                <p class="submit"> 
                <input type="submit" name="Submit" value="<?php _e('Save') ?>" /> 
                </p> 
                </form>
				<?php
			} else {
				$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "tips (tip_site_ID, tip_content, tip_added) VALUES ( '" . $current_site->id . "', '" . $_POST['tip_content'] . "' , '" . time() . "')" );
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='ms-admin.php?page=manage-tips&updated=true&updatedmsg=" . urlencode(__('Tip Added!')) . "';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
		case "edit_tip":
			$tmp_tip_content = $wpdb->get_var("SELECT tip_content FROM " . $wpdb->base_prefix . "tips WHERE tip_ID = '" . $_GET['tid'] . "' AND tip_site_ID = '" . $current_site->id . "'");
			?>
			<h2><?php _e('Edit Tip') ?></h2>
            <form name="form1" method="POST" action="ms-admin.php?page=manage-tips&action=edit_tip_process"> 
			<input type="hidden" name="tid" value="<?php echo $_GET['tid']; ?>" />
                <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Tip') ?></th>
				<td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $tmp_tip_content; ?></textarea>
                <br />
                <?php //_e('') ?></td> 
                </tr>
                </table>
            <p class="submit"> 
            <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" /> 
            </p> 
            </form>
	        <?php
		break;
		//---------------------------------------------------//
		case "edit_tip_process":
			$tmp_tip_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "tips WHERE tip_ID = '" . $_POST['tid'] . "' AND tip_site_ID = '" . $current_site->id . "'");
			if ($tmp_tip_count > 0){
				if ($_POST['tip_content'] == ''){
					?>
					<h2><?php _e('Edit Tip') ?></h2>
					<form name="form1" method="POST" action="ms-admin.php?page=manage-tips&action=edit_tip_process"> 
					<input type="hidden" name="tid" value="<?php echo $_POST['tid']; ?>" />
						<table class="form-table">
						<tr valign="top">
						<th scope="row"><?php _e('Tip') ?></th>
						<td><textarea name="tip_content" id="tip_content" style="width: 95%" rows="5"><?php echo $tmp_tip_content; ?></textarea>
						<br />
						<?php //_e('') ?></td> 
						</tr>
						</table>
					<p class="submit"> 
					<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" /> 
					</p> 
					</form>
					<?php
				} else {
					$wpdb->query( "UPDATE " . $wpdb->base_prefix . "tips SET tip_content = '" . $_POST['tip_content'] . "' WHERE tip_ID = '" . $_POST['tid'] . "' AND tip_site_ID = '" . $current_site->id . "'" );
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='ms-admin.php?page=manage-tips&updated=true&updatedmsg=" . urlencode(__('Settings saved.')) . "';
					</script>
					";
				}
			}
		break;
		//---------------------------------------------------//
		case "delete_tip":
				$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "tips WHERE tip_ID = '" . $_GET['tid'] . "' AND tip_site_ID = '" . $current_site->id . "'" );
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='ms-admin.php?page=manage-tips&updated=true&updatedmsg=" . urlencode(__('Tip Removed.')) . "';
				</script>
				";
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

?>
