import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher globally available for Echo
window.Pusher = Pusher;

// Enable verbose logging in development
const isDevelopment = import.meta.env.DEV;
if (isDevelopment) {
    Pusher.logToConsole = true;
    console.log('🔌 Reverb/Pusher: Verbose logging enabled');
}

// Initialize Laravel Echo with Reverb
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    encrypted: import.meta.env.VITE_REVERB_SCHEME === 'https',
    wsPath: import.meta.env.VITE_REVERB_SERVER_PATH,
    wssPath: import.meta.env.VITE_REVERB_SERVER_PATH,
    enabledTransports: ['ws', 'wss'],
});

if (isDevelopment) {
    console.log('🚀 Echo initialized with config:', {
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT,
        scheme: import.meta.env.VITE_REVERB_SCHEME,
        path: import.meta.env.VITE_REVERB_SERVER_PATH,
    });
}

export default echo;
