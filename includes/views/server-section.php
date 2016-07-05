<tr valign="top" class="woominecraft">
	<th scope="row" class="titledesc"><?php _e( 'Available Servers', 'woominecraft' ); ?></th>
	<td>
		<table class="wc_shipping widefat" cellspacing="0">
			<thead>
			<tr>
				<th class="sort"></th>
				<th class="wmc_label"><?php _e( 'Dropdown Label', 'woominecraft' ); ?></th>
				<th class="wmc_key"><?php _e( 'Key', 'woominecraft' ); ?></th>
				<th><input type="button" class="button button-primary wmc_add_server" value="<?php _e( 'Add Server', 'woominecraft' ); ?>" /> </th>
			</tr>
			</thead>
			<tbody>
			<?php $count = 0; foreach ( woo_minecraft()->admin->get_servers() as $server ) : ?>
				<tr class="row">
					<td width="1%" class="sort"></td>
					<td class="wmc_name"><input type="text" name="wmc_servers[<?php echo $count; ?>][name]" value="<?php echo $server['name']; ?>" /></td>
					<td class="wmc_key"><input type="text" name="wmc_servers[<?php echo $count; ?>][key]" value="<?php echo $server['key']; ?>" class="widefat" /></td>
					<td><input type="button" class="button button-secondary wmc_delete_server" value="<?php _e( 'Remove Server', 'woominecraft' ); ?>" /> </td>
				</tr>
			<?php $count++; endforeach; ?>
			</tbody>
		</table>
	</td>
</tr>
