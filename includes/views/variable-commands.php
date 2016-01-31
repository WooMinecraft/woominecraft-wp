<tr>
	<td>
		<div class="woo_minecraft_v">
			<p class="title"><?php _e( 'WooMinecraft', 'wmc' ); ?></p>

			<p class="form-field woo_minecraft woo_minecraft_v">
				<label><?php _e( 'Commands', 'wmc' ); ?></label>
				<input type="button" class="button button-primary woo_minecraft_add" name="Add" id="woo_minecraft_add_v" value="<?php _e( 'Add', 'wmc' ); ?>" />
				<input type="button" class="button woo_minecraft_reset" name="Reset" id="woo_minecraft_reset_v" value="<?php _e( 'Reset Fields', 'wmc' ); ?>"/>
				<img class="help_tip" data-tip="<?php _e( 'Use %s for the player\'s name.<br /><br />No leading slash is needed.', 'wmc' ); ?>" src="<?php echo plugins_url( 'help.png', dirname( __FILE__ ) ) ?>" height="16" width="16"/>
				<span class="woo_minecraft_copyme" style="display:none">
					<input type="text" name="minecraft_woo[variable][<?php echo $loop; ?>][]" value="" class="short" placeholder="<?php _e( 'Use %s for player name', 'wmc' ); ?>" />
					<input type="button" class="button button-small delete remove_row" value="<?php _e( 'Delete', 'wmc' ); ?>">
				</span>
				<?php if ( ! empty( $meta ) ) : ?>
					<?php foreach ( $meta as $command ) : ?>
						<span>
							<input type="text" name="minecraft_woo[variable][<?php echo $loop; ?>][]" value="<?php echo $command; ?>" class="short"/>
							<input type="button" class="button button-small delete remove_row" value="<?php _e( 'Delete', 'wmc' ); ?>">
						</span>
					<?php endforeach; ?>
				<?php endif; ?>
			</p>
		</div>
	</td>
</tr>
