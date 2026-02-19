from flask import Flask, request, jsonify
from flask_cors import CORS
import easyocr
import cv2
import numpy as np
import re

app = Flask(__name__)
CORS(app)

print("Loading OCR Model...")
reader = easyocr.Reader(['en'], gpu=False)
print("OCR Ready!")

def process_image(file):
    file_bytes = np.frombuffer(file.read(), np.uint8)
    return cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)

@app.route('/ocr', methods=['POST'])
def ocr():
    if 'image' not in request.files: return jsonify({'error': 'No image'}), 400
    
    doc_type = request.form.get('doc_type', 'generic')
    name = request.form.get('student_name', '').lower()
    
    # Run OCR
    image = process_image(request.files['image'])
    results = reader.readtext(image, detail=0)
    full_text = " ".join(results).lower()
    print(f"Text Found: {full_text[:50]}...")

    response = {'success': False}

    # 1. REPORT CARD LOGIC (Find LRN)
    if doc_type == 'report_card':
        match = re.search(r'\b\d{12}\b', full_text) # Find 12 digits
        if match:
            response['success'] = True
            response['lrn'] = match.group(0)
            response['message'] = "LRN Found"
        else:
            response['error'] = "No 12-digit LRN found."

    # 2. BIRTH CERT LOGIC (Find Name + 'Live Birth')
    elif doc_type == 'birth_cert':
        if "live birth" in full_text and all(part in full_text for part in name.split()):
            response['success'] = True
            response['message'] = "Birth Cert Verified"
        else:
            response['error'] = "Name or 'Live Birth' not found."

    # 3. GENERIC LOGIC
    else:
        response['success'] = True # Accept for now
        response['message'] = "Document Scanned"

    return jsonify(response)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=9001, debug=True)