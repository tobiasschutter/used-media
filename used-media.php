<?php
/*

Plugin Name: 		Used Media
Version: 			0.1
Description: 		Is your media used inside a Post? This plugin will show it!
Author: 			Codepress
Author URI: 		http://www.codepress.nl
Plugin URI: 		http://www.codepress.nl/plugins/used-media/
Text Domain: 		used-media
Domain Path: 		/languages
License:			GPLv2

Copyright 2012  Codepress  info@codepress.nl

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'CPUM_VERSION', 	'0.1' );
define( 'CPUM_TEXTDOMAIN', 	'used-media' );
define( 'CPUM_SLUG', 		'used-media' );
define( 'CPUM_URL', 		plugins_url('', __FILE__) );

// After saving the post we want to check which attachments are being used and add the respective post ID.
add_action('save_post', array( 'Codepress_Used_Media', 'add_postid_to_attachments' ),10 ,2);

// After deleting a post we will 'remove' the attachments
add_action('delete_post', array( 'Codepress_Used_Media', 'delete_postid_from_attachments' ),10 ,1);

// Add Post Titles to attachment edit screen
add_filter('attachment_fields_to_edit', array( 'Codepress_Used_Media', 'add_post_titles_to_attachment_edit' ), 10, 2);

// register settings menu
add_action( 'admin_menu', array( 'Codepress_Used_Media', 'settings_menu' ) );
add_action( 'admin_init', array( 'Codepress_Used_Media', 'handle_requests' ) );

// register columns
add_filter( 'manage_media_columns', array( 'Codepress_Used_Media', 'add_used_by_column_heading' ), 1, 1 );
add_action( 'manage_media_custom_column', array( 'Codepress_Used_Media', 'manage_used_by_column_value' ), 10, 2 );

// styling & scripts
add_action( 'admin_enqueue_scripts' , array( 'Codepress_Used_Media', 'scripts' ) );

// warning message
add_filter( 'media_row_actions', array( 'Codepress_Used_Media', 'change_delete_warning' ), 10, 3);
add_filter( 'plugin_action_links',  array( 'Codepress_Used_Media', 'add_settings_link' ), 1, 2);

/**
 * Template functions
 *
 * @since     0.1
 */
require_once dirname( __FILE__ ) . '/classes/template-functions.php';

/**
 * Codepress_Used_Media Class
 *
 * @since     0.1
 *
 */
class Codepress_Used_Media
{
	protected static $key = 'used_by_post';
	
	protected $media_id;	
	
	/**
	 * Constructor
	 *
	 * @since     0.1
	 */
	function __construct( $media_id )
	{
		$this->media_id = $media_id;
	}	
	
    /**
	 * Add Post ID to attachment after being saved
	 *
	 * @since     0.1
	 */
    public static function add_postid_to_attachments($post_id, $post)
    {
        if ( in_array( $post->post_type, array( 'revision', 'nav_menu_item', 'attachment' ) ) )
            return false;
		
		// remove post ID from all attachments
		self::delete_postid_from_attachments($post_id);
		
		// add post ID to attachment
		$attachments = self::get_attachments_from_document($post->post_content);
		
		if ( $attachments ) {
           foreach ( $attachments as $attachment_id ) {
				add_post_meta($attachment_id, self::$key, $post_id);
            }

			return $attachments;			
        }
		
		return false;
    }
    
    /**
	 * Remove Post ID from attachment after being removed
	 *
	 * @since     0.1
	 */
    public static function delete_postid_from_attachments($post_id)
    {
        $attachments = get_posts(array(
			'post_type'		=> 'attachment',
			'numberposts'	=> -1,
			'meta_query'	=> array(
				array(
					'key'	=> 'used_by_post',
					'value'	=> $post_id
				)
			),
			'fields'		=> 'ids'
		));
		if ( $attachments ) {
           foreach ( $attachments as $attachment_id ) {			  
			   delete_post_meta($attachment_id, self::$key, $post_id);
            }           
        }
    }
    
    /**
	 * Get attachments from content
	 *
	 * @since     0.1
	 */
    private static function get_attachments_from_document( $content )
    {
        if ( empty( $content ) )
            return array();
        
        $attachments = array();

        $dom = new domDocument;
        $dom->loadHTML( $content );
        $dom->preserveWhiteSpace = false;
        
        // images        
        if ( $images = $dom->getElementsByTagName('img') ) {
            foreach ($images as $image) {
                $class = $image->getAttribute('class');
                                                
                // image found
                $result = preg_match('/wp-image-([1-9][0-9]*)/', $class, $matches);
                if ( $result && isset($matches[1]) ){
                    if ( $attachment = get_post($matches[1]) ) {
                        $attachments[] = $attachment->ID;                        
                    }
                }  
            }
        }
        
        // anchors
        $anchors = $dom->getElementsByTagName('a');
        if ( $anchors ) {
            foreach ($anchors as $anchor) {
                $rel = $anchor->getAttribute('rel');                                                
               
                // attachment found
                $result = preg_match('/wp-att-([1-9][0-9]*)/', $rel, $matches);                
                if ( $result && isset($matches[1]) ){                    
                    if ( $attachment = get_post($matches[1]) ) {
                        $attachments[] = $attachment->ID;                        
                    }
                }  
            }
        }
        
        return array_unique($attachments);
    }
	
	/**
	 * Add post titles to attachment edit page
	 *
	 * @since     0.1
	 */
	public function add_post_titles_to_attachment_edit( $form_fields, $post )
	{
		$self = new self( $post->ID );		
		
		$form_fields['post_titles'] = array(
			'label'      => __('Used By', CPUM_TEXTDOMAIN),
			'input'      => 'html',
			'html'       => $self->get_post_titles()
		);
		
		return $form_fields;
	}
	
	/**
	 * Get Post Titles by Attachment ID
	 *
	 * @since     0.1
	 */
	function get_post_titles()
	{
		$text = array();
		
		// Image used by Editor
		$post_ids = get_post_meta( $this->media_id, self::$key);
		if ( $titles = self::get_titles_by_post_ids( $post_ids, 'editor' ) ) {
			$text[] = "<span title='" . __('This Attachment is used in the Editor.', CPUM_TEXTDOMAIN ) . "' class='cpum-editor'></span> {$titles}";
		}
		
		// Image used as Featured Image
		$post_ids = $this->get_post_ids_by_featured_image();
		if ( $titles = self::get_titles_by_post_ids( $post_ids, 'featured' ) ) {
			$text[] = "<span title='" . __('This Attachment is used as Featured Image.', CPUM_TEXTDOMAIN ) . "' class='cpum-featured'></span> {$titles}";
		}
		
		// Image used by Custom Field		
		$post_ids = $this->get_post_ids_by_custom_fields();
		if ( $titles = self::get_titles_by_post_ids( array_unique($post_ids), 'custom_field' ) ) {
			$text[] = "<span title='" . __('This Attachment is used in a Custom Field.', CPUM_TEXTDOMAIN ) . "' class='cpum-custom_field'></span> {$titles}";
		}
		
		return implode( '<br/>', $text );		
	}
	
	/**
	 * Get Post ID's of Featured Images
	 *
	 * @since     0.1
	 */
	function get_post_ids_by_featured_image()
	{
		$post_ids = get_posts(array(
			'post_type'		=> 'any',
			'numberposts'	=> -1,
			'meta_query'	=> array(
				array(
					'key'	=> '_thumbnail_id',
					'value'	=> $this->media_id
				)
			),
			'fields'		=> 'ids'
		));
		
		return $post_ids;
	}
	
	/**
	 * Get Post ID's by Custom Fields
	 *
	 * @since     0.1
	 */
	function get_post_ids_by_custom_fields()
	{
		$post_ids = array();
		
		$custom_fields = apply_filters('cpum-custom-fields', array());
		
		if ( $custom_fields ) {			
			foreach ( $custom_fields as $field ) {
				$custom_post_ids = get_posts(array(
					'post_type'		=> 'any',
					'numberposts'	=> -1,
					'meta_query'	=> array(
						array(
							'key'		=> $field,
							'value'		=> $this->media_id
						)
					),
					'fields'	=> 'ids'
				));
				if ( $custom_post_ids ) {
					$post_ids = array_merge($post_ids, $custom_post_ids);
				}
			}			
		}
		
		return $post_ids;
	}
	
	/**
	 * Get Post Titles by Post ID's
	 *
	 * @since     0.1
	 */
	public static function get_titles_by_post_ids( $post_ids, $class = '' )
	{
		$titles = array();
		
		if ( $post_ids ) {
			foreach ( $post_ids as $id ) {
				$p = get_post($id);
				
				if ( !$p )
					continue; 

				$title = apply_filters('the_title', $p->post_title);
				if ( !$title ) {
					$title = $p->post_name;
				}
				
				$link  = get_edit_post_link($id);
				if ( $title ) {
					
					if ( 'featured' == $class ) {
						$attr_title = __('This image is used as a Featured Image.', CPUM_TEXTDOMAIN );
						if ( $link )
							$link .= '#postimagediv';
					}
						
					if ( 'editor' == $class ) {
						$attr_title = __('This media is used in the Editor.', CPUM_TEXTDOMAIN );
						if ( $link )
							$link .= '#postdivrich';
					}
					
					if ( 'custom_field' == $class ) {
						$attr_title = __('This media is used in a Custom Field.', CPUM_TEXTDOMAIN );			
					}
					
					$titles[] = $link ? "<a title='{$attr_title}' class='cpum-{$class}' href='{$link}'>{$title}</a>" : $title;
				}
			}
		}
		
		if ( !$titles )
			return false;
			
		return implode('<span class="cpum-divider"></span>', $titles);
	}
	
	/**
	 * Column Heading
	 *
	 * @since     0.1
	 */
	public static function add_used_by_column_heading( $columns )
	{
		$columns['used_by_post'] = __('Used By', CPUM_TEXTDOMAIN);

		return $columns;
	}
	
	/**
	 * Column Value
	 *
	 * @since     0.1
	 */
	public static function manage_used_by_column_value( $column_name, $media_id )
	{
		$self = new self( $media_id );
		
		if ( 'used_by_post' == $column_name ) {
		
			echo $self->get_post_titles();
		}
	}
	
	/**
	 * Register column css
	 *
	 * @since     0.1
	 */
	public static function scripts()
	{
		global $pagenow;
		
		if ( in_array($pagenow, array('upload.php', 'media.php', 'media-upload.php') ) || ( isset($_REQUEST['page']) && CPUM_SLUG == $_REQUEST['page'] ) ) {
		
			wp_enqueue_style( 'cpum-css', CPUM_URL.'/assets/css/admin.css', array(), CPUM_VERSION, 'all' );
			wp_enqueue_script( 'cpum-js', CPUM_URL.'/assets/js/admin.js', array('jquery'), CPUM_VERSION );
			
			// custom delete message
			wp_localize_script( 'cpum-js', 'cpumL10n', array( 
				'usedByEditor' 		=> __( "This media is used in the Post Editor. \n\n", CPUM_TEXTDOMAIN ),
				'usedAsFeatured' 	=> __( "This image is used as a Featured Image. \n\n", CPUM_TEXTDOMAIN ), 
				'isAttached' 		=> __( "This media is attached to a Post. \n\n", CPUM_TEXTDOMAIN ),
				'usedInCustomField' => __( "This media is used in a Custom Field. \n\n", CPUM_TEXTDOMAIN ) 
			));
		}
	}
	
	/**
	 * Add a warning when deleting Attachments 
	 * 
	 * When the attachment is used by a Post either in the Editor or as a Featured Image
	 *
	 * @since     0.1
	 */
	public static function change_delete_warning( $actions, $post, $detached )
	{
		// when this attachment is used by a post, set a custom warning	
		if ( 'attachment' === $post->post_type && 'trash' !== $post->post_status  ) {
			
			$self = new self( $post->ID );
			
			$by_editor 			= get_post_meta( $post->ID, self::$key ) ? 1 : 0;			
			$is_attached 		= $post->post_parent ? 1 : 0;
			$as_featured 		= $self->get_post_ids_by_featured_image() ? 1 : 0;
			$in_custom_field	= $self->get_post_ids_by_custom_fields() ? 1 : 0;
			
			$actions['delete'] = str_replace('return showNotice.warn();', "return showCustomDeleteNotice.warn({$by_editor},{$as_featured},{$is_attached},{$in_custom_field})", $actions['delete']);
		}		
		
		return $actions;
	}
	
	/**
	 * Admin message.
	 *
	 * @since     0.1
	 */
	
	public static function admin_message($message = "", $type = 'updated')
	{
		$GLOBALS['cpum_message'] = $message;
		$GLOBALS['cpum_message_type'] = $type;
		
		add_action('admin_notices', array( __CLASS__, 'admin_notice') );
	}
	
	function admin_notice()
	{
	    echo '<div class="' . $GLOBALS['cpum_message_type'] . '" id="message">'.$GLOBALS['cpum_message'].'</div>';
	}
	
	/**
	 * Admin Menu.
	 *
	 * Create the admin menu link for the settings page.
	 *
	 * @since     0.1
	 */
	public function settings_menu() 
	{
		$page = add_options_page(
			esc_html__( 'Used Media Settings', CPUM_TEXTDOMAIN ),
			esc_html__( 'Used Media', CPUM_TEXTDOMAIN ),
			'manage_options',
			CPUM_SLUG,
			array( __CLASS__, 'plugin_settings_page')
		);		
	}
	
	/**
	 * Add Settings link to plugin page
	 *
	 * @since     0.1
	 */
	public static function add_settings_link( $links, $file ) 
	{
		if ( $file != plugin_basename( __FILE__ ))
			return $links;

		array_unshift($links, '<a href="' . admin_url("admin.php") . '?page=' . CPUM_SLUG . '">' . __( 'Settings' ) . '</a>');
		return $links;
	}
	
	/**
	 * Handle Requests
	 *
	 * Handles the 'scan site' and 'restore plugin data' requests
	 *
	 * @since     0.1
	 */
	public function handle_requests()
	{
		if ( !isset($_REQUEST['page']) || CPUM_SLUG != $_REQUEST['page'] )
			return false;
			
		// scan website
		if ( isset($_REQUEST['_wpnonce_scan']) && wp_verify_nonce( $_REQUEST['_wpnonce_scan'], CPUM_SLUG ) ) {
			$message = "<p>" . __('No attachments found.', CPUM_TEXTDOMAIN) . "</p>";
			
			if ( $result = cpum_scan_site() ) {				
				
				// get titles of the media items
				$titles = implode(', ', $result);
				$count  = count($result);
				if ( $count > 80 ) {
					$titles = implode(',  ', array_slice($result, 0, 80)) . '[...]';
				}
				
				$message = "<p>" . sprintf( __('The following <strong>%s media library item(s)</strong> have been scanned and tagged: ', CPUM_TEXTDOMAIN), $count) . $titles . " <a class='button' href='" . trailingslashit(get_admin_url()) . "upload.php'>" . __('Go to Media', CPUM_TEXTDOMAIN) . "</a></p>";
			
			}

			self::admin_message($message, 'updated');
		}
		
		// reset
		if ( isset($_REQUEST['_wpnonce_reset']) && wp_verify_nonce( $_REQUEST['_wpnonce_reset'], CPUM_SLUG ) ) {
			
			$attachments = get_posts(array(
				'post_type'		=> 'attachment',
				'numberposts'	=> -1,
				'meta_query'	=> array(
					array(
						'key'		=> self::$key,
						'value'		=> array(''),
						'compare' 	=> 'NOT IN'
					)
				),
				'fields'	=> 'ids'
			));
			
			$result = array();
			if ($attachments) {
				foreach ( $attachments as $id ) {
					$result[] = delete_metadata( 'post', $id, self::$key);
				}
			}
			
			$message = "<p>" . __('No plugin data found.',  CPUM_TEXTDOMAIN) . "</p>";
			
			if ( $count = count($result) ) {
				$message = "<p>" . sprintf( __('%s attachments found. Succesfully restored plugin data.',  CPUM_TEXTDOMAIN), "<strong>{$count}</strong>") . "</p>";
			}
			
			self::admin_message( $message, 'updated');
		}
	}
	
	/**
	 * Settings Page Template.
	 *
	 * @since     0.1
	 */
	public function plugin_settings_page() 
	{
		
	?>
	<div id="cpum" class="wrap">
		<?php screen_icon(CPUM_SLUG) ?>
		<h2><?php _e('Used Media', CPUM_TEXTDOMAIN); ?></h2>
		
		<p>
			<?php _e('This plugin will check if a media item is used by a Post(type). It will look for media in the <strong>Content</strong> of a post, or if it has been set as <strong>Featured Image</strong>, or if is has been used in a <strong>Custom Field</strong>.', CPUM_TEXTDOMAIN); ?>
		</p>
		
		<ol>
			<li><?php _e('Click \'Scan Site\'.', CPUM_TEXTDOMAIN); ?></li>
			<li><?php printf( __('Go to your <a href="%1$s">Media Library</a> after the scan. In the Media Library you will see which Post uses that item in the added column \'Used By\'.', CPUM_TEXTDOMAIN), get_admin_url() . 'upload.php' ); ?></li>
			<li><?php _e('You will only need to run this once. Every time a post is updated it will be scanned automatically.', CPUM_TEXTDOMAIN); ?></li>
		</ol>
		
		<form method="post" action="">
			<?php wp_nonce_field( CPUM_SLUG, '_wpnonce_scan' ) ?>
			<p class="submit">				
				<input type="submit" class="button-primary" value="<?php _e('Scan Site', CPUM_TEXTDOMAIN) ?>" />
				<span class="description"><?php _e('You only need to run this once.', CPUM_TEXTDOMAIN ) ?></span>
			</p>			
		</form>	
		
		<form action="" method="post">
			<?php wp_nonce_field( CPUM_SLUG, '_wpnonce_reset' ) ?>
			<input type="submit" onclick="return confirm('<?php _e("Warning! ALL saved plugin data will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", CPUM_TEXTDOMAIN ); ?>');" value="<?php _E('Restore default settings', CPUM_TEXTDOMAIN ) ?>" name="cpum-restore-defaults" class="button">
			<span class="description"><?php _e('Only use this if you want the reset all plugin data.', CPUM_TEXTDOMAIN ) ?></span>
		</form>	

		
	</div>
	<?php
	}
}
