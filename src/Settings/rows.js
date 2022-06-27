/** @jsx jsx */
import {Spinner,Button} from "@wordpress/components";
import {jsx} from "@emotion/react";
import {__} from '@wordpress/i18n'

const ServerSettingsRows = ({servers, deleteServer, updateServers}) => {

	let newServers = Array.from( servers );
	// console.log( {servers} );

	const updateServerName = ( name, index ) => {
		newServers[index].name = name;
		updateServers( newServers );
	};

	const updateServerKey = ( key, index ) => {
		newServers[index].key = key;
		updateServers( newServers );
	};

	if ( newServers.length < 1 ) {
		return (
			<tr>
				<td
					css={{
						textAlign: "center",
						padding: "20px"
					}}
					colSpan={3}
				>
					<Spinner />
				</td>
			</tr>
		);
	}

	return (
		newServers.map( ( server, index ) => (
			<tr key={`wmcserver-${index}`}>
				<td>
					<input
						type="text"
						name={`wmc_servers[${index}][name]`}
						value={server.name}
						pattern="[A-Za-z0-9 \-_]{3,}"
						title={__('Special characters (non-alphanumeric) are not allowed, though underscores, hyphens and spaces are. 3 characters minimum.')}
						onChange={ (evt) => {
							return updateServerName( evt.target.value, index );
						} }
					/>
				</td>
				<td>
					<input
						type="text"
						name={`wmc_servers[${index}][key]`}
						value={server.key}
						pattern="[A-Za-z0-9\-_]{6,}"
						title={__('Special characters (non-alphanumeric) are not allowed, 6 characters minimum. Underscores and hyphens are allowed.')}
						onChange={ (evt) => {
							return updateServerKey( evt.target.value, index );
						} }
					/>
				</td>
				<td>
					<Button
						isDestructive
						className={`server-${index}`}
						onClick={ () => {
							return deleteServer( server )
						} }
					>
					{__('Delete', 'woominecraft')}
					</Button>
				</td>
			</tr>
		) )
	);
}

export default ServerSettingsRows;
