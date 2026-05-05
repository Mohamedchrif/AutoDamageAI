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

# ── Load Models ──────────────────────────────────────────────
parts_model  = YOLO("../models/car_parts_best.pt")
damage_model = YOLO("../models/car_damage_best.pt")

# ── First Model Classes (Car Parts) ──────────────────────────
parts_labels = [
    'back_bumper', 'back_door', 'back_glass', 'back_left_door',
    'back_left_light', 'back_light', 'back_right_door', 'front_bumper',
    'front_door', 'front_glass', 'front_left_light', 'front_light',
    'front_right_door', 'front_right_light', 'hood', 'left_mirror',
    'right_mirror', 'tailgate', 'trunk', 'unknown', 'wheel',
]

# ── Second Model Classes (Damages) ───────────────────────────
damage_labels = ['dent', 'glass_break', 'scratch', 'smash']

# ── Repair Cost in Algerian Dinar (DZD) ──────────────────────
COST_MAP = {
    'dent':        (8000,   40000),
    'glass_break': (10000,  60000),
    'scratch':     (3000,   20000),
    'smash':       (50000, 300000),
}

# ── Annotation Colors Based on Severity (BGR) ────────────────
SEVERITY_COLORS = {
    "major":    (0,   0,   255),
    "moderate": (0,   165, 255),
    "minor":    (0,   255, 0),
}


def extract_polygons(mask_array: np.ndarray) -> list:
    """Split a YOLO mask array containing NaNs into separate valid polygons."""
    if mask_array is None or len(mask_array) == 0:
        return None
    nan_idx = np.where(np.isnan(mask_array[:, 0]))[0]
    polys = []
    if len(nan_idx) > 0:
        last_idx = 0
        for idx in nan_idx:
            poly = mask_array[last_idx:idx]
            if len(poly) > 2:
                polys.append(poly.tolist())
            last_idx = idx + 1
        poly = mask_array[last_idx:]
        if len(poly) > 2:
            polys.append(poly.tolist())
    else:
        if len(mask_array) > 2:
            polys.append(mask_array.tolist())
    return polys if len(polys) > 0 else None


def tighten_polygons(polys: list, bbox: list, img_w: int, img_h: int,
                     erosion_px: int = 6) -> list:
    """Erode mask polygons and clip them to the damage bounding box so the
    overlay stays tight around the actual damage region."""
    if polys is None:
        return None

    x1, y1, x2, y2 = bbox
    # Build a binary mask from all polygons
    binary = np.zeros((img_h, img_w), dtype=np.uint8)
    for poly in polys:
        pts = np.array(poly, dtype=np.int32)
        cv2.fillPoly(binary, [pts], 255)

    # Clip to bounding box area only (keeps mask inside the detected region)
    clip = np.zeros_like(binary)
    clip[max(0, y1):min(img_h, y2), max(0, x1):min(img_w, x2)] = 255
    binary = cv2.bitwise_and(binary, clip)

    # Erode to shrink and tighten
    if erosion_px > 0:
        kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (erosion_px, erosion_px))
        binary = cv2.erode(binary, kernel, iterations=1)

    # Extract contours from the processed mask
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    result = []
    for cnt in contours:
        if cv2.contourArea(cnt) > 100:          # ignore tiny specks
            result.append(cnt.reshape(-1, 2).tolist())
    return result if result else None


def crop_part_image(image_array: np.ndarray, bbox: list, padding: int = 20):
    """Crop the damaged part from the image and return the cropped image and offsets."""
    h, w = image_array.shape[:2]
    x1, y1, x2, y2 = bbox
    x1 = max(0, x1 - padding)
    y1 = max(0, y1 - padding)
    x2 = min(w, x2 + padding)
    y2 = min(h, y2 + padding)
    return image_array[y1:y2, x1:x2], x1, y1


def draw_single_annotation(image_array: np.ndarray, issue: dict, offset_x: int, offset_y: int) -> np.ndarray:
    """Draw a colored mask or bounding box and label on the cropped part image."""
    img = image_array.copy()
    h, w = img.shape[:2]
    color = SEVERITY_COLORS.get(issue["severity"], (0, 255, 255))
    
    font_scale = max(0.3, min(0.6, w / 400.0))
    font_thickness = max(1, int(font_scale * 2.5))
    line_thickness = max(1, int(font_scale * 3.5))

    # Border around the entire cropped image
    cv2.rectangle(img, (2, 2), (w - 2, h - 2), color, line_thickness)

    # Draw damage mask if available, otherwise fallback to bounding box
    if issue.get("masks") is not None:
        overlay = img.copy()
        for poly_pts in issue["masks"]:
            pts = np.array(poly_pts, np.int32)
            pts[:, 0] -= offset_x
            pts[:, 1] -= offset_y
            cv2.fillPoly(overlay, [pts], (0, 0, 255)) # Red mask for damage
        cv2.addWeighted(overlay, 0.4, img, 0.6, 0, img)
        for poly_pts in issue["masks"]:
            pts = np.array(poly_pts, np.int32)
            pts[:, 0] -= offset_x
            pts[:, 1] -= offset_y
            cv2.polylines(img, [pts], True, (0, 0, 255), max(1, line_thickness - 1))
    else:
        # Fallback to bounding box
        dx1, dy1, dx2, dy2 = issue["bbox"]
        cx1 = max(0, dx1 - offset_x)
        cy1 = max(0, dy1 - offset_y)
        cx2 = min(w, dx2 - offset_x)
        cy2 = min(h, dy2 - offset_y)
        cv2.rectangle(img, (cx1, cy1), (cx2, cy2), (0, 0, 255), max(1, line_thickness - 1))

    # Add label with part name and damage percentage at the bottom
    damage_percentage = int(issue["confidence"] * 100)
    label = f'{issue["part"]} - {issue["class"]} ({damage_percentage}%)'
    
    (lw, lh), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, font_scale, font_thickness)
    cv2.rectangle(img, (0, h - lh - 14), (lw + 10, h), color, -1)
    cv2.putText(img, label, (5, h - 6),
                cv2.FONT_HERSHEY_SIMPLEX, font_scale, (255, 255, 255), font_thickness)

    return img


def image_to_base64(image_array: np.ndarray) -> str:
    """Convert a numpy BGR image to a JPEG base64 data URI."""
    img_rgb = cv2.cvtColor(image_array, cv2.COLOR_BGR2RGB)
    pil_img = Image.fromarray(img_rgb)
    buffer  = io.BytesIO()
    pil_img.save(buffer, format='JPEG', quality=85)
    buffer.seek(0)
    b64 = base64.b64encode(buffer.read()).decode('utf-8')
    return f'data:image/jpeg;base64,{b64}'


def analyze_damage_cv2(image_array: np.ndarray) -> list:
    """Run both models and return a list of detected damages with corresponding cropped part images."""
    if image_array is None or image_array.size == 0:
        return []

    parts_results  = parts_model(image_array,  verbose=False)
    damage_results = damage_model(image_array, verbose=False)

    all_parts = []
    for pr in parts_results:
        if pr.boxes is None:
            continue
        boxes = pr.boxes
        masks = pr.masks.xy if pr.masks is not None else [None] * len(boxes)
        for pb, mask in zip(boxes, masks):
            px1, py1, px2, py2 = map(int, pb.xyxy[0])
            p_conf = float(pb.conf[0].item())
            p_cls = int(pb.cls[0])
            if p_conf > 0.3:
                all_parts.append({
                    "part": parts_labels[p_cls],
                    "bbox": [px1, py1, px2, py2],
                    "masks": extract_polygons(mask),
                    "confidence": p_conf
                })

    detected_issues = []

    for r in damage_results:
        if r.boxes is None:
            continue
        boxes = r.boxes
        masks = r.masks.xy if r.masks is not None else [None] * len(boxes)
        for box, mask in zip(boxes, masks):
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            conf = float(box.conf[0].item())
            cls  = int(box.cls[0])

            if conf > 0.3:
                damage_name = damage_labels[cls]

                damage_box = [x1, y1, x2, y2]
                damage_area = (x2 - x1) * (y2 - y1)

                # Calculate intersection with all detected parts
                intersected_parts = []
                for p in all_parts:
                    px1, py1, px2, py2 = p["bbox"]
                    
                    ix1 = max(x1, px1)
                    iy1 = max(y1, py1)
                    ix2 = min(x2, px2)
                    iy2 = min(y2, py2)
                    
                    if ix1 < ix2 and iy1 < iy2:
                        intersection_area = (ix2 - ix1) * (iy2 - iy1)
                        intersected_parts.append((p, intersection_area))

                # Sort parts by intersection area (largest first)
                intersected_parts.sort(key=lambda x: x[1], reverse=True)
                
                matched_parts = []
                # Only select parts that cover a significant portion of the damage area (> 15%)
                for p, area in intersected_parts:
                    if area >= 0.15 * damage_area:
                        matched_parts.append(p)

                # If no part meets the 15% condition (e.g. erratic bounding box), use the one with the largest intersection as a fallback
                if not matched_parts and intersected_parts:
                    matched_parts.append(intersected_parts[0][0])

                # If no intersection at all, use the center point of the damage as a final fallback
                if not matched_parts:
                    cx, cy = (x1 + x2) // 2, (y1 + y2) // 2
                    for p in all_parts:
                        px1, py1, px2, py2 = p["bbox"]
                        if px1 <= cx <= px2 and py1 <= cy <= py2:
                            matched_parts.append(p)
                            break
                            
                # If no car part is detected in this region (e.g., missing side parts from the labels list)
                if not matched_parts:
                    matched_parts.append({"part": "unknown", "bbox": [x1, y1, x2, y2]})

                severity = (
                    "major"    if conf >= 0.8 else
                    "moderate" if conf >= 0.6 else
                    "minor"
                )
                
                # Calculate dynamic repair cost proportional to damage severity (confidence)
                base_min, base_max = COST_MAP.get(damage_name, (10000, 50000))
                diff = base_max - base_min
                
                # Minimum and maximum costs scale up with the damage percentage
                cost_min = int(base_min + (diff * conf * 0.5))
                cost_max = int(base_min + (diff * conf))
                
                # Round to the nearest 1000 DZD for realistic pricing
                cost_min = max(base_min, round(cost_min, -3))
                cost_max = min(base_max, round(cost_max, -3))
                
                # Ensure the maximum cost is always strictly greater than the minimum
                if cost_max <= cost_min:
                    cost_max = cost_min + 5000

                # Assign the damage to all affected parts
                for p in matched_parts:
                    part_name = p["part"]
                    
                    # Filter out physically impossible combinations (e.g., glass break on a wheel)
                    if damage_name == "glass_break" and part_name in ['wheel', 'hood', 'front_bumper', 'back_bumper', 'trunk']:
                        continue

                    issue_data = {
                        "class":      damage_name,
                        "part":       part_name,
                        "confidence": round(conf, 3),
                        "bbox":       [x1, y1, x2, y2],
                        "part_bbox":  p["bbox"],
                        "severity":   severity,
                        "cost_min":   cost_min,
                        "cost_max":   cost_max,
                        "masks":      tighten_polygons(
                                          extract_polygons(mask),
                                          [x1, y1, x2, y2],
                                          image_array.shape[1],
                                          image_array.shape[0],
                                          erosion_px=8,
                                      ),
                    }

                    # ── Crop the specific damaged part ──────────
                    part_crop, offset_x, offset_y = crop_part_image(image_array, p["bbox"], padding=15)
                    annotated_crop = draw_single_annotation(part_crop, issue_data, offset_x, offset_y)
                    issue_data["part_image"] = image_to_base64(annotated_crop)

                    detected_issues.append(issue_data)

    return detected_issues, all_parts


def draw_annotations(image_array: np.ndarray, detected_issues: list, all_parts: list) -> np.ndarray:
    """Draw all annotations on the full original image."""
    img = image_array.copy()
    overlay = img.copy()
    h, w = img.shape[:2]

    font_scale     = max(0.4, min(1.0, w / 900.0))
    font_thickness = max(1, int(font_scale * 2.5))
    line_thickness = max(1, int(font_scale * 3.0))

    # ── Unique color palette per part (BGR) ────────────────────
    PART_COLORS = [
        (0,   0,   220),   # red
        (0,   180, 255),   # orange
        (220, 0,   220),   # magenta
        (255, 100, 0  ),   # blue
        (0,   220, 180),   # yellow-green
        (180, 0,   255),   # purple
        (0,   200, 80 ),   # lime green
        (255, 0,   150),   # deep pink
        (200, 160, 0  ),   # teal-blue
        (0,   120, 255),   # sky orange
    ]

    # Assign one color per unique part name encountered
    part_color_map = {}
    color_idx = 0
    for issue in detected_issues:
        pname = issue.get("part", "unknown")
        if pname not in part_color_map:
            part_color_map[pname] = PART_COLORS[color_idx % len(PART_COLORS)]
            color_idx += 1

    # ── Pass 1: fill semi-transparent mask per part ─────────────
    for issue in detected_issues:
        color    = part_color_map[issue.get("part", "unknown")]
        # Use the full part_bbox as the clip area so the entire part is covered
        part_box = issue["part_bbox"]
        px1, py1, px2, py2 = part_box

        if issue.get("masks") is not None:
            # Clip the damage mask to the part bounding box
            tight = tighten_polygons(issue["masks"], part_box, w, h, erosion_px=0)
            if tight:
                for poly_pts in tight:
                    pts = np.array(poly_pts, np.int32)
                    cv2.fillPoly(overlay, [pts], color)
                continue
        # Fallback: fill the entire part bbox
        cv2.rectangle(overlay, (px1, py1), (px2, py2), color, -1)

    cv2.addWeighted(overlay, 0.4, img, 0.6, 0, img)

    # ── Pass 2: outlines and labels ─────────────────────────────
    for issue in detected_issues:
        color    = part_color_map[issue.get("part", "unknown")]
        part_box = issue["part_bbox"]
        px1, py1, px2, py2 = part_box

        # Mask outline clipped to part bbox
        if issue.get("masks") is not None:
            tight = tighten_polygons(issue["masks"], part_box, w, h, erosion_px=0)
            if tight:
                for poly_pts in tight:
                    pts = np.array(poly_pts, np.int32)
                    cv2.polylines(img, [pts], True, color, max(1, line_thickness - 1))

        # Part bounding box outline
        cv2.rectangle(img, (px1, py1), (px2, py2), color, line_thickness)

        # Label: part name + damage class + confidence
        part_name    = issue.get("part", "unknown")
        damage_label = f"{part_name}: {issue['class']} {int(issue['confidence']*100)}%"
        (lw, lh), _  = cv2.getTextSize(damage_label, cv2.FONT_HERSHEY_SIMPLEX,
                                        font_scale * 0.8, font_thickness)
        text_y = py1 if py1 > lh + 10 else py1 + lh + 15
        cv2.rectangle(img, (px1, text_y - lh - 10), (px1 + lw + 4, text_y), color, -1)
        cv2.putText(img, damage_label, (px1 + 2, text_y - 5),
                    cv2.FONT_HERSHEY_SIMPLEX, font_scale * 0.8, (255, 255, 255), font_thickness)

    return img


@app.route('/predict', methods=['POST'])
def predict():
    if 'image' not in request.files:
        return jsonify({"success": False, "error": "No image file provided."}), 400

    file = request.files['image']
    if file.filename == '':
        return jsonify({"success": False, "error": "Empty filename."}), 400

    try:
        # ── Decode the image ──────────────────────────────────
        file_bytes  = np.frombuffer(file.read(), dtype=np.uint8)
        image_array = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)
        if image_array is None:
            return jsonify({"success": False, "error": "Could not decode image."}), 422

        h, w = image_array.shape[:2]

        # ── Run the models ────────────────────────────────────
        detected_issues, all_parts = analyze_damage_cv2(image_array)
        is_undamaged    = len(detected_issues) == 0

        # ── Draw annotations on the full image ────────────────
        annotated     = draw_annotations(image_array, detected_issues, all_parts)
        annotated_b64 = image_to_base64(annotated)

        # ── Calculate total estimated costs ───────────────────
        cost_min = sum(d["cost_min"] for d in detected_issues)
        cost_max = sum(d["cost_max"] for d in detected_issues)

        # ── Build the summary payload ─────────────────────────
        issues_summary = []
        for d in detected_issues:
            issues_summary.append({
                "class":      d["class"],
                "part":       d["part"],
                "confidence": d["confidence"],
                "bbox":       d["bbox"],
                "part_bbox":  d["part_bbox"],
                "severity":   d["severity"],
                "cost_min":   d["cost_min"],
                "cost_max":   d["cost_max"],
                "part_image": d["part_image"],  # Cropped part image
            })

        return jsonify({
            "success":             True,
            "is_undamaged":        is_undamaged,
            "total_detections":    len(detected_issues),
            "detected_issues":     issues_summary,
            "annotated_image":     annotated_b64,
            "cost_min":            cost_min,
            "cost_max":            cost_max,
            "original_dimensions": {"width": w, "height": h},
        })

    except Exception as e:
        logger.error(f"Inference error: {str(e)}\n{traceback.format_exc()}")
        return jsonify({"success": False, "error": str(e)}), 500


# ── Alternative Route (Alias) ────────────────────────────────
@app.route('/analyze', methods=['POST'])
def analyze():
    return predict()


# ── Health Check Route ───────────────────────────────────────
@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "ok"})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)