import argparse
import ctypes
import contextlib
import json
import math
import os
import sys
import time
from ctypes import wintypes
from pathlib import Path

import torch
from PIL import Image, UnidentifiedImageError
from transformers import AutoImageProcessor, AutoModelForImageClassification


MODEL_DIR = Path(__file__).resolve().parent / "model"


class ProcessMemoryCounters(ctypes.Structure):
    _fields_ = [
        ("cb", wintypes.DWORD),
        ("PageFaultCount", wintypes.DWORD),
        ("PeakWorkingSetSize", ctypes.c_size_t),
        ("WorkingSetSize", ctypes.c_size_t),
        ("QuotaPeakPagedPoolUsage", ctypes.c_size_t),
        ("QuotaPagedPoolUsage", ctypes.c_size_t),
        ("QuotaPeakNonPagedPoolUsage", ctypes.c_size_t),
        ("QuotaNonPagedPoolUsage", ctypes.c_size_t),
        ("PagefileUsage", ctypes.c_size_t),
        ("PeakPagefileUsage", ctypes.c_size_t),
    ]


def peak_working_set_mib() -> float | None:
    if sys.platform != "win32":
        return None

    counters = ProcessMemoryCounters()
    counters.cb = ctypes.sizeof(counters)
    kernel32 = ctypes.WinDLL("kernel32", use_last_error=True)
    psapi = ctypes.WinDLL("psapi", use_last_error=True)
    kernel32.GetCurrentProcess.restype = wintypes.HANDLE
    psapi.GetProcessMemoryInfo.argtypes = [
        wintypes.HANDLE,
        ctypes.POINTER(ProcessMemoryCounters),
        wintypes.DWORD,
    ]
    psapi.GetProcessMemoryInfo.restype = wintypes.BOOL
    success = psapi.GetProcessMemoryInfo(
        kernel32.GetCurrentProcess(),
        ctypes.byref(counters),
        counters.cb,
    )
    if not success:
        return None
    return counters.PeakWorkingSetSize / (1024 * 1024)


def load_model():
    if not (MODEL_DIR / "model.safetensors").is_file():
        raise FileNotFoundError("Model files are missing. Run download_model.py first.")

    config_path = MODEL_DIR / "config.json"
    config = json.loads(config_path.read_text(encoding="utf-8"))
    raw_labels = config.get("id2label")
    if not isinstance(raw_labels, dict) or not raw_labels:
        raise ValueError("Model configuration has no id2label mapping.")

    labels = {}
    for index, label in raw_labels.items():
        if not str(index).isdigit() or not isinstance(label, str) or not label.strip():
            raise ValueError("Model configuration contains an invalid id2label mapping.")
        labels[int(index)] = label

    processor = AutoImageProcessor.from_pretrained(
        MODEL_DIR,
        local_files_only=True,
        use_fast=False,
    )
    model = AutoModelForImageClassification.from_pretrained(
        MODEL_DIR,
        local_files_only=True,
        use_safetensors=True,
    )
    model.to(torch.device("cpu"))
    model.eval()

    if model.config.id2label:
        labels = {int(index): label for index, label in model.config.id2label.items()}

    return processor, model, labels


def predict_image(image: Image.Image, processor, model, labels: dict[int, str]) -> dict[str, str | float]:
    started = time.perf_counter()
    inputs = processor(images=image, return_tensors="pt")
    with torch.inference_mode():
        logits = model(**inputs).logits[0]
        probabilities = torch.softmax(logits, dim=-1)
        score, predicted_index = torch.max(probabilities, dim=-1)
    elapsed = time.perf_counter() - started

    index = int(predicted_index.item())
    if index not in labels:
        raise ValueError("Predicted class is absent from the model label mapping.")

    configured_label = labels[index].strip().casefold().replace("-", "_").replace(" ", "_")
    if configured_label not in {"human", "ai_generated", "artificial"}:
        raise ValueError("Predicted label is not supported by the local integration.")

    model_score = float(score.item())
    if not math.isfinite(model_score) or not 0 <= model_score <= 1:
        raise ValueError("Predicted score is outside the supported range.")

    return {
        "label": "ai_generated" if configured_label in {"ai_generated", "artificial"} else "human",
        "score": model_score,
        "model": "dima806/ai_vs_human_generated_image_detection",
        "inference_time_ms": round(elapsed * 1000, 3),
    }


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Run the local AI-image classifier on CPU. No image is sent externally."
    )
    parser.add_argument(
        "image",
        nargs="?",
        help="Path to a local image; required unless --check-model is used.",
    )
    parser.add_argument(
        "--check-model",
        action="store_true",
        help="Load the local model and show its configured labels without inference.",
    )
    parser.add_argument(
        "--json",
        action="store_true",
        help="Write a single JSON prediction object to stdout.",
    )
    args = parser.parse_args()

    torch.set_num_threads(max(1, min(4, os.cpu_count() or 1)))

    try:
        with contextlib.redirect_stdout(sys.stderr if args.json else sys.stdout):
            processor, model, labels = load_model()

        if args.check_model:
            if args.json:
                print(json.dumps({"model_load": "ok", "labels": labels}, allow_nan=False))
            else:
                print("model_load=ok device=cpu")
                print(f"labels={json.dumps(labels, ensure_ascii=False, sort_keys=True)}")
            return 0
        if not args.image:
            parser.error("Provide an image path or use --check-model.")

        image_path = Path(args.image).expanduser()
        with Image.open(image_path) as source:
            image = source.convert("RGB")

        prediction = predict_image(image, processor, model, labels)

        if args.json:
            print(json.dumps(prediction, allow_nan=False, separators=(",", ":")))
            return 0

        display_label = next(
            (
                label
                for label in labels.values()
                if label.strip().casefold().replace("-", "_").replace(" ", "_")
                in (
                    {"ai_generated", "artificial"}
                    if prediction["label"] == "ai_generated"
                    else {"human"}
                )
            ),
            str(prediction["label"]),
        )
        print(f"prediction={display_label}")
        print(f"model_score={float(prediction['score']):.6f}")
        print(f"inference_seconds={float(prediction['inference_time_ms']) / 1000:.3f}")
        peak_mib = peak_working_set_mib()
        if peak_mib is not None:
            print(f"process_peak_working_set_mib={peak_mib:.1f}")
        else:
            print("process_peak_working_set_mib=unavailable")
        return 0
    except (FileNotFoundError, UnidentifiedImageError, OSError, ValueError) as error:
        print(f"run_failed=true error_type={type(error).__name__}", file=sys.stderr if args.json else sys.stdout)
        return 1
    except Exception as error:
        print(f"run_failed=true error_type={type(error).__name__}", file=sys.stderr if args.json else sys.stdout)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
