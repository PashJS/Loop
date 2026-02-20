#!/bin/bash
# Kill existing node processes forcefully
pkill -9 -f node
sleep 5

# Check if port 8080 is free
if ss -tulnp | grep -q ":8080 "; then
    echo "ERROR: Port 8080 is still in use!" >> "/var/www/html/FloxWatch/ws_service.log"
    echo "Failing process:" >> "/var/www/html/FloxWatch/ws_service.log"
    ss -tulnp | grep ":8080 " >> "/var/www/html/FloxWatch/ws_service.log"
    exit 1
fi

# Log directory
LOG_FILE="/var/www/html/FloxWatch/ws_service.log"
SERVER_JS="/var/www/html/FloxWatch/websocket/server.js"

echo "----------------------------------------" >> $LOG_FILE
echo "Starting WebSocket Server at $(date)" >> $LOG_FILE
echo "Node Version: $(node -v)" >> $LOG_FILE
echo "Server Script: $SERVER_JS" >> $LOG_FILE

# Check if file exists
if [ -f "$SERVER_JS" ]; then
    echo "File exists." >> $LOG_FILE
else
    echo "ERROR: File $SERVER_JS not found!" >> $LOG_FILE
    exit 1
fi

# Start server
nohup node $SERVER_JS >> $LOG_FILE 2>&1 &

PID=$!
echo "Server started with PID: $PID"
echo "Server started with PID: $PID" >> $LOG_FILE
