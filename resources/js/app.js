import './bootstrap';

import Alpine from 'alpinejs';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

window.Alpine = Alpine;

Alpine.start();

createInertiaApp({
	title: (title) => (title ? `${title} - SELG Ballot Scanner` : 'SELG Ballot Scanner'),
	resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
	setup({ el, App, props, plugin }) {
		createApp({ render: () => h(App, props) })
			.use(plugin)
			.component('Link', Link)
			.component('Head', Head)
			.mount(el);
	},
});
