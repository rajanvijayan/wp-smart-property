<?php
/*
 * @Author 		wp-smart-property
 * @Author 		WP Smart Plugin
 * Copyright: 	https://www.wpsmartplugin.com/
 */
class propertyMetaBox {
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );
	}
	public function add_meta_box( $post_type ) {
	$post_types = array('post', 'property');
		if ( in_array( $post_type, $post_types )) {
			add_meta_box(
				'additional_property_information'
				,__( 'Additional Property Information', 'wp-smart-property' )
				,array( $this, 'property_meta_box_content' )
				,$post_type
				,'advanced'
				,'high'
			);
		}
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST['property_meta_box_nonce'] ) )
			return $post_id;

		$nonce = $_POST['property_meta_box_nonce'];
		if ( ! wp_verify_nonce( $nonce, 'property_meta_box' ) )
			return $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return $post_id;
		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return $post_id;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}
		//$mydata = sanitize_text_field( $_POST['myplugin_new_field'] );
		if ( isset( $_POST['property_additional_field'] ) ) {
			foreach( $_POST['property_additional_field'] as $key => $value ){
				update_post_meta( $post_id, $key, $value );
			}
		}
		//update_post_meta( $post_id, '_my_meta_value_key', $mydata );
	}
	
	public function property_meta_box_content( $post ) {
		wp_nonce_field( 'property_meta_box', 'property_meta_box_nonce' );
		?>
		<p><label for="property_address"><?php _e( 'Property address', 'wp-smart-property' );?></label></p>
        <textarea id="property_address" name="property_additional_field[property_address]"  cols="40" rows="3"><?php echo esc_html( get_post_meta( $post->ID, 'property_address', true ) );?></textarea>
        
        <p><label for="admin_notes"><?php _e( 'Admin notes for this property', 'wp-smart-property' );?></label></p>
        <textarea id="admin_notes" name="property_additional_field[admin_notes]"  cols="40" rows="3"><?php echo esc_html( get_post_meta( $post->ID, 'admin_notes', true ) );?></textarea>
        <?php
	}
}
$metaBox = new propertyMetaBox;
?>