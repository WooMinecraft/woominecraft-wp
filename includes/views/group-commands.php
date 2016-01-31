<div class="woo_minecraft">
	<p class="title"><?php _e( 'WooMinecraft', 'wmc' ); ?></p>

	<p class="form-field woo_minecraft">
		<label for="woo_minecraft_general"><?php _e( 'Commands', 'wmc' ); ?></label>
		<input type="button" class="button button-primary woo_minecraft_add" name="Add" id="woo_minecraft_add" value="<?php _e( 'Add', 'wmc' ); ?>"/>
		<input type="button" class="button woo_minecraft_reset" name="Reset" id="woo_minecraft_reset" value="<?php _e( 'Reset Fields', 'wmc' ); ?>"/>
		<img class="help_tip" data-tip="<?php _e( 'Any commands added here, will run on top of variable commands if any. <br /><br />No leading slash is needed.', 'wmc' ); ?> />" src="<?php echo plugins_url( 'help.png', dirname( __FILE__ ) ); ?>" height="16" width="16"/>
		<span class="tip-test tip tips" data-tip="This is a tip"></span>
				<span class="woo_minecraft_copyme" style="display:none">
					<input type="text" name="minecraft_woo[general][]" value="" class="short" placeholder="<?php _e( 'Use %s for player name', 'wmc' ); ?>"/>
					<input type="button" class="button button-small delete remove_row" value="Delete">
				</span>
		<?php if ( ! empty( $meta ) ) : ?>
			<?php foreach ( $meta as $command ) : ?>
				<span>
					<input type="text" name="minecraft_woo[general][]" value="<?php echo $command; ?>" class="short"/>
					<input type="button" class="button button-small delete remove_row" value="<?php _e( 'Delete', 'wmc' ); ?>">
				</span>
			<?php endforeach; ?>
		<?php endif; ?>
	</p>
</div>
