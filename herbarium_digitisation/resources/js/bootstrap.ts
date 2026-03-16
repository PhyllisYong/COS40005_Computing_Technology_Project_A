import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<any>;
    }
}

window.Pusher = Pusher;

const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbAppKey) {
    window.Echo = new Echo<any>({
        broadcaster: 'reverb',
        key: reverbAppKey,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        // CSRF token is automatically included for private channel authentication
        authEndpoint: '/broadcasting/auth',
    });
} else {
    console.warn('Realtime disabled: set VITE_REVERB_APP_KEY to enable Echo/Reverb.');
}
