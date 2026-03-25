from flask import Flask, request, jsonify
from flask_cors import CORS
import cv2
import numpy as np
import os
import sys
import traceback
import logging
import base64
import io
from PIL import Image
from ultralytics import YOLO

# =============================================================================
# CONFIGURATION & LOGGING
# =============================================================================

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger(__name__)

app = Flask(__name__)
# Enable CORS for API
CORS(app)

# =============================================================================
# MODEL LOADING (Singleton Pattern)
# =============================================================================

# Define path to the model, matching the original location
MODEL_PATH = os.path.join(os.path.dirname(os.path.dirname(__file__)), "models", "car_damage_model.pt")

if not os.path.exists(MODEL_PATH):
    logger.error(f"❌ YOLO model not found at {MODEL_PATH}")
    # In a real setup, copy the model to the correct location or pass via env var
    logger.warning("Please ensure the model exists.")

try:
    if os.path.exists(MODEL_PATH):
        logger.info(f"🔄 Loading YOLO model from {MODEL_PATH}...")
        yolo_model = YOLO(MODEL_PATH)
        logger.info("✅ Model loaded successfully.")
    else:
        yolo_model = None
except Exception as e:
    logger.error(f"❌ Failed to load model: {str(e)}")
    yolo_model = None

# =============================================================================
# CLASS LABELS
# =============================================================================

class_labels = [
    'Undamaged', 'Front-Windscreen-Damage', 'Headlight-Damage', 'Rear-windscreen-Damage',
    'RunningBoard-Dent', 'Sidemirror-Damage', 'Signlight-Damage', 'Taillight-Damage',
    'bonnet-dent', 'boot-dent', 'doorouter-dent', 'fender-dent', 'front-bumper-dent',
    'pillar-dent', 'quaterpanel-dent', 'rear-bumper-dent', 'roof-dent'
]

# =============================================================================
# UTILITIES
# =============================================================================

def estimate_repair_cost(class_name, severity):
    cost_table = {
        'Front-Windscreen-Damage':  {'minor': (150, 300),  'moderate': (300, 600),   'major': (600, 1200)},
        'Rear-windscreen-Damage':   {'minor': (150, 300),  'moderate': (300, 600),   'major': (600, 1100)},
        'Headlight-Damage':         {'minor': (80,  200),  'moderate': (200, 450),   'major': (450, 900)},
        'Taillight-Damage':         {'minor': (70,  180),  'moderate': (180, 400),   'major': (400, 800)},
        'Sidemirror-Damage':        {'minor': (80,  200),  'moderate': (200, 500),   'major': (500, 900)},
        'Signlight-Damage':         {'minor': (50,  150),  'moderate': (150, 300),   'major': (300, 600)},
        'RunningBoard-Dent':        {'minor': (100, 250),  'moderate': (250, 500),   'major': (500, 1000)},
        'bonnet-dent':              {'minor': (200, 400),  'moderate': (400, 800),   'major': (800, 1800)},
        'boot-dent':                {'minor': (150, 350),  'moderate': (350, 700),   'major': (700, 1500)},
        'doorouter-dent':           {'minor': (150, 300),  'moderate': (300, 600),   'major': (600, 1400)},
        'fender-dent':              {'minor': (150, 350),  'moderate': (350, 700),   'major': (700, 1500)},
        'front-bumper-dent':        {'minor': (200, 400),  'moderate': (400, 900),   'major': (900, 2000)},
        'rear-bumper-dent':         {'minor': (200, 400),  'moderate': (400, 800),   'major': (800, 1800)},
        'pillar-dent':              {'minor': (200, 500),  'moderate': (500, 1200),  'major': (1200, 3000)},
        'quaterpanel-dent':         {'minor': (200, 450),  'moderate': (450, 1000),  'major': (1000, 2500)},
        'roof-dent':                {'minor': (200, 500),  'moderate': (500, 1200),  'major': (1200, 3000)},
        'Undamaged':                {'minor': (0, 0),      'moderate': (0, 0),       'major': (0, 0)},
    }
    defaults = {'minor': (100, 300), 'moderate': (300, 700), 'major': (700, 1500)}
    entry = cost_table.get(class_name, defaults)
    return entry.get(severity, (100, 500))

def analyze_damage_cv2(image_array):
    if image_array is None or image_array.size == 0:
        logger.warning("⚠️ Empty or invalid image array received.")
        return []

    try:
        if not yolo_model:
            return []
            
        results = yolo_model(image_array, verbose=False)
        detected_issues = []

        for r in results:
            for box in r.boxes:
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                conf = float(box.conf[0].item())
                cls = int(box.cls[0])

                if conf > 0.3:
                    if conf >= 0.8:
                        severity = "major"
                    elif conf >= 0.6:
                        severity = "moderate"
                    else:
                        severity = "minor"

                    detected_issues.append({
                        "class": class_labels[cls],
                        "confidence": round(conf, 3),
                        "bbox": [x1, y1, x2, y2],
                        "severity": severity
                    })
        
        logger.info(f"🔍 Detected {len(detected_issues)} issue(s).")
        return detected_issues

    except Exception as e:
        logger.error(f"❌ Inference error: {str(e)}\n{traceback.format_exc()}")
        raise

def draw_annotations(image_array, detected_issues):
    img = image_array.copy()
    severity_colors = {
        "major": (0, 0, 255),
        "moderate": (0, 165, 255),
        "minor": (0, 255, 0)
    }
    
    for issue in detected_issues:
        x1, y1, x2, y2 = issue["bbox"]
        severity = issue["severity"]
        label = f'{issue["class"]} ({issue["confidence"]:.2f})'
        color = severity_colors.get(severity, (0, 255, 255))
        
        cv2.rectangle(img, (x1, y1), (x2, y2), color, 2)
        (label_w, label_h), baseline = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 2)
        cv2.rectangle(img, (x1, y1 - label_h - 10), (x1 + label_w, y1), color, -1)
        cv2.putText(img, label, (x1, y1 - 5), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
    
    return img

def image_to_base64(image_array):
    try:
        img_rgb = cv2.cvtColor(image_array, cv2.COLOR_BGR2RGB)
        pil_img = Image.fromarray(img_rgb)
        buffer = io.BytesIO()
        pil_img.save(buffer, format='JPEG', quality=85, optimize=True)
        buffer.seek(0)
        img_base64 = base64.b64encode(buffer.read()).decode('utf-8')
        return f'data:image/jpeg;base64,{img_base64}'
    except Exception as e:
        logger.error(f"❌ Failed to encode image to base64: {str(e)}")
        raise

# =============================================================================
# API ROUTES
# =============================================================================

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({"status": "healthy", "model_loaded": yolo_model is not None})

@app.route('/predict', methods=['POST'])
def predict():
    """
    Accepts image upload via multipart/form-data, runs YOLO inference, 
    and returns JSON with the detections and annotated base64 image.
    """
    logger.info("📥 Received POST request to /predict")

    try:
        if 'image' not in request.files:
            return jsonify({"error": "No image file provided."}), 400

        file = request.files['image']
        if file.filename == '':
            return jsonify({"error": "Empty filename."}), 400

        file_bytes = np.frombuffer(file.read(), np.uint8)
        img = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)
        if img is None:
            return jsonify({"error": "Failed to decode image."}), 400

        issues = analyze_damage_cv2(img)
        annotated_img = draw_annotations(img, issues)
        annotated_img_base64 = image_to_base64(annotated_img)

        enriched_issues = []
        total_min = 0
        total_max = 0
        for issue in issues:
            cost = estimate_repair_cost(issue['class'], issue['severity'])
            enriched_issues.append({**issue, 'cost_min': cost[0], 'cost_max': cost[1]})
            total_min += cost[0]
            total_max += cost[1]

        analysis_result = {
            "success": True,
            "detected_issues": enriched_issues,
            "total_detections": len(enriched_issues),
            "is_undamaged": len(enriched_issues) == 0,
            "annotated_image": annotated_img_base64,
            "cost_min": total_min,
            "cost_max": total_max,
            "original_dimensions": {"width": img.shape[1], "height": img.shape[0]},
        }

        return jsonify(analysis_result)

    except Exception as e:
        logger.error(f"❌ Unhandled exception in /predict: {str(e)}\n{traceback.format_exc()}")
        return jsonify({
            "error": "Internal server error",
            "details": str(e)
        }), 500

if __name__ == '__main__':
    logger.info("🚀 Starting Flask AI API Server...")
    app.run(host='0.0.0.0', port=5000, debug=True, use_reloader=False)
