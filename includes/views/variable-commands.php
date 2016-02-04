<div class="woo_minecraft">
	<p class="title"><?php _e( 'WooMinecraft Commands', 'wmc' ); ?></p>
	<div class="form-fields">
		<div>
			<input type="button" class="button button-primary woo_minecraft_add" name="Add" id="woo_minecraft_add" value="<?php _e( 'Add', 'wmc' ); ?>"/>
			<input type="button" class="button woo_minecraft_reset" name="Reset" id="woo_minecraft_reset" value="<?php _e( 'Reset Fields', 'wmc' ); ?>"/>
			<span class="woocommerce-help-tip" data-tip="<?php _e( 'Any commands added here, will run on top of variable commands if any; no leading slash is needed.', 'wmc' );  ?>"></span>
		</div>
		<span class="woo_minecraft_copyme command" style="display:none">
			<input type="text" name="minecraft_woo[<?php echo $variation->ID; ?>][]" value="" class="short" placeholder="<?php _e( 'Use %s for player name', 'wmc' ); ?>"/>
			<input type="button" class="button button-small delete remove_row" value="Delete">
		</span>
		<?php if ( ! empty( $meta ) ) : ?>
			<?php foreach ( $meta as $command ) : ?>
				<span class="command">
					<input type="text" name="minecraft_woo[<?php echo $variation->ID; ?>][]" value="<?php echo $command; ?>" class="short"/>
					<input type="button" class="button button-small delete remove_row" value="<?php _e( 'Delete', 'wmc' ); ?>">
				</span>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
