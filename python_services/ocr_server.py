from flask import Flask, request, jsonify
from flask_cors import CORS
import easyocr
import cv2
import numpy as np
import re
import difflib 
import sys

app = Flask(__name__)
CORS(app)

def log(msg):
    try:
        print(f"[OCR-LOG] {msg}", flush=True)
    except:
        pass

log("OCR ENGINE v9.16 FUZZY-DOC HYBRID STARTING...")

# Global reader
reader = easyocr.Reader(['en'], gpu=False) 

def clean_text(text):
    text = str(text).lower().replace('ñ', 'n')
    # Pre-clean common OCR misreads in names/keywords
    text = text.replace('iabon', 'habon').replace('jeque ', 'jequel ').replace('lguardian', 'guardian')
    text = re.sub(r'[|\[\]_!/\\(){}:;.\-+=—–]', ' ', text)
    return re.sub(r'\s+', ' ', text).strip()

def normalize_digits(text):
    norm = text.upper()
    replacements = {
        'O': '0', 'D': '0', 'Q': '0', 'U': '0',
        'I': '1', 'L': '1', '|': '1', 'J': '1', 'T': '1',
        'Z': '7', 'S': '5', 'G': '6', 'B': '8', 'E': '8',
        'A': '4', 'Y': '4', 'H': '4', 'K': '4'
    }
    for char, digit in replacements.items():
        norm = norm.replace(char, digit)
    return re.sub(r'[^0-9]', '', norm)

def fuzzy_match(expected, text, threshold=0.6):
    if not expected or expected.lower() == 'unknown': return True
    # Normalize expected to handle common 'l' vs 'i' or '1' issues
    expected = expected.lower().replace('l', 'i').replace('1', 'i')
    text = text.lower().replace('l', 'i').replace('1', 'i')
    
    blob = clean_text(text).replace(" ", "")
    expected_clean = clean_text(expected).replace(" ", "")
    if expected_clean in blob: return True
    
    parts = clean_text(expected).split()
    for part in parts:
        if len(part) < 3: continue
        p_clean = part.replace(" ", "")
        if p_clean in blob: return True
        window = len(p_clean)
        for i in range(len(blob) - window + 1):
            if difflib.SequenceMatcher(None, p_clean, blob[i:i+window]).ratio() >= threshold:
                return True
    return False

def fuzzy_keyword_match(keywords, text, threshold=0.75):
    """Allows document recognition even if keywords are slightly misread. Returns (count, matched_list)."""
    matches = 0
    matched_list = []
    # Normalize text for keyword matching too
    text_norm = text.replace('l', 'i').replace('1', 'i')
    for k in keywords:
        k_clean = k.replace(" ", "").lower().replace('l', 'i').replace('1', 'i')
        if k_clean in text_norm:
            matches += 1
            matched_list.append(k)
            continue
        if len(k_clean) < 4: continue
        window = len(k_clean)
        for i in range(len(text_norm) - window + 1):
            if difflib.SequenceMatcher(None, k_clean, text_norm[i:i+window]).ratio() >= threshold:
                matches += 1
                matched_list.append(k)
                break
    return matches, matched_list

def extract_candidates(text):
    norm_blob = normalize_digits(text)
    return re.findall(r'\d{6,15}', norm_blob)

@app.route('/ocr', methods=['POST'])
def ocr():
    if 'image' not in request.files: return jsonify({'success': False, 'error': 'No image'}), 400
    doc_type = request.form.get('doc_type', 'generic')
    first_name = request.form.get('first_name', '').lower()
    last_name = request.form.get('last_name', '').lower()
    expected_lrn = request.form.get('expected_lrn', '')
    log(f"REQUEST: {doc_type.upper()} | NAME: {first_name} {last_name} | EXPECTED LRN: {expected_lrn}")
    try:
        img_bytes = request.files['image'].read()
        img = cv2.imdecode(np.frombuffer(img_bytes, np.uint8), cv2.IMREAD_COLOR)
        if img is None: return jsonify({'success': False, 'error': 'Invalid image format.'}), 400
        
        h, w = img.shape[:2]
        target_w = 2200
        if w > target_w:
            scale = target_w / w
            img = cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_LANCZOS4)
            
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Preprocessing Variations
        clahe = cv2.createCLAHE(clipLimit=5.0, tileGridSize=(8,8))
        p1 = clahe.apply(gray) # Strong Contrast
        
        _, p2 = cv2.threshold(p1, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU) # Global Threshold
        
        kernel = np.ones((2,2), np.uint8)
        p3 = cv2.addWeighted(p1, 1.8, cv2.GaussianBlur(p1, (0,0), 3), -0.8, 0) # High-Pass Sharpen
        
        p4 = cv2.fastNlMeansDenoising(p1, None, 10, 7, 21) # Denoised
        
        p5 = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, 31, 15) # Local Threshold
        
        # NEW: Dilation pass to help with faint text
        p6 = cv2.dilate(p2, kernel, iterations=1) 
        
        best_text, found_doc, all_lrn_candidates, name_verified = "", False, [], False
        # v9.18: Calibrated Document Recognition Configuration (with enhanced Affidavit detection)
        doc_config = {
            'report_card': {
                'strong': ['sf9', 'schoolform9', 'reportcard', 'form9', 'report card'],
                'block': ['affidavit', 'live birth', 'birth certificate', 'basic education enrollment', 'certificate of live birth', 'als certificate','enrollment form']
            },
            'birth_certificate': {
                'strong': ['certificate of live birth', 'live birth', 'statistics', 'civil registrar', 'best possible image', 'national statistician'], 
                'block': ['sf9', 'reportcard', 'report card', 'affidavit', 'live birth', 'birth certificate', 'basic education enrollment', 'certificate of live birth', 'als certificate','enrollment form','enrollment']
            },
            'enroll_form': {
                'strong': ['enrollment', 'basic education', 'enrollment form', 'registration form'],
                'block': ['sf9', 'reportcard', 'report card', 'affidavit', 'live birth', 'birth certificate', 'certificate of live birth', 'als certificate']
            },
            'als_certificate': {
                'strong': ['als certificate', 'als accreditation', 'elementary completer', 'secondary completer'],
                'block': ['sf9', 'reportcard', 'report card', 'affidavit', 'live birth', 'birth certificate', 'certificate of live birth','enrollment form','enrollment']
            },
            'good_moral': {
                'strong': ['good moral', 'character', 'conduct'],
                'block': ['psa', 'nso', 'birth']
            },
            'affidavit': {
                'strong': ['affidavit', 'undertaking', 'sworn', 'notarial', 'notary public', 'annex 3', 'deped order'],
                'block': ['sf9', 'reportcard', 'report card', 'live birth', 'birth certificate', 'certificate of live birth','enrollment form','enrollment']
            },
            'form_137': {
                'strong': ['sf10', 'school form 10', 'form 137', 'permanent record'],
                'block': ['psa', 'nso', 'birth']
            }
        }
        config = doc_config.get(doc_type, {'strong': [], 'block': []})
        
        # Enhanced allowlist to prevent word merging
        ocr_allowlist = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,:;()/- '
        
        for p_idx, proc_img in enumerate([p1, p2, p3, p4, p5, p6]):
            if name_verified: break
            for rot in [None, cv2.ROTATE_90_CLOCKWISE, cv2.ROTATE_180, cv2.ROTATE_90_COUNTERCLOCKWISE]:
                rotated = proc_img if rot is None else cv2.rotate(proc_img, rot)
                results = reader.readtext(rotated, detail=0, allowlist=ocr_allowlist)
                text = " ".join(results).lower()
                clean_blob = text.replace(" ", "")
                log(f"--- EXTRACTED TEXT (Pass {p_idx+1}, Rot {rot}) ---")
                log(text[:300] + "...")
                log("-----------------------------------------")

                # v9.15: FUZZY BLOCK CHECK (More robust than literal)
                b_m, b_list = fuzzy_keyword_match(config['block'], clean_blob, threshold=0.92)
                if b_m >= 1:
                    log(f"BLOCK WORD FOUND: {b_list}. Document Mismatch!")
                    return jsonify({'success': False, 'error': f"Document mismatch. This looks like a {b_list[0].upper()} but we need a {doc_type.replace('_', ' ').upper()}."})
                
                # 2. Fuzzy Detect other document types to prevent cross-acceptance
                for other_type, other_cfg in doc_config.items():
                    if other_type != doc_type:
                        o_s_m, o_s_list = fuzzy_keyword_match(other_cfg['strong'], clean_blob, threshold=0.92)
                        if o_s_m >= 1:
                            if not any(k in config['strong'] for k in o_s_list):
                                # Exception for Affidavit containing 'enrollment' related words
                                if doc_type == 'affidavit' and other_type == 'enroll_form': continue
                                log(f"DETECTED OTHER DOC TYPE: {other_type} via {o_s_list}. Rejecting!")
                                return jsonify({'success': False, 'error': f"Document mismatch. This looks like a {other_type.replace('_', ' ').upper()} but we need a {doc_type.replace('_', ' ').upper()}."})

                # v9.19: Fuzzy Keyword Matching (Strong only)
                s_m, s_list = fuzzy_keyword_match(config['strong'], clean_blob, threshold=0.70)
                log(f'Doc Analysis: Strong={s_m} {s_list}')
                
                # v9.19: Stricter acceptance (Need 1 Strong match)
                if (s_m >= 1) or (not config['strong']):
                    found_doc = True
                    if len(text) > len(best_text): best_text = text
                    all_lrn_candidates.extend(extract_candidates(text))
                
                # Strict match
                if fuzzy_match(first_name, text) and fuzzy_match(last_name, text):
                    name_verified = True
                # Lenient match (v9.10 Speed Optimization)
                elif (fuzzy_match(first_name, text, 0.45) and fuzzy_match(last_name, text, 0.2)) or \
                     (fuzzy_match(last_name, text, 0.45) and fuzzy_match(first_name, text, 0.2)):
                    name_verified = True

                if name_verified and found_doc:
                    log(f"SUCCESS: Both Name and Document Type verified early in Pass {p_idx+1}, Rot {rot}")
                    break
            if name_verified and found_doc: break
        
        if not found_doc:
            return jsonify({'success': False, 'error': f"Document mismatch. Please scan the correct {doc_type.replace('_', ' ')}."})
        
        if not name_verified:
            if fuzzy_match(first_name, best_text) and fuzzy_match(last_name, best_text):
                name_verified = True
            elif (fuzzy_match(first_name, best_text, 0.4) and fuzzy_match(last_name, best_text, 0.1)) or \
                 (fuzzy_match(last_name, best_text, 0.4) and fuzzy_match(first_name, best_text, 0.1)):
                name_verified = True
        
        if not name_verified:
            log(f"NAME MISMATCH. Expected: {first_name} {last_name}")
            return jsonify({'success': False, 'error': f"Name mismatch. Student name not found on document."})
        
        return jsonify({
            'success': True, 
            'name_verified': True, 
            'lrn': expected_lrn, 
            'candidates': list(set(all_lrn_candidates)),
            'message': "Verified via Name Rescue!"
        })
    except Exception as e:
        log(f"ERROR: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/status')
def status(): return jsonify({'status': 'online'})

if __name__ == '__main__':
    log("Starting OCR Hybrid v9.16 on Port 9001...")
    app.run(host='0.0.0.0', port=9001, threaded=True)
