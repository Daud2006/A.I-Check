from pathlib import Path

from huggingface_hub import snapshot_download


MODEL_ID = "dima806/ai_vs_human_generated_image_detection"
MODEL_REVISION = "341594f2b003d185db789becf48a2286c177b926"
MODEL_DIR = Path(__file__).resolve().parent / "model"
MODEL_FILES = (
    "config.json",
    "model.safetensors",
    "preprocessor_config.json",
)


def main() -> int:
    try:
        snapshot_download(
            repo_id=MODEL_ID,
            revision=MODEL_REVISION,
            local_dir=MODEL_DIR,
            allow_patterns=list(MODEL_FILES),
        )
    except Exception as error:
        print(f"download_failed=true error_type={type(error).__name__}")
        return 1

    missing = [name for name in MODEL_FILES if not (MODEL_DIR / name).is_file()]
    if missing:
        print(f"download_incomplete=true missing_files={','.join(missing)}")
        return 1

    weight_size = (MODEL_DIR / "model.safetensors").stat().st_size
    print(f"download_complete=true model_id={MODEL_ID}")
    print(f"weights_bytes={weight_size}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
