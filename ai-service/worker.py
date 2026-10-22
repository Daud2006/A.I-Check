import concurrent.futures
import hmac
import ipaddress
import json
import logging
import math
import os
import secrets
import sys
import threading
import warnings
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any

from PIL import Image, UnidentifiedImageError


MODEL_ID = "dima806/ai_vs_human_generated_image_detection"
MAX_IMAGE_BYTES = 10 * 1024 * 1024
MAX_JSON_BYTES = 8 * 1024
MAX_IMAGE_PIXELS = 40_000_000
INFERENCE_TIMEOUT_SECONDS = 60
ALLOWED_SUFFIXES = {".jpg", ".jpeg", ".png", ".webp"}
ALLOWED_PIL_FORMATS = {"JPEG", "PNG", "WEBP"}
LOGGER = logging.getLogger("ai_service.worker")

Image.MAX_IMAGE_PIXELS = MAX_IMAGE_PIXELS
warnings.simplefilter("error", Image.DecompressionBombWarning)


class WorkerError(Exception):
    def __init__(self, status: int, message: str):
        super().__init__(message)
        self.status = status
        self.message = message


class PersistentInferenceWorker:
    def __init__(
        self,
        image_root: Path,
        processor: Any,
        model: Any,
        labels: dict[int, str],
        inference_function=None,
        inference_timeout_seconds: float = INFERENCE_TIMEOUT_SECONDS,
    ):
        self.image_root = image_root.resolve(strict=True)
        if not self.image_root.is_dir():
            raise ValueError("Private image storage root is unavailable.")

        self.processor = processor
        self.model = model
        self.labels = labels
        if inference_function is None:
            from predict import predict_image

            inference_function = predict_image
        self.inference_function = inference_function
        self.inference_timeout_seconds = inference_timeout_seconds
        self.executor = concurrent.futures.ThreadPoolExecutor(
            max_workers=1,
            thread_name_prefix="local-image-inference",
        )
        self.inference_slot = threading.BoundedSemaphore(1)
        self.closed = False

    def infer_path(self, requested_path: str) -> dict[str, Any]:
        image_path = self._resolve_private_image(requested_path)
        image = self._load_valid_image(image_path)

        if not self.inference_slot.acquire(blocking=False):
            raise WorkerError(429, "Worker is busy. Retry shortly.")

        try:
            future = self.executor.submit(
                self.inference_function,
                image,
                self.processor,
                self.model,
                self.labels,
            )
        except Exception:
            self.inference_slot.release()
            raise

        future.add_done_callback(lambda _: self.inference_slot.release())
        try:
            prediction = future.result(timeout=self.inference_timeout_seconds)
        except concurrent.futures.TimeoutError as error:
            LOGGER.warning("inference_timeout")
            raise WorkerError(504, "Image analysis timed out.") from error
        except Exception as error:
            LOGGER.error("inference_failed exception_class=%s", type(error).__name__)
            raise WorkerError(500, "Image analysis failed.") from error

        return self._validate_prediction(prediction)

    def close(self) -> None:
        if self.closed:
            return
        self.closed = True
        self.executor.shutdown(wait=False, cancel_futures=True)

    def _resolve_private_image(self, requested_path: str) -> Path:
        if not isinstance(requested_path, str) or not requested_path:
            raise WorkerError(400, "A private image path is required.")

        supplied_path = Path(requested_path)
        if not supplied_path.is_absolute() or ".." in supplied_path.parts:
            raise WorkerError(403, "Image path is outside private storage.")

        try:
            resolved_path = supplied_path.resolve(strict=True)
            resolved_path.relative_to(self.image_root)
        except (OSError, ValueError):
            raise WorkerError(403, "Image path is outside private storage.") from None

        if not resolved_path.is_file() or resolved_path.suffix.lower() not in ALLOWED_SUFFIXES:
            raise WorkerError(422, "The image file is invalid.")

        try:
            if resolved_path.stat().st_size > MAX_IMAGE_BYTES:
                raise WorkerError(413, "Image exceeds the allowed size.")
        except OSError:
            raise WorkerError(422, "The image file is invalid.") from None

        return resolved_path

    @staticmethod
    def _load_valid_image(image_path: Path) -> Image.Image:
        try:
            with Image.open(image_path) as probe:
                if probe.format not in ALLOWED_PIL_FORMATS:
                    raise WorkerError(422, "The image format is not supported.")
                if probe.width * probe.height > MAX_IMAGE_PIXELS:
                    raise WorkerError(413, "Image dimensions exceed the allowed limit.")
                probe.verify()

            with Image.open(image_path) as source:
                return source.convert("RGB")
        except WorkerError:
            raise
        except (OSError, UnidentifiedImageError, Image.DecompressionBombError, Image.DecompressionBombWarning):
            raise WorkerError(422, "The image file is invalid.") from None

    @staticmethod
    def _validate_prediction(prediction: Any) -> dict[str, Any]:
        if (
            not isinstance(prediction, dict)
            or set(prediction) != {"label", "score", "model", "inference_time_ms"}
            or prediction.get("label") not in {"human", "ai_generated"}
            or isinstance(prediction.get("score"), bool)
            or not isinstance(prediction.get("score"), (int, float))
            or not math.isfinite(prediction["score"])
            or not 0 <= prediction["score"] <= 1
            or prediction.get("model") != MODEL_ID
            or isinstance(prediction.get("inference_time_ms"), bool)
            or not isinstance(prediction.get("inference_time_ms"), (int, float))
            or not math.isfinite(prediction["inference_time_ms"])
            or prediction["inference_time_ms"] < 0
        ):
            raise WorkerError(500, "Image analysis failed.")

        return {
            "label": prediction["label"],
            "score": float(prediction["score"]),
            "model": MODEL_ID,
            "inference_time_ms": float(prediction["inference_time_ms"]),
        }


def create_worker(image_root: Path | None = None, model_loader=None) -> PersistentInferenceWorker:
    if model_loader is None:
        from predict import load_model

        model_loader = load_model

    processor, model, labels = model_loader()
    return PersistentInferenceWorker(
        image_root or private_image_root(),
        processor,
        model,
        labels,
    )


class BoundedThreadingHTTPServer(ThreadingHTTPServer):
    request_queue_size = 2
    daemon_threads = True

    def __init__(self, server_address, request_handler, worker, secret):
        self.worker = worker
        self.secret = secret
        self.handler_slots = threading.BoundedSemaphore(4)
        super().__init__(server_address, request_handler)

    def process_request(self, request, client_address):
        if not self.handler_slots.acquire(blocking=False):
            try:
                response_body = b'{"error":"Worker is overloaded."}'
                request.sendall(
                    b"HTTP/1.1 503 Service Unavailable\r\n"
                    b"Content-Type: application/json\r\n"
                    b"Connection: close\r\n"
                    + f"Content-Length: {len(response_body)}\r\n\r\n".encode("ascii")
                    + response_body
                )
            except OSError:
                pass
            finally:
                self.shutdown_request(request)
            return

        try:
            super().process_request(request, client_address)
        except Exception:
            self.handler_slots.release()
            raise

    def process_request_thread(self, request, client_address):
        try:
            super().process_request_thread(request, client_address)
        finally:
            self.handler_slots.release()

    def handle_error(self, request, client_address):
        LOGGER.warning("request_handler_failed")


class WorkerRequestHandler(BaseHTTPRequestHandler):
    server: BoundedThreadingHTTPServer
    protocol_version = "HTTP/1.1"

    def setup(self):
        super().setup()
        self.request.settimeout(5)

    def log_message(self, format_string, *args):
        return

    def do_GET(self):
        if not self._authorized():
            self._send_json(401, {"error": "Unauthorized."})
            return

        if self.path != "/health":
            self._send_json(404, {"error": "Not found."})
            return

        self._send_json(200, {"status": "ready", "model": MODEL_ID})

    def do_POST(self):
        if not self._authorized():
            self._send_json(401, {"error": "Unauthorized."})
            return

        if self.path != "/infer":
            self._send_json(404, {"error": "Not found."})
            return

        if self.headers.get_content_type() != "application/json":
            self._send_json(415, {"error": "Content-Type must be application/json."})
            return

        content_length = self.headers.get("Content-Length")
        if content_length is None or not content_length.isdecimal():
            self._send_json(400, {"error": "A valid Content-Length is required."})
            return

        if int(content_length) > MAX_JSON_BYTES:
            self._send_json(413, {"error": "Request is too large."})
            return

        try:
            body = self.rfile.read(int(content_length))
            request_data = json.loads(body)
        except (OSError, json.JSONDecodeError, UnicodeDecodeError):
            self._send_json(400, {"error": "Request JSON is invalid."})
            return

        if not isinstance(request_data, dict) or set(request_data) != {"path"}:
            self._send_json(400, {"error": "Request must contain only a private image path."})
            return

        try:
            prediction = self.server.worker.infer_path(request_data["path"])
        except WorkerError as error:
            self._send_json(error.status, {"error": error.message})
            return
        except Exception as error:
            LOGGER.error("request_inference_failed exception_class=%s", type(error).__name__)
            self._send_json(500, {"error": "Image analysis failed."})
            return

        self._send_json(200, prediction)

    def _authorized(self) -> bool:
        if not self.client_address or not self._is_loopback(self.client_address[0]):
            return False

        supplied = self.headers.get("Authorization", "")
        prefix = "Bearer "
        if not supplied.startswith(prefix):
            return False

        return hmac.compare_digest(supplied[len(prefix):], self.server.secret)

    @staticmethod
    def _is_loopback(address: str) -> bool:
        try:
            return ipaddress.ip_address(address).is_loopback
        except ValueError:
            return False

    def _send_json(self, status: int, response_data: dict[str, Any]) -> None:
        body = json.dumps(response_data, allow_nan=False, separators=(",", ":")).encode("utf-8")
        try:
            self.send_response(status)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Cache-Control", "no-store")
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(body)
        except (BrokenPipeError, ConnectionResetError, OSError):
            LOGGER.warning("client_connection_closed")
        finally:
            self.close_connection = True


def private_image_root() -> Path:
    return Path(__file__).resolve().parent.parent / "storage" / "app" / "private" / "image-analyses"


def main() -> int:
    secret = os.environ.get("AI_WORKER_SECRET", "")
    if len(secret.encode("utf-8")) < 32:
        print("worker startup failed: AI_WORKER_SECRET must contain at least 32 bytes", file=sys.stderr)
        return 1

    try:
        import torch

        torch.set_num_threads(max(1, min(4, os.cpu_count() or 1)))
        worker = create_worker()
        server = BoundedThreadingHTTPServer(("127.0.0.1", 8765), WorkerRequestHandler, worker, secret)
    except Exception as error:
        LOGGER.error("worker_startup_failed exception_class=%s", type(error).__name__)
        print("worker startup failed: local model or private storage is unavailable", file=sys.stderr)
        return 1

    LOGGER.info("worker_ready host=127.0.0.1 port=8765 model=%s", MODEL_ID)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        LOGGER.info("worker_shutdown_requested")
    finally:
        server.server_close()
        worker.close()
    return 0


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    raise SystemExit(main())
