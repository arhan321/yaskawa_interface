'use strict';

/**
 * RMS Reverb connection indicator (transport-level only).
 * - Uses the exact Echo instance returned to rms-live.js; no duplicate WebSocket.
 * - Connected = WebSocket connected, NOT physical robot/collector healthy.
 * - Reconnecting/offline => persistent warning banner + topbar indicator.
 * - Never reloads the page and never changes application data.
 * - No external HTTP request, no secret on the browser.
 */
export function initRmsReverbStatus(Echo) {
    const root = document.querySelector('.rms-app');
    if (!root || root.dataset.rmsReverbMonitorEnabled !== '1') return null;

    const livePages = root.dataset.rmsLiveEnabled === '1';
    const toolbar = document.querySelector('.top-actions') || document.querySelector('.topbar');
    const heading = document.querySelector('.page-heading');
    if (!toolbar || !heading) return null;

    const indicator = document.createElement('span');
    indicator.id = 'rms-reverb-status';
    indicator.className = 'rms-live-indicator rms-reverb-indicator';
    indicator.setAttribute('role', 'status');
    indicator.setAttribute('aria-live', 'polite');
    indicator.setAttribute('title', 'Status koneksi WebSocket browser ke Laravel Reverb; bukan status controller robot.');
    toolbar.prepend(indicator);

    const banner = document.createElement('div');
    banner.id = 'rms-reverb-warning';
    banner.className = 'rms-reverb-warning';
    banner.setAttribute('role', 'alert');
    banner.hidden = true;
    const headingText = document.createElement('strong');
    const detailText = document.createElement('span');
    banner.append(headingText, detailText);
    heading.insertAdjacentElement('afterend', banner);

    const liveKey = root.dataset.rmsLiveKey;
    let current = 'connecting';
    let grace = null;

    function render(state, note = '') {
        if (state === current && indicator.textContent) return;
        current = state;
        const browserOffline = !navigator.onLine;
        const states = {
            connecting: ['REVERB CONNECTING', 'connecting'],
            connected: ['REVERB CONNECTED', 'connected'],
            reconnecting: ['REVERB RECONNECTING', 'fallback'],
            offline: [browserOffline ? 'NETWORK OFFLINE' : 'REVERB OFFLINE', 'error'],
            disabled: ['REVERB NOT CONFIGURED', 'error'],
        };
        const [label, color] = states[state] || states.offline;
        indicator.textContent = label;
        indicator.dataset.state = color;
        banner.hidden = !['reconnecting', 'offline', 'disabled'].includes(state);
        if (!banner.hidden) {
            headingText.textContent = browserOffline
                ? 'Koneksi internet/jaringan browser terputus. '
                : state === 'disabled'
                    ? 'Konfigurasi Reverb belum lengkap. '
                    : 'Koneksi Laravel Reverb tidak tersedia. ';
            detailText.textContent = livePages
                ? 'Dashboard menggunakan polling JSON sebagai cadangan; status robot tetap mengikuti timestamp telemetry, bukan status Reverb. '
                : 'Pembaruan langsung melalui WebSocket sedang tidak tersedia. ';
            if (note === 'not-configured') detailText.textContent += 'Periksa REVERB_APP_KEY dan konfigurasi Live RMS.';
            else detailText.textContent += 'Periksa service reverb_yaskawa dan jalur WebSocket /app/. Sistem akan mencoba tersambung kembali.';
        }
    }

    render('connecting');
    // Avoid alarming users for ordinary handshakes shorter than 7 seconds.
    grace = setTimeout(() => {
        if (current === 'connecting') render('offline');
    }, 7000);

    if (!liveKey || liveKey.startsWith('GENERATE_')) {
        clearTimeout(grace);
        render('disabled', 'not-configured');
        return null;
    }

    try {
        const secure = location.protocol === 'https:';
        const port = Number(location.port) || (secure ? 443 : 80);
        const echo = new Echo({
            broadcaster: 'reverb',
            key: liveKey,
            cluster: 'mt1',
            wsHost: location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: secure,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
        });
        const connection = echo.connector?.pusher?.connection;
        if (!connection) throw new Error('Reverb connection adapter not available');

        function update(state) {
            if (state === 'connected') {
                clearTimeout(grace);
                render('connected');
            } else if (state === 'connecting' || state === 'initialized') {
                // On initial load: allow normal handshake time.
                if (current === 'connected') render('reconnecting');
            } else if (state === 'disconnected' || state === 'unavailable' || state === 'failed') {
                clearTimeout(grace);
                render('offline');
            }
        }
        connection.bind('state_change', ({ current: state }) => update(state));
        connection.bind('connected', () => update('connected'));
        connection.bind('disconnected', () => update('disconnected'));
        connection.bind('unavailable', () => update('unavailable'));
        connection.bind('failed', () => update('failed'));
        connection.bind('error', () => {
            if (connection.state !== 'connected') render('offline');
        });
        update(connection.state);

        window.addEventListener('offline', () => render('offline'));
        window.addEventListener('online', () => {
            if (connection.state === 'connected') render('connected');
            else render('reconnecting');
        });

        return echo;
    } catch (_) {
        clearTimeout(grace);
        render('offline');
        return null;
    }
}
