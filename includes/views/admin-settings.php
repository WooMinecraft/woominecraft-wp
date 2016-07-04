<div class="wrap">
	<h2><?php printf( __( ' %s Options', 'woominecraft' ), 'WooMinecraft' ); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( 'woo_minecraft' ); ?>
		<table class="woominecraft form-table">
			<thead>
				<tr>
					<th><?php _e( 'Server Label', 'woominecraft' ); ?></th>
					<th><?php _e( 'Server Key', 'woominecraft' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<tr>
				<?php
				$option = get_option( 'wm_servers', array() );
				if ( empty( $option ) ) {
					?>
					<td>
						<input type="text" name="wm_server[0][name]" value="<?php echo ''; ?>" />
					</td>
					<td>
						<input type="text" name="wm_server[0][key]" id="wm_key" value="<?php echo get_option( 'wm_key' ); ?>"/>
						<p class="description"><?php printf( __( 'Type %s in-game as op to get your key.', 'woominecraft' ), '/woo register' ); ?></p>
					</td>
					<?php
				} else {
					$count = 0;
					foreach ( $option as $server ) {
						?>
						<td>
							<input type="text" name="wm_server[<?php echo $count; ?>][name]" value="<?php echo esc_attr( $server['name'] ); ?>"/>
						</td>
						<td>
							<input type="text" name="wm_server[<?php echo $count; ?>][key]" id="wm_key" value="<?php echo esc_attr( $server['key'] ); ?>"/>
							<p class="description"><?php printf( __( 'Type %s in-game as op to get your key.', 'woominecraft' ), '/woo register' ); ?></p>
						</td>
						<?php
						$count ++;
					}
				}
				?>
			</tr>
			</tbody>
		</table>
		<?php echo get_submit_button(); ?>
	</form>
</div>
