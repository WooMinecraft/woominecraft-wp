<?php
/**
 * @global array $commands
 */
$servers = woo_minecraft()->admin->get_servers();
?>
<div class="woo_minecraft">
	<p class="title"><?php _e( 'WooMinecraft Commands', 'woominecraft' ); ?><span class="woocommerce-help-tip" data-tip="<?php _e( 'Any commands added here, will run on top of variable commands if any; no leading slash is needed.', 'woominecraft' );  ?>"></span></p>
	<table class="woominecraft commands" cellpadding="5px">
		<thead>
			<tr>
				<th class="command"><?php _e( 'Command', 'woominecraft' ); ?></th>
				<th class="server"><?php _e( 'Server', 'woominecraft' ); ?></th>
				<th class="buttons"><input type="button" class="button button-small button-primary wmc_add_server" value="<?php _e( 'Add Command', 'woominecraft' ); ?>" /></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! isset( $commands ) || empty( $commands ) ) : ?>
			<tr class="row">
				<td><input type="text" name="wmc_commands[0][command]" class="widefat" placeholder="<?php _e( 'give %s apple 1', 'woominecraft' ); ?>" /> </td>
				<td>
					<select name="wmc_commands[0][server]" >
					<?php
					foreach ( $servers as $server ) {
						printf( '<option value="%s">%s</option>', $server['key'], $server['name'] );
					}
					?>
					</select>
				</td>
				<td><input type="button" class="button button-small wmc_delete_server" value="<?php _e( 'Delete Command', 'woominecraft' ); ?>" /></td>
			</tr>
			<?php else : ?>
				<?php $offset = 0; foreach ( $commands as $server_key => $command ) : ?>
					<?php foreach( $command as $player_command ) : ?>
					<tr class="row">
						<td><input type="text" name="wmc_commands[<?php echo $offset; ?>>][command]" class="widefat" placeholder="<?php _e( 'give %s apple 1', 'woominecraft' ); ?>" value="<?php echo $player_command; ?>" /> </td>
						<td>
							<select name="wmc_commands[<?php echo $offset; ?>>][server]" >
								<?php
								foreach ( $servers as $server ) {
									printf( '<option value="%s" %s>%s</option>', $server['key'], selected( $server['key'], $server_key, false ), $server['name'] );
								}
								?>
							</select>
						</td>
						<td><input type="button" class="button button-small wmc_delete_server" value="<?php _e( 'Delete Command', 'woominecraft' ); ?>" /></td>
					</tr>
					<?php $offset++; endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
