<div class="wrap">
	<h2><?php printf( __( ' %s Options', 'wmc' ), 'WooMinecraft' ); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( 'woo_minecraft' ); ?>
		<table class="form-table wide-fat">
			<tbody>
			<tr>
				<th><label for="wm_key"><?php _e( 'Game Key', 'wmc' ); ?></label></th>
				<td><input type="text" name="wm_key" id="wm_key" value="<?php echo get_option( 'wm_key' ); ?>"/>
					<p class="description"><?php printf( __( 'Type %s in-game as op to get your key.', 'wmc' ), '/woo register' ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>
		<?php echo get_submit_button(); ?>
	</form>
</div>
