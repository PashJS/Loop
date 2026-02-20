const WebSocket = require('ws');
const http = require('http');

const PORT = process.env.PORT || 8080;
const HOST = '0.0.0.0';

const server = http.createServer((req, res) => {
    res.writeHead(200);
    res.end('FloxWatch WebSocket Engine is Running');
});

const wss = new WebSocket.Server({ server });

// Room management: roomName -> Set of WebSocket clients
const rooms = new Map();

wss.on('connection', (ws, req) => {
    const ip = req.socket.remoteAddress;
    ws.id = Math.random().toString(36).substring(7).toUpperCase();
    console.log(`[${new Date().toISOString()}] New Connection [${ws.id}] from ${ip}`);

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);
            console.log(`[${new Date().toISOString()}] [${ws.id}] Received type: ${data.type}`);

            if (data.type === 'JOIN_STREAM') {
                const roomName = data.streamId;
                if (!rooms.has(roomName)) {
                    rooms.set(roomName, new Set());
                }
                rooms.get(roomName).add(ws);
                console.log(`[STATE] Client ${ws.id} joined room: ${roomName}`);
                return;
            }

            if (data.type === 'LEAVE_STREAM') {
                const roomName = data.streamId;
                if (rooms.has(roomName)) {
                    rooms.get(roomName).delete(ws);
                    console.log(`[${ws.id}] Left room: ${roomName}`);
                }
                return;
            }

            if (data.type === 'HEARTBEAT') {
                return; // Silently handle heartbeat
            }

            // Route based on message type
            if (data.type === 'NEW_PRIVATE_MESSAGE' || data.type === 'PRIVATE_MESSAGE') {
                // Target recipient room
                const targetRoom = `user_${data.receiver_id}`;
                broadcastToRoom(targetRoom, data);

                // ALSO broadcast to SENDER so their other tabs sync
                const senderRoom = `user_${data.sender_id}`;
                if (senderRoom !== targetRoom) {
                    broadcastToRoom(senderRoom, data);
                }
            } else if (data.type === 'GROUP_MESSAGE') {
                // Target group room
                const targetRoom = `group_${data.group_id}`;
                broadcastToRoom(targetRoom, data);
            } else if (data.receiver_id) {
                // Generic targeted message (Typing, status, etc)
                broadcastToRoom(`user_${data.receiver_id}`, data);
            } else if (data.group_id) {
                broadcastToRoom(`group_${data.group_id}`, data);
            } else {
                // Fallback: Global broadcast (old behavior)
                broadcastGlobal(data);
            }

        } catch (e) {
            console.error(`[ERR] Message handling failed:`, e.message);
        }
    });

    ws.on('close', () => {
        console.log(`[${new Date().toISOString()}] Connection closed for [${ws.id}]`);
        // Cleanup rooms
        rooms.forEach((clients, roomName) => {
            clients.delete(ws);
            if (clients.size === 0) rooms.delete(roomName);
        });
    });

    ws.on('error', (err) => {
        console.error(`[WS-ERR] Client ${ws.id}:`, err.message);
    });
});

function broadcastToRoom(roomName, data) {
    const clients = rooms.get(roomName);
    if (clients) {
        let sentCount = 0;
        const payload = JSON.stringify(data);
        clients.forEach(client => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(payload);
                sentCount++;
            }
        });
        console.log(`[BROADCAST] Sent ${data.type} to room ${roomName} (Recipients: ${sentCount})`);
    } else {
        console.log(`[BROADCAST] Target room ${roomName} not found.`);
    }
}

function broadcastGlobal(data) {
    const payload = JSON.stringify(data);
    wss.clients.forEach(client => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(payload);
        }
    });
}

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

