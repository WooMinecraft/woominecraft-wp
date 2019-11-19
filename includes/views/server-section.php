<tr valign="top" class="woominecraft">
	<th scope="row" class="titledesc"><?php esc_html_e( 'Available Servers', 'woominecraft' ); ?></th>
	<td>
		<p class="description"><?php esc_html_e( 'CAUTION: Changing your server keys will invalidate any/all orders with the previous key. You cannot undo this.', 'woominecraft' ); ?></p>
		<br /><br />
		<table class="wc_shipping widefat" cellspacing="0">
			<thead>
			<tr>
				<th class="sort"></th>
				<th class="wmc_label"><?php esc_html_e( 'Dropdown Label', 'woominecraft' ); ?></th>
				<th class="wmc_key"><?php esc_html_e( 'Key', 'woominecraft' ); ?></th>
				<th><input type="button" class="button button-primary wmc_add_server" value="<?php esc_attr_e( 'Add Server', 'woominecraft' ); ?>" /> </th>
			</tr>
			</thead>
			<tbody>
			<?php $count = 0; foreach ( \WooMinecraft\WooCommerce\get_servers() as $server ) : ?>
				<tr class="row">
					<td width="1%" class="sort"></td>
					<td class="wmc_name"><input type="text" name="wmc_servers[<?php echo (int) $count; ?>][name]" value="<?php echo esc_attr( $server['name'] ); ?>" /></td>
					<td class="wmc_key"><input type="text" name="wmc_servers[<?php echo (int) $count; ?>][key]" value="<?php echo esc_attr( $server['key'] ); ?>" class="widefat" /></td>
					<td><input type="button" class="button button-secondary wmc_delete_server" value="<?php esc_html_e( 'Remove Server', 'woominecraft' ); ?>" /> </td>
				</tr>
			<?php
				$count++;
				endforeach; // Endforeach.
			?>
			</tbody>
		</table>
	</td>
</tr>
