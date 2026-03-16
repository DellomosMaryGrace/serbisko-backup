from flask import Flask, jsonify, request
from flask_cors import CORS
import serial, time
import serial.tools.list_ports
import threading

app = Flask(__name__)
CORS(app)

# ==========================================
# HARDWARE CONNECTION (With Auto-Reconnect)
# ==========================================
print("\n" + "="*50)
print("🤖 SERBISKO HARDWARE CONTROLLER ONLINE!")
print("="*50 + "\n")

def find_arduino_port(baud=9600, timeout=1):
    """Scan available serial ports and try to open one that responds like an Arduino."""
    for port in serial.tools.list_ports.comports():
        try:
            ser = serial.Serial(port.device, baud, timeout=timeout)
            time.sleep(2)
            if ser.is_open:
                print(f"🔍 Found serial device on {port.device} ({port.description})")
                return ser
        except Exception:
            continue
    return None

arduino = find_arduino_port()
if arduino:
    print("✅ Arduino Connected Successfully!")
else:
    print("❌ WARNING: Arduino NOT found. Running in simulation mode.")

def monitor_connection(interval=5):
    global arduino
    while True:
        if arduino and arduino.is_open:
            time.sleep(interval)
            continue
        ser = find_arduino_port()
        if ser:
            arduino = ser
            print("🔁 Arduino reconnected.")
        else:
            print("⏳ Arduino still not available, will retry...")
        time.sleep(interval)

threading.Thread(target=monitor_connection, daemon=True).start()

def send_command(cmd):
    global arduino
    if arduino and getattr(arduino, 'is_open', False):
        try:
            arduino.write((cmd + '\n').encode())
            print(f"📡 Sent to Arduino: [{cmd}]")
            return True
        except Exception as e:
            print(f"⚠️ Error sending to Arduino: {e}. Marking as disconnected.")
            arduino = None
            
    print(f"⚠️ SIMULATED Arduino Command: [{cmd}]")
    return False

# ==========================================
# 1. DOOR / SLOT CONTROLS (Fixes the /api/door 404 error)
# ==========================================
@app.route('/api/door', methods=['POST'])
@app.route('/api/door/<action>', methods=['POST'])
def control_door(action=None):
    # If the frontend just hits /api/door without an action in the URL, try to guess or read the JSON
    if not action:
        data = request.get_json(silent=True) or {}
        action = data.get('action', 'open') # Default to open if frontend doesn't specify
        
    if action.lower() == 'open':
        send_command('r')
        return jsonify({'status': 'success', 'command': 'r', 'message': 'Slot opened'})
    elif action.lower() == 'close':
        send_command('f')
        return jsonify({'status': 'success', 'command': 'f', 'message': 'Slot closed'})
        
    return jsonify({'error': 'Invalid slot action'}), 400

# ==========================================
# 2. CONVEYOR CONTROLS
# ==========================================
@app.route('/api/conveyor/start', methods=['POST'])
def start_conveyor():
    send_command('c0')
    
    def conveyor_timer():
        time.sleep(6)
        send_command('c1')
        print("🛑 Conveyor Auto-Stopped (c1 triggered).")
        
    threading.Thread(target=conveyor_timer).start()
    return jsonify({'status': 'success', 'command': 'c0', 'message': 'Conveyor started. Will auto-stop in 6s.'})

@app.route('/api/conveyor/w', methods=['POST'])
def trigger_w():
    send_command('w')
    return jsonify({'status': 'success', 'command': 'w', 'message': 'Conveyor W command triggered'})

# ==========================================
# 3. BIN ROUTING (Fixes the /api/strand/be 404 error)
# ==========================================
@app.route('/api/strand/<cluster>', methods=['POST'])
def select_bin(cluster):
    # Mapping based on your Tray ID screenshot (lowercase as requested)
    cluster_map = {
        'ASSH': 'b1',
        'BE': 'b2',
        'STEM': 'b3',
        'CSS': 'b4',
        'DIGITAL': 'b4',   
        'EIM': 'b5',
        'EPAS': 'b5',
        'HARDWARE': 'b5'   
    }
    
    # .upper() makes it case-insensitive so 'be' or 'BE' both work!
    cmd = cluster_map.get(cluster.upper())
    
    if cmd:
        send_command(cmd)
        return jsonify({'status': 'success', 'command': cmd, 'cluster': cluster.upper()})
    else:
        return jsonify({'error': f'Unknown cluster mapping for: {cluster}'}), 400

# ==========================================
# 4. IR SENSOR READER
# ==========================================
@app.route('/api/sensor/read', methods=['GET'])
def read_sensor():
    if not arduino:
        return jsonify({'status': 'success', 'sensor': 'I1', 'message': 'Simulated Success'})
        
    arduino.reset_input_buffer()
    time.sleep(0.5) 
    
    if arduino.in_waiting > 0:
        line = arduino.readline().decode('utf-8').strip()
        if 'I1' in line:
            return jsonify({'status': 'success', 'sensor': 'I1', 'message': 'Success! Go to next document.'})
        elif 'I0' in line:
            return jsonify({'status': 'error', 'sensor': 'I0', 'message': 'Error/Reject Bin. Please rescan.'})
            
    return jsonify({'status': 'waiting', 'message': 'No sensor data detected yet.'})

@app.route('/api/sensor/check-rejection', methods=['GET'])
def check_rejection():
    """Specifically checks for PAPER_REJECTED signal from Arduino."""
    if not arduino:
        return jsonify({'rejected': False})
        
    found = False
    # Read ALL available lines in the buffer to make sure we don't miss it
    while arduino.in_waiting > 0:
        try:
            line = arduino.readline().decode('utf-8').strip()
            print(f"📡 Serial Read (Rejection Check): {line}")
            if 'PAPER_REJECTED' in line:
                found = True
                # Don't break immediately, clear the rest of the buffer 
                # to avoid lingering signals for the next doc
        except Exception as e:
            print(f"⚠️ Error reading serial: {e}")
            break
            
    return jsonify({'rejected': found})

# ==========================================
# STATUS PING
# ==========================================
@app.route('/status', methods=['GET'])
def server_status():
    return jsonify({
        'status': 'online', 
        'arduino_connected': arduino is not None
    })

if __name__ == '__main__':
    print("Starting Arduino Hardware Controller on Port 51234...")
    app.run(host='0.0.0.0', port=51234, debug=True)