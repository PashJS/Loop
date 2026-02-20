const WebSocket = require('ws');
const http = require('http');

const PORT = process.env.PORT || 8080;
const HOST = '0.0.0.0';

const server = http.createServer((req, res) => {
    res.writeHead(200);
    res.end('WebSocket server is running');
});

const wss = new WebSocket.Server({ server });

wss.on('connection', (ws, req) => {
    const ip = req.socket.remoteAddress;
    console.log(`[${new Date().toISOString()}] New Connection from ${ip}`);

    ws.on('message', (message) => {
        const data = JSON.parse(message);
        console.log(`[${new Date().toISOString()}] Received:`, data);

        // Broadcast to all clients
        wss.clients.forEach((client) => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(JSON.stringify(data));
            }
        });
    });

    ws.on('close', () => {
        console.log(`[${new Date().toISOString()}] Connection closed for ${ip}`);
    });
});

server.listen(PORT, HOST, () => {
    console.log(`[${new Date().toISOString()}] WebSocket Server started on port ${PORT}`);
    console.log(`[${new Date().toISOString()}] Binding to: ${HOST}`);
});

server.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
        console.error(`[${new Date().toISOString()}] FATAL: Port ${PORT} is already in use.`);
        process.exit(1);
    } else {
        console.error(`[${new Date().toISOString()}] Server Error:`, err);
    }
});
