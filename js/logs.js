const Logs = {
    load: async () => {
        const type = document.getElementById('log-type-select').value;
        const res = await State.api('get_logs', { type });
        document.getElementById('log-content').innerHTML = res.data 
            ? (Array.isArray(res.data) ? res.data.join('<br>') : JSON.stringify(res.data))
            : 'No logs available.';
    }
};
document.getElementById('log-type-select')?.addEventListener('change', Logs.load);
