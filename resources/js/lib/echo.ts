import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

let connection: Echo<'reverb'> | null = null;

export type RealtimeConfig = {
    key: string | null;
    host: string | null;
    port: number;
    scheme: string;
};

export function productEcho(
    config: RealtimeConfig | null,
): Echo<'reverb'> | null {
    if (!config?.key || !config.host) {
        return null;
    }

    if (!connection) {
        window.Pusher = Pusher;
        const secure = config.scheme === 'https';
        connection = new Echo<'reverb'>({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host,
            wsPort: config.port || 80,
            wssPort: config.port || 443,
            forceTLS: secure,
            enabledTransports: ['ws', 'wss'],
        });
    }

    return connection;
}
