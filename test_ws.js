const WebSocket = require('ws');
const ws = new WebSocket('ws://localhost:8080');

ws.on('open', () => {
    console.log('CONNECTED TO SERVER');
    ws.send(JSON.stringify({
        type: 'JOIN_STREAM',
        streamId: 'test_room'
    }));
});

ws.on('message', (data) => {
    console.log('RECEIVED:', data.toString());
    process.exit(0);
});

ws.on('error', (err) => {
    console.error('ERROR:', err.message);
    process.exit(1);
});

setTimeout(() => {
    console.log('TIMEOUT');
    process.exit(1);
}, 5000);
