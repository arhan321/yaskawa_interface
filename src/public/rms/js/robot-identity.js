'use strict';

document.querySelectorAll('[data-rms-home-form]').forEach(form => {
    const axes = ['S', 'L', 'U', 'R', 'B', 'T'];
    const button = form.querySelector('[data-rms-capture-home]');
    const status = form.querySelector('[data-rms-capture-status]');
    const sampleId = form.querySelector('[data-rms-captured-sample]');
    const confirmation = form.querySelector('[name="home_verified"]');
    const say = (text, state) => { status.textContent = text; status.dataset.state = state; };
    form.querySelectorAll('[data-rms-home-axis]').forEach(input => input.addEventListener('input', () => {
        sampleId.value = '';
        confirmation.checked = false;
        say('Input manual. Periksa kembali acuan pada pendant sebelum menyimpan.', 'manual');
    }));
    button.addEventListener('click', async () => {
        button.disabled = true;
        sampleId.value = '';
        confirmation.checked = false;
        say('Mengambil posisi controller…', 'pending');
        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 8000);
        try {
            const url = new URL(form.dataset.snapshotUrl, location.origin);
            const response = await fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store', signal: abort.signal});
            if (!response.ok) throw new Error('Posisi belum tersedia. Periksa koneksi dan aktifkan RMS Live.');
            const data = await response.json();
            const robot = data.robots?.find(r => String(r.id) === url.searchParams.get('robot_id'));
            const age = Date.parse(data.server_time) - Date.parse(robot?.last_sampled_at);
            const receiveAge = Date.parse(data.server_time) - Date.parse(robot?.last_received_at);
            if (!robot || robot.is_demo || robot.connectivity !== 'connected' || robot.position_ok !== true || !robot.sample_id
                || !Number.isFinite(age) || age < -5000 || age > 60000
                || !Number.isFinite(receiveAge) || receiveAge < -5000 || receiveAge > (data.stale_seconds ?? 30) * 1000
                || !axes.every(axis => Number.isInteger(robot.axes?.[axis]?.pulse)
                    && robot.axes[axis].pulse >= -2147483648 && robot.axes[axis].pulse <= 2147483647)) {
                throw new Error('Posisi enam axis belum segar/lengkap. Ambil ulang atau isi acuan pendant secara manual.');
            }
            axes.forEach(axis => { form.querySelector(`[data-rms-home-axis="${axis}"]`).value = robot.axes[axis].pulse; });
            sampleId.value = robot.sample_id;
            say(`Posisi diambil: ${robot.last_sampled_wib ?? robot.last_sampled_at}. Pastikan ini titik home yang benar, lalu konfirmasi.`, 'ok');
        } catch (error) {
            say(error.name === 'AbortError' ? 'Permintaan melewati batas waktu. Silakan ambil ulang.' : error.message, 'error');
        } finally {
            clearTimeout(timeout);
            button.disabled = false;
        }
    });
});
