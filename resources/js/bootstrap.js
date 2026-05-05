import axios from 'axios';
import "bootstrap-icons/font/bootstrap-icons.css";
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Optional: initialize Laravel Echo (Pusher) if environment variables and
// packages are available. This is non-fatal when missing so builds won't fail.
try {
	const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || import.meta.env.MIX_PUSHER_APP_KEY;
	const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || import.meta.env.MIX_PUSHER_APP_CLUSTER || 'mt1';
	const broadcaster = import.meta.env.VITE_PUSHER_BROADCAST_DRIVER || import.meta.env.MIX_PUSHER_BROADCAST_DRIVER || null;

	if (pusherKey && broadcaster === 'pusher') {
		// dynamic import to avoid hard dependency during build if not installed
		Promise.all([import('laravel-echo'), import('pusher-js')])
			.then(([{ default: Echo }, { default: Pusher }]) => {
				try {
					window.Pusher = Pusher;
					window.Echo = new Echo({
						broadcaster: 'pusher',
						key: pusherKey,
						cluster: pusherCluster,
						forceTLS: true,
						encrypted: true,
					});
					console.debug('Laravel Echo initialized');
				} catch (e) {
					console.debug('Failed to initialize Echo', e);
				}
			})
			.catch((e) => {
				// Packages not installed or failed to load — ignore silently.
				// Developer may choose to install `laravel-echo` and `pusher-js`.
			});
	}
} catch (e) {
	// ignore
}
