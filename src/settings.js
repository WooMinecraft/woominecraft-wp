import ReactDOM from 'react-dom';
import {useState, useEffect} from 'react';
import apiFetch from '@wordpress/api-fetch';
import {__} from '@wordpress/i18n';
import {Button} from '@wordpress/components';

import ServerSettingsRows from "./Settings/rows";

const WmcSettings = () => {
	const [servers, setServers] = useState([])

	const updateServers = servers => {
		setServers( servers );
	};

	const deleteServer = server => {
		const newServers = servers.filter( s => s !== server );

		// console.log( {server, servers, newServers} );
		updateServers( newServers );
	};

	const emptyServer =  { key: '', name: '' };

	useEffect( () => { loadData(); }, [] );

	const loadData = async () => {
		const {wm_servers} = await apiFetch(
			{ path: '/wp/v2/settings' }
		);

		setServers( wm_servers );
	};

	return (
		<>
			<table className="wc_shipping widefat">
				<thead>
					<tr>
						<th className="wmc_label">{__('Server Label', 'woominecraft')}</th>
						<th className="wmc_key">{__('Server Key', 'woominecraft')}</th>
						<th>
							{/* @TODO: Disable button until data is loaded. */}
							<Button
								isSecondary
								onClick={ () => {
									updateServers( [ ...servers, emptyServer ] )
								} }
							>
								{__('Add Server', 'woominecraft')}
							</Button>
						</th>
					</tr>
				</thead>
				<tbody>
					<ServerSettingsRows
						deleteServer={deleteServer}
						updateServers={updateServers}
						servers={servers}
					/>
				</tbody>
			</table>
		</>
	)
}
ReactDOM.render( <WmcSettings />, document.getElementById('wmc-settings' ) );
