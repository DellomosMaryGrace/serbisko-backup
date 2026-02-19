from flask import Flask, jsonify
from flask_cors import CORS
import serial, time

app = Flask(__name__)
CORS(app)

try:
    # ADJUST 'COM3' TO YOUR ARDUINO PORT
    arduino = serial.Serial('COM3', 9600, timeout=1)
    time.sleep(2)
except:
    arduino = None

@app.route('/api/strand/<name>', methods=['POST'])
def sort(name):
    if not arduino: return jsonify({'error': 'No Arduino'}), 500
    
    cmd = 'b1' if name == 'STEM' else 'b2' # Simplified mapping
    arduino.write((cmd + '\n').encode())
    return jsonify({'sent': cmd})

if __name__ == '__main__':
    app.run(port=8080)