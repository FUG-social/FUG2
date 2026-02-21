<main id="view-logs" class="view">
    <h2>System Logs</h2>
    <select id="log-type-select">
        <option value="all">All</option>
        <option value="database">Database</option>
        <option value="error">Error</option>
        <option value="chat">Chat</option>
        <option value="realtime">Realtime</option>
    </select>
    <button onclick="Logs.load()">Refresh</button>
    <div id="log-content" style="font-family:monospace; font-size:12px; height:350px; overflow-y:auto; border:1px solid #000; padding:10px; margin-top:10px; background:#f9f9f9;"></div>
</main>
