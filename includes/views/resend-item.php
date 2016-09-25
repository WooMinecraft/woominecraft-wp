<?php
/**
 * @global WP_Post $post
 * @global array $item
 * 
 * @todo: provide a dropdown select of registered servers to select from
 */
?>
<span class="woominecraft resend_item">
		<button class="button button-primary woominecraft_resend_donation" data-orderid="<?php echo $post->ID ?>" data-variation="<?php echo $item['variation_id'] ?>">
			<span><?php _e( 'Resend Donation', 'woominecraft' ); ?></span>
		</button>
</span>
