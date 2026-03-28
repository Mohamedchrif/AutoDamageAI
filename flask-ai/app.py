import io
import base64
import logging
import traceback

import cv2
import numpy as np
from PIL import Image
from flask import Flask, request, jsonify
from flask_cors import CORS
from ultralytics import YOLO

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)

yolo_model = YOLO("../models/car_damage_model.pt")

class_labels = [
    'Bodypanel-Dent',
    'Front-Windscreen-Damage',
    'Headlight-Damage',
    'Rear-windscreen-Damage',
    'RunningBoard-Dent',
    'Sidemirror-Damage',
    'Signlight-Damage',
    'Taillight-Damage',
    'bonnet-dent',
    'boot-dent',
    'doorouter-dent',
    'fender-dent',
    'front-bumper-dent',
    'pillar-dent',
    'quaterpanel-dent',
    'rear-bumper-dent',
    'roof-dent'
]

COST_MAP = {
    'Bodypanel-Dent':          (150,  500),
    'Front-Windscreen-Damage': (200,  600),
    'Headlight-Damage':        (100,  400),
    'Rear-windscreen-Damage':  (150,  500),
    'RunningBoard-Dent':       (100,  300),
    'Sidemirror-Damage':       (80,   250),
    'Signlight-Damage':        (50,   150),
    'Taillight-Damage':        (80,   250),
    'bonnet-dent':             (200,  800),
    'boot-dent':               (150,  600),
    'doorouter-dent':          (200,  700),
    'fender-dent':             (150,  500),
    'front-bumper-dent':       (200,  600),
    'pillar-dent':             (300, 1200),
    'quaterpanel-dent':        (200,  700),
    'rear-bumper-dent':        (150,  500),
    'roof-dent':               (300, 1000),
}

# Severity colour map for annotations (BGR)
SEVERITY_COLORS = {
    "major":    (0,   0,   255),   # Red
    "moderate": (0,   165, 255),   # Orange
    "minor":    (0,   255, 0),     # Green
}


def analyze_damage_cv2(image_array: np.ndarray) -> list:
    """Run YOLO inference and return structured detections."""
    if image_array is None or image_array.size == 0:
        return []

    results = yolo_model(image_array, verbose=False)
    detected_issues = []

    for r in results:
        for box in r.boxes:
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            conf = float(box.conf[0].item())
            cls  = int(box.cls[0])

            if conf > 0.3:
                label    = class_labels[cls]
                severity = (
                    "major"    if conf >= 0.8 else
                    "moderate" if conf >= 0.6 else
                    "minor"
                )
                cost_min, cost_max = COST_MAP.get(label, (100, 400))
                detected_issues.append({
                    "class":      label,
                    "confidence": round(conf, 3),
                    "bbox":       [x1, y1, x2, y2],
                    "severity":   severity,
                    "cost_min":   cost_min,
                    "cost_max":   cost_max,
                })

    return detected_issues


def draw_annotations(image_array: np.ndarray, detected_issues: list) -> np.ndarray:
    """Draw bounding boxes + labels on a copy of the image."""
    img = image_array.copy()

    for issue in detected_issues:
        x1, y1, x2, y2 = issue["bbox"]
        color = SEVERITY_COLORS.get(issue["severity"], (0, 255, 255))
        label = f'{issue["class"]} ({issue["confidence"]:.2f})'

        # Box
        cv2.rectangle(img, (x1, y1), (x2, y2), color, 2)

        # Label background
        (lw, lh), baseline = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.55, 2)
        cv2.rectangle(img, (x1, y1 - lh - 10), (x1 + lw + 4, y1), color, -1)

        # Label text
        cv2.putText(
            img, label,
            (x1 + 2, y1 - 5),
            cv2.FONT_HERSHEY_SIMPLEX, 0.55,
            (255, 255, 255), 2,
        )

    return img


def image_to_base64(image_array: np.ndarray) -> str:
    """Convert a BGR numpy array to a JPEG base64 data URI."""
    img_rgb = cv2.cvtColor(image_array, cv2.COLOR_BGR2RGB)
    pil_img = Image.fromarray(img_rgb)
    buffer  = io.BytesIO()
    pil_img.save(buffer, format='JPEG', quality=85)
    buffer.seek(0)
    b64 = base64.b64encode(buffer.read()).decode('utf-8')
    return f'data:image/jpeg;base64,{b64}'


@app.route('/predict', methods=['POST'])
def predict():
    if 'image' not in request.files:
        return jsonify({"success": False, "error": "No image file provided."}), 400

    file = request.files['image']
    if file.filename == '':
        return jsonify({"success": False, "error": "Empty filename."}), 400

    try:
        # ── Decode image ─────────────────────────────────────
        file_bytes  = np.frombuffer(file.read(), dtype=np.uint8)
        image_array = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)
        if image_array is None:
            return jsonify({"success": False, "error": "Could not decode image."}), 422

        h, w = image_array.shape[:2]

        # ── Run AI ───────────────────────────────────────────
        detected_issues = analyze_damage_cv2(image_array)

        # The new car_damage_model.pt doesn't output 'Undamaged'. Empty outputs = undamaged.
        damage_issues = detected_issues
        is_undamaged  = len(damage_issues) == 0

        # ── Annotate image ───────────────────────────────────
        annotated     = draw_annotations(image_array, damage_issues)
        annotated_b64 = image_to_base64(annotated)

        # ── Aggregate costs ──────────────────────────────────
        cost_min = sum(d["cost_min"] for d in damage_issues)
        cost_max = sum(d["cost_max"] for d in damage_issues)

        # ── Build response (matches all DB columns) ───────────
        return jsonify({
            "success":             True,
            "is_undamaged":        is_undamaged,
            "total_detections":    len(damage_issues),
            "detected_issues":     damage_issues,
            "annotated_image":     annotated_b64,          # full data URI base64
            "cost_min":            cost_min,
            "cost_max":            cost_max,
            "original_dimensions": {"width": w, "height": h},
        })

    except Exception as e:
        logger.error(f"Inference error: {str(e)}\n{traceback.format_exc()}")
        return jsonify({"success": False, "error": str(e)}), 500


# Alias route
@app.route('/analyze', methods=['POST'])
def analyze():
    return predict()


@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "ok"})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)