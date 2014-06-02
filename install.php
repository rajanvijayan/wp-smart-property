<?php 
/*
Plugin Name: WP Smart Property
Author: <strong>Rajan V</strong>
Version: 1.0
Description: This is realestate plugin. It have a widget you can use it very simple steps.
*/

/*  Copyright 2007-2013 Rajan V (email: ratanit2000 at gmail.com)

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
ob_start();
session_start();
add_action('init', 'initial');

function initial(){
	$labels = array(
	'name' => _x('Properties', 'post type general name', ''),
	'singular_name' => _x('Property', 'post type singular name', ''),
	'add_new' => _x('Add New', 'print', ''),
	'add_new_item' => __('Add New Property', ''),
	'edit_item' => __('Edit Property', ''),
	'new_item' => __('New Property', ''),
	'all_items' => __('All Properties', ''),
	'view_item' => __('View Property', ''),
	'search_items' => __('Search Properties', ''),
	'not_found' =>  __('No properties found', ''),
	'not_found_in_trash' => __('No properties found in Trash', ''), 
	'parent_item_colon' => '',
	'menu_name' => __('Properties', '')
	);
	$args = array(
	'labels' => $labels,
	'public' => true,
	'publicly_queryable' => true,
	'show_ui' => true, 
	'show_in_menu' => true, 
	'query_var' => true,
	'capability_type' => 'post',
	'has_archive' => true, 
	'hierarchical' => false,
	'menu_position' => null,
	'menu_icon' => plugins_url( 'wp-smart-property/images/property.png'),
	'supports' =>array( 'title', 'editor', 'thumbnail' )
	); 
	register_post_type('property', $args);
	
	$args = array(
		'hierarchical'          => true,
		'show_ui'               => true,
		'labels'                => array('name' => 'Category' , 'menu_name' => 'Category' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'property_category' ),
	);
	register_taxonomy( 'property_category', 'property', $args );
	
	$args = array(
		'hierarchical'          => true,
		'show_ui'               => true,
		'labels'                => array('name' => 'Bed Room' , 'menu_name' => 'Bed Room' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'bedroom' ),
		'show_in_nav_menus'		=> false
	);
	register_taxonomy( 'bedroom', 'property', $args );
	
	$args = array(
		'hierarchical'          => true,
		'show_ui'               => true,
		'labels'                => array('name' => 'Bath Room' , 'menu_name' => 'Bath Room' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'bathroom' ),
		'show_in_nav_menus'		=> false
	);
	register_taxonomy( 'bathroom', 'property', $args );
	
	$args = array(
		'hierarchical'          => true,
		'show_ui'               => true,
		'labels'                => array('name' => 'Car Space' , 'menu_name' => 'Car Space' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'carspace' ),
		'show_in_nav_menus'		=> false
	);
	register_taxonomy( 'carspace', 'property', $args );
	
	$args = array(
		'hierarchical'          => true,
		'show_ui'               => true,
		'labels'                => array('name' => 'Residency Type' , 'menu_name' => 'Residency Type' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'residencytype' ),
	);
	register_taxonomy( 'residencytype', 'property', $args );
	
	$args = array(
		'hierarchical'          => false,
		'show_ui'               => true,
		'labels'                => array('name' => 'Close to' , 'menu_name' => 'Close to' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'closeto' ),
		'show_in_nav_menus'		=> false
	);
	register_taxonomy( 'closeto', 'property', $args );
	
	$args = array(
		'hierarchical'          => false,
		'show_ui'               => true,
		'labels'                => array('name' => 'Away from' , 'menu_name' => 'Away from' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'awayfrom' ),
		'show_in_nav_menus'		=> false
	);
	register_taxonomy( 'awayfrom', 'property', $args );
	
	$args = array(
		'hierarchical'          => false,
		'show_ui'               => true,
		'labels'                => array('name' => 'Features' , 'menu_name' => 'Features' ),
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'features' ),
		'show_in_nav_menus'		=> false
	);
	register_taxonomy( 'features', 'property', $args );
	
}


add_action( 'admin_init', 'my_admin_property' );
function my_admin_property() {
	add_meta_box( 'property_meta_box', 'Property Details', 'display_property_meta_box','property', 'normal', 'high' );
}

function display_property_meta_box( $property ) {
	?>
	<table>
		
        <tr>
            <td style="width: 100%">Address</td>
            <td><textarea style="width:425px; height:100px;" name="meta[full_addrerss]" class="mceEditor" id='textareaID'><?php echo esc_html( get_post_meta( $property->ID, 'full_addrerss', true ) );?></textarea>
            <p class="howto">( Please put correct address )</p>
            </td>
		</tr>
        
	</table>
<?php 
}

add_action( 'save_post', 'add_property_fields', 10, 2 );

function add_property_fields( $property_id, $property ) {
	if ( $property->post_type == 'property' ) {
		if ( isset( $_POST['meta'] ) ) {
			foreach( $_POST['meta'] as $key => $value ){
				update_post_meta( $property_id, $key, $value );
			}
		}
	}
}


class PropertySearchWidget extends WP_Widget
{
  function PropertySearchWidget()
  {
    $widget_ops = array('classname' => 'PropertySearchWidget', 'description' => 'Displays Property Search Form' );
    $this->WP_Widget('PropertySearchWidget', 'PropertySearch', $widget_ops);
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
	?>
    <div class="search-form">
        <h2>Search Property</h2>
        <form action="<?php bloginfo( 'url' );?>" method="get" >
            <input type="hidden" name="post_type" value="property" />
            <input type="text" name="s" id="search-key" value="<?php echo get_query_var( 's' ) ?>" class="input-block-level" placeholder="Enter suburb, postcode or property ID" />
            <div class="row-fluid">
                <div class="span4">
                    <label class="bedroom-icon">Bedrooms</label>
                </div>
                <div class="span8">
                    <select name="bedroom" class="input-block-level">
                        <option value="">Any</option>
                        <?php
                        $terms = get_terms("bedroom");
                        $count = count($terms);
                        if ( $count > 0 ){
                            foreach ( $terms as $term ) {?>
                            <option <?php if( get_query_var( 'bedroom' ) == $term->name ){?> selected <?php }?>><?php echo $term->name;?></option>
                        <?php }
                        }?>
                    </select>
                </div>
            </div>
            <div class="row-fluid">
                <div class="span4">
                    <label class="bathroom-icon">Bathrooms</label>
                </div>
                <div class="span8">
                    <select name="bathroom" class="input-block-level">
                        <option value="">Any</option>
                        <?php
                        $terms = get_terms("bathroom");
                        $count = count($terms);
                        if ( $count > 0 ){
                            foreach ( $terms as $term ) {?>
                            <option <?php if( get_query_var( 'bathroom' ) == $term->name ){?> selected <?php }?>><?php echo $term->name;?></option>
                        <?php }
                        }?>
                    </select>
                </div>
            </div>
            <div class="row-fluid">
                <div class="span4">
                    <label class="car-icon">Car space</label>
                </div>
                <div class="span8">
                    <select name="carspace" class="input-block-level">
                        <option value="">Any</option>
                        <?php
                        $terms = get_terms("carspace");
                        $count = count($terms);
                        if ( $count > 0 ){
                            foreach ( $terms as $term ) {?>
                            <option <?php if( get_query_var( 'carspace' ) == $term->name ){?> selected <?php }?>><?php echo $term->name;?></option>
                        <?php }
                        }?>
                    </select>
                </div>
            </div>
            <div class="row-fluid">
                <div class="span4">
                    <label class="home-icon">Type</label>
                </div>
                <div class="span8">
                    <select name="residencytype" class="input-block-level">
                        <option value="">Any</option>
                        <?php
                        $terms = get_terms("residencytype");
                        $count = count($terms);
                        if ( $count > 0 ){
                            foreach ( $terms as $term ) {?>
                            <option <?php if( get_query_var( 'residencytype' ) == $term->name ){?> selected <?php }?>><?php echo $term->name;?></option>
                        <?php }
                        }?>
                    </select>
                </div>
            </div>
            <div class="advance-search">
            <h2>Search with</h2>
                <div class="row-fluid">
                    <div class="span6">
                        <h3>Close to</h3>
                        <?php
                        $terms = get_terms("closeto");
                        $count = count($terms);
                        if ( $count > 0 ){
                            foreach ( $terms as $term ) {?>
                            <div class="checkbox">
                                <label>
                                    <input name="closeto" value="<?php echo $term->name;?>" type="checkbox" /><?php echo $term->name;?>
                                </label>
                            </div>
                        <?php }
                        }?>
                    </div>
                    <div class="span6">
                        <h3>Stay away from..</h3>
                        <?php
                        $terms = get_terms("awayfrom");
                        $count = count($terms);
                        if ( $count > 0 ){
                            foreach ( $terms as $term ) {?>
                            <div class="checkbox">
                                <label>
                                    <input name="awayfrom" value="<?php echo $term->name;?>" type="checkbox" /><?php echo $term->name;?>
                                </label>
                            </div>
                        <?php }
                        }?>
                    </div>
                </div>
            </div>
            <div class="submit">
                <input type="submit" class="btn btn-new" value="Search Now" />
            </div>
        </form>
    </div>
    <?php
	echo $after_widget;
  }
 
}
add_action( 'widgets_init', create_function('', 'return register_widget("PropertySearchWidget");') );
?>