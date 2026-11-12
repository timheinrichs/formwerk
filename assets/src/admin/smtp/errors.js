import { __ } from '@wordpress/i18n';

const LOGGED_OUT = [
	'rest_cookie_invalid_nonce',
	'rest_forbidden',
	'rest_not_logged_in',
];
const OFFLINE = [ 'fetch_error', 'offline_error' ];

/**
 * Builds an error text that always says what happened first, then what to do.
 *
 * apiFetch brings its own message for every failure ("You are probably offline."),
 * which alone does not tell people whether their work was saved.
 *
 * @param {Object} error        Error thrown by apiFetch.
 * @param {string} whatHappened Sentence that names the failed action.
 * @return {string} Text for a notice.
 */
export function describeError( error, whatHappened ) {
	if ( LOGGED_OUT.includes( error?.code ) ) {
		return (
			whatHappened +
			' ' +
			__(
				'You have been logged out. Open a new tab, log in again, then come back and try again.',
				'formwerk'
			)
		);
	}
	if ( OFFLINE.includes( error?.code ) || ! error?.message ) {
		return (
			whatHappened +
			' ' +
			__( 'Check your internet connection and try again.', 'formwerk' )
		);
	}
	return whatHappened + ' ' + error.message;
}
