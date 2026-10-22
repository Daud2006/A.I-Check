import http.client
import json
import os
import subprocess
import sys
import tempfile
import threading
import time
import unittest
from pathlib import Path
from unittest.mock import Mock
from unittest.mock import patch

from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from worker import (
    MODEL_ID,
    BoundedThreadingHTTPServer,
    PersistentInferenceWorker,
    WorkerError,
    WorkerRequestHandler,
    create_worker,
)


TEST_SECRET = "poc-test-secret-that-is-not-used-in-production"
VALID_PREDICTION = {
    "label": "human",
    "score": 0.75,
    "model": MODEL_ID,
    "inference_time_ms": 12.5,
}


class LocalWorkerTests(unittest.TestCase):
    def setUp(self):
        self.temp_dir = tempfile.TemporaryDirectory()
        self.root = Path(self.temp_dir.name) / "private" / "image-analyses"
        self.root.mkdir(parents=True)
        self.image_path = self.root / "photo.png"
        Image.new("RGB", (16, 16), color=(40, 80, 120)).save(self.image_path)
        self.predictor = Mock(return_value=dict(VALID_PREDICTION))
        self.worker = PersistentInferenceWorker(
            self.root,
            object(),
            object(),
            {0: "human", 1: "AI-generated"},
            inference_function=self.predictor,
            inference_timeout_seconds=0.2,
        )
        self.server = BoundedThreadingHTTPServer(
            ("127.0.0.1", 0),
            WorkerRequestHandler,
            self.worker,
            TEST_SECRET,
        )
        self.server_thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.server_thread.start()
        self.base_url = self.server.server_address

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.worker.close()
        self.server_thread.join(timeout=2)
        self.temp_dir.cleanup()

    def request(self, method, path, *, secret=TEST_SECRET, payload=None, content_type="application/json"):
        connection = http.client.HTTPConnection(*self.base_url, timeout=3)
        headers = {}
        if secret is not None:
            headers["Authorization"] = f"Bearer {secret}"
        body = None
        if payload is not None:
            body = json.dumps(payload)
            headers["Content-Type"] = content_type
        connection.request(method, path, body=body, headers=headers)
        response = connection.getresponse()
        response_body = response.read()
        connection.close()
        return response.status, json.loads(response_body) if response_body else None

    def test_startup_loads_model_once_and_health_returns_safe_status(self):
        loader = Mock(return_value=(object(), object(), {0: "human", 1: "AI-generated"}))
        worker = create_worker(self.root, loader)
        self.assertIs(worker.processor, loader.return_value[0])
        loader.assert_called_once_with()
        worker.close()

        self.assertEqual(self.server.server_address[0], "127.0.0.1")
        status, body = self.request("GET", "/health")
        self.assertEqual(status, 200)
        self.assertEqual(body, {"status": "ready", "model": MODEL_ID})
        self.assertNotIn("secret", json.dumps(body).lower())
        self.assertNotIn(str(self.root), json.dumps(body))

    def test_health_requires_authentication_and_rejects_wrong_secret(self):
        self.assertEqual(self.request("GET", "/health", secret=None)[0], 401)
        self.assertEqual(self.request("GET", "/health", secret="wrong-secret")[0], 401)

    def test_infer_returns_the_expected_json_contract(self):
        status, body = self.request("POST", "/infer", payload={"path": str(self.image_path)})
        self.assertEqual(status, 200)
        self.assertEqual(set(body), {"label", "score", "model", "inference_time_ms"})
        self.assertEqual(body, VALID_PREDICTION)
        self.predictor.assert_called_once()

    def test_infer_requires_auth_and_json_content_type(self):
        status, _ = self.request("POST", "/infer", secret=None, payload={"path": str(self.image_path)})
        self.assertEqual(status, 401)
        status, _ = self.request(
            "POST",
            "/infer",
            payload={"path": str(self.image_path)},
            content_type="text/plain",
        )
        self.assertEqual(status, 415)
        self.predictor.assert_not_called()

    def test_path_traversal_outside_root_and_symlink_escape_are_rejected(self):
        traversal = str(self.root / ".." / "outside.png")
        self.assertEqual(self.request("POST", "/infer", payload={"path": traversal})[0], 403)

        with tempfile.TemporaryDirectory() as external_dir:
            external_image = Path(external_dir) / "outside.png"
            Image.new("RGB", (8, 8)).save(external_image)
            self.assertEqual(self.request("POST", "/infer", payload={"path": str(external_image)})[0], 403)

            escaped_link = self.root / "escaped.png"
            try:
                escaped_link.symlink_to(external_image)
            except (OSError, NotImplementedError):
                if sys.platform != "win32":
                    self.skipTest("Symlink creation is unavailable in this environment.")

                junction = self.root / "escaped-directory"
                try:
                    environment = dict(os.environ)
                    environment["WORKER_JUNCTION"] = str(junction)
                    environment["WORKER_TARGET"] = str(external_dir)
                    subprocess.run(
                        [
                            "powershell.exe",
                            "-NoProfile",
                            "-Command",
                            "New-Item -ItemType Junction -LiteralPath $env:WORKER_JUNCTION -Target $env:WORKER_TARGET | Out-Null",
                        ],
                        check=True,
                        capture_output=True,
                        timeout=5,
                        env=environment,
                    )
                except (OSError, subprocess.SubprocessError):
                    self.skipTest("Windows symlink and junction creation are unavailable.")
                escaped_link = junction / "outside.png"
                escaped_link.write_bytes(external_image.read_bytes())

            self.assertEqual(self.request("POST", "/infer", payload={"path": str(escaped_link)})[0], 403)

        self.predictor.assert_not_called()

    def test_canonical_symlink_target_outside_root_is_rejected(self):
        link_path = self.root / "link.png"
        link_path.write_bytes(b"symlink target is mocked")
        outside_path = self.temp_dir.name and Path(self.temp_dir.name).parent / "outside.png"
        original_resolve = Path.resolve

        def resolve_path(path, strict=False):
            if path == link_path:
                return outside_path
            return original_resolve(path, strict=strict)

        with patch.object(Path, "resolve", resolve_path):
            with self.assertRaises(WorkerError) as error:
                self.worker.infer_path(str(link_path))

        self.assertEqual(error.exception.status, 403)
        self.predictor.assert_not_called()

    def test_invalid_image_and_image_over_size_limit_are_rejected(self):
        invalid = self.root / "invalid.png"
        invalid.write_bytes(b"not an image")
        self.assertEqual(self.request("POST", "/infer", payload={"path": str(invalid)})[0], 422)

        oversized = self.root / "oversized.png"
        with oversized.open("wb") as image_file:
            image_file.truncate(10 * 1024 * 1024 + 1)
        self.assertEqual(self.request("POST", "/infer", payload={"path": str(oversized)})[0], 413)
        self.predictor.assert_not_called()

    def test_unexpected_request_fields_and_large_json_body_are_rejected(self):
        self.assertEqual(
            self.request("POST", "/infer", payload={"path": str(self.image_path), "extra": "value"})[0],
            400,
        )

        connection = http.client.HTTPConnection(*self.base_url, timeout=3)
        connection.request(
            "POST",
            "/infer",
            body=json.dumps({"path": "a" * 9000}),
            headers={
                "Authorization": f"Bearer {TEST_SECRET}",
                "Content-Type": "application/json",
            },
        )
        response = connection.getresponse()
        self.assertEqual(response.status, 413)
        response.read()
        connection.close()

    def test_two_inferences_run_in_one_persistent_model_instance(self):
        for _ in range(2):
            status, body = self.request("POST", "/infer", payload={"path": str(self.image_path)})
            self.assertEqual(status, 200)
            self.assertEqual(body, VALID_PREDICTION)

        self.assertEqual(self.predictor.call_count, 2)
        self.assertIsNotNone(self.worker.model)
        self.assertIsNotNone(self.worker.processor)

    def test_invalid_prediction_and_predictor_exception_never_return_a_score(self):
        self.predictor.return_value = {**VALID_PREDICTION, "score": float("nan")}
        status, body = self.request("POST", "/infer", payload={"path": str(self.image_path)})
        self.assertEqual(status, 500)
        self.assertEqual(body, {"error": "Image analysis failed."})
        self.assertNotIn("score", body)

        self.predictor.side_effect = RuntimeError("private image path and traceback")
        status, body = self.request("POST", "/infer", payload={"path": str(self.image_path)})
        self.assertEqual(status, 500)
        self.assertNotIn("private image path", json.dumps(body))
        self.assertNotIn(str(self.root), json.dumps(body))

    def test_inference_timeout_returns_504_and_busy_slot_rejects_parallel_request(self):
        started = threading.Event()
        release = threading.Event()

        def slow_prediction(*_args):
            started.set()
            release.wait(timeout=2)
            return dict(VALID_PREDICTION)

        self.predictor.side_effect = slow_prediction
        result = {}

        request_thread = threading.Thread(
            target=lambda: result.setdefault(
                "response",
                self.request("POST", "/infer", payload={"path": str(self.image_path)}),
            ),
            daemon=True,
        )
        request_thread.start()
        self.assertTrue(started.wait(timeout=1))

        busy_status, busy_body = self.request(
            "POST",
            "/infer",
            payload={"path": str(self.image_path)},
        )
        self.assertEqual(busy_status, 429)
        self.assertNotIn("score", busy_body)

        request_thread.join(timeout=2)
        self.assertFalse(request_thread.is_alive())
        self.assertEqual(result["response"][0], 504)
        self.assertEqual(result["response"][1], {"error": "Image analysis timed out."})

        release.set()
        deadline = time.monotonic() + 1
        acquired = self.worker.inference_slot.acquire(blocking=False)
        while not acquired and time.monotonic() < deadline:
            time.sleep(0.01)
            acquired = self.worker.inference_slot.acquire(blocking=False)
        self.assertTrue(acquired)
        self.worker.inference_slot.release()

    def test_worker_creation_fails_cleanly_when_model_loader_fails(self):
        loader = Mock(side_effect=FileNotFoundError("private model path"))
        with self.assertRaises(FileNotFoundError):
            create_worker(self.root, loader)
        loader.assert_called_once_with()

    def test_missing_startup_secret_exits_without_exposing_internal_details(self):
        environment = dict(os.environ)
        environment.pop("AI_WORKER_SECRET", None)
        completed = subprocess.run(
            [sys.executable, str(Path(__file__).resolve().parents[1] / "worker.py")],
            capture_output=True,
            text=True,
            timeout=10,
            env=environment,
        )
        self.assertNotEqual(completed.returncode, 0)
        self.assertIn("AI_WORKER_SECRET", completed.stderr)
        self.assertNotIn("Traceback", completed.stderr)
        self.assertNotIn(str(self.root), completed.stderr)


if __name__ == "__main__":
    unittest.main()
