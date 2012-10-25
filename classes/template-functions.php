<?php

/**
 * Complete scan of the entire site. 
 *
 * This will add postIDs to all matching attachments.
 *
 * @since     0.1
 */
function cpum_scan_site()
{
	set_time_limit(360);
	
	$posts = get_posts(array(
		'post_type'     => 'any',
		'numberposts'   => -1
	));

	if ( empty($posts) )
		return false;        
	
	$titles = array();
	
	foreach ( $posts as $post ){            
		$attachment_ids = Codepress_Used_Media::add_postid_to_attachments($post->ID, $post);
		
		if ( $attachment_ids ) {
			foreach ( $attachment_ids as $id ) {
				
				$title = get_the_title($id);				
				if ( $link = get_edit_post_link($id) ) {
					$title = "<a href='{$link}'>{$title}</a>";
				}
				$titles[] = $title;
			}
		}
	}
	
	return array_unique( $titles );
}