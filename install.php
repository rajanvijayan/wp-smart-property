<?php
/*
Plugin Name: WP Smart Wishlist
Author: <strong>Rajan V</strong>
Description: This is wishlist plugin for WP-e Commerce Site. It have a widget you can use it very simple steps..
*/

/*  Copyright 2007-2013 Rajan V (email: ratanit2000 at gmail.com).

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Hook for adding admin menus
add_action('wpsc_product_form_fields_end', 'initialize_wishlist');
add_action('wp_footer', 'ajaxUrl');
add_action('wp_head', 'whshCSS');

function whshCSS(){
	echo '<link type="text/css" rel="stylesheet" href="'.plugins_url().'/wp-smart-wishlist/css/custom.css" />';
}

function initialize_wishlist(){
	global $current_user;
	?>
    
	<input type="button" value="<?php _e( 'Add to Wishlist ');?>" class="addWishlist" proid="<?php echo wpsc_the_product_id();?>" />
    <?php echo get_user_meta( $current_user->ID, 'wp_smart_wishlist', true ) ;?>
    <?php
}
function ajaxUrl() {
    ?>
	<script type="text/javascript" >
	function loadWishlist(){
		var data = { 'action': 'load_wish_list'};
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
			jQuery.post(ajaxurl, data, function(response) {
			jQuery('#wishlist').html(response);
		});
	}
	function removeWishlist( proid ){
		var data = { 'action': 'remove_wish_list', 'proid': proid };
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
			jQuery.post(ajaxurl, data, function(response) {
			loadWishlist();
		});
	}
    jQuery(document).ready(function() {
		loadWishlist();
		jQuery('.addWishlist').click(function(){
			<?php if ( is_user_logged_in() ) {?>
			var data = { 'action': 'add_wish_list', 'proid': jQuery(this).attr('proid') };
			var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
				jQuery.post(ajaxurl, data, function(response) {
				loadWishlist();
			});
			<?php }else{?>
				alert('Please login after use this option');
			<?php }?>
		});
		
    });
    </script>
	<?php
}

add_action( 'wp_ajax_add_wish_list', 'add_wish_list_callback' );

function add_wish_list_callback() {
	global $wpdb;

	$proid = $_POST['proid'];
	$user_ID = get_current_user_id();

	$wishlist = get_user_meta( $user_ID , 'wishlist', true);
	$array = unserialize ( $wishlist );
	if( ! in_array($proid ,$array) ){
		$array[] .= $proid;
		$new_wish = serialize( $array );
		update_user_meta( $user_ID , 'wishlist' , $new_wish, $wishlist );	
	}
	
	die();
}

add_action( 'wp_ajax_remove_wish_list', 'remove_wish_list_callback' );

function remove_wish_list_callback() {
	global $wpdb;

	$proid = $_POST['proid'];
	$user_ID = get_current_user_id();

	$wishlist = get_user_meta( $user_ID , 'wishlist', true);
	$array = unserialize ( $wishlist );
	if( in_array($proid ,$array) ){
		$key = array_search($proid ,$array); 
		unset( $array[$key]);
		$new_wish = serialize( $array );
		update_user_meta( $user_ID , 'wishlist' , $new_wish, $wishlist );	
	}
	
	die();
}

add_action( 'wp_ajax_load_wish_list', 'load_wish_list_callback' );

function load_wish_list_callback() {
	global $wpdb;

	$user_ID = get_current_user_id();

	$wishlist = get_user_meta( $user_ID , 'wishlist', true);
	$array = unserialize ( $wishlist );
	
	$html = '<table class="wishlist-table">
					<thead>
						<tr>
							<th>SNo</th>
							<th>Product Name</th>
							<th>Image</th>
							<th>&nbsp</th>
						<tr>
					<thead>
					<tbody>
						';
						$i = 1;
		if( ! empty($array) ){
			foreach( $array as $k => $v ){
				global $post;
				$post = get_post( $v );
				setup_postdata($post);
				if ( has_post_thumbnail() ) {
					$img = get_the_post_thumbnail( $post_id, array(64,64) );
				}
				else {
					$img = '<img src="' . plugins_url( 'image/notfound.png' , __FILE__ ).'" alt="Image" width="64" height="64" />';
				}
	
				//Template Start
				$html .= '	<tr>
								<td>'.$i.'</td>
								<td><a href="'.get_permalink().'">'. get_the_title() .'</a></td>
								<td>'.$img.'</td>
								<td><a href="javascript:void();" onclick="removeWishlist('.get_the_ID().')"><img src="' . plugins_url( 'image/remove.png' , __FILE__ ).'" alt="Remove" width="16" height="16" /></a></td>
							<tr>';
				//Template End
				$i++;
			}
		}
		else{
			$html .= '<tr><td colspan="4">No Wishlist Found</td></tr>';
		}
		$html .= '	</tbody>
					</table>';
		echo $html;
		
	
	die();
}

/********WIDGET SECTION*********/

class WishlistWidget extends WP_Widget
{
  function WishlistWidget()
  {
    $widget_ops = array('classname' => 'WishlistWidget', 'description' => 'Displays Wishlist' );
    $this->WP_Widget('WishlistWidget', 'Smart Wish List', $widget_ops);
  }
 
  function form($instance)
  {
    $instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
    $title = $instance['title'];
?>
  <p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
<?php
  }
 
  function update($new_instance, $old_instance)
  {
    $instance = $old_instance;
    $instance['title'] = $new_instance['title'];
    return $instance;
  }
 
  function widget($args, $instance)
  {
    extract($args, EXTR_SKIP);
 
    echo $before_widget;
    $title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
 
    if (!empty($title))
      echo $before_title . $title . $after_title;;
 
    // WIDGET CODE GOES HERE
    echo "<div id='wishlist'></div>";
 
    echo $after_widget;
  }
 
}
add_action( 'widgets_init', create_function('', 'return register_widget("WishlistWidget");') );

?>