#!/usr/bin/env python3
"""Validate and update helper-owned translation catalogs.

Helpers expose their English source texts in a private constant whose name ends
with ``_TRANSLATION_SOURCES``. The matching catalog is stored below
``src/translations/<Helper>.json``. Existing translations are never overwritten
unless the English source text changed.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
SOURCE_DIR = ROOT / "src"
TRANSLATION_DIR = SOURCE_DIR / "translations"
MODELS_ENDPOINT = "https://models.github.ai/inference/chat/completions"
DEFAULT_MODEL = "openai/gpt-4.1"
NAMESPACE_PATTERN = re.compile(r"namespace\s+([^;]+);")
TYPE_PATTERN = re.compile(r"\b(?:trait|class)\s+([A-Za-z_][A-Za-z0-9_]*)")
PLACEHOLDER_PATTERN = re.compile(r"\{\{[^{}]+\}\}|\{[^{}]+\}|%\d*\$?[a-zA-Z]")


def helper_sources(path: Path) -> dict[str, str]:
    source = path.read_text(encoding="utf-8")
    namespace_match = NAMESPACE_PATTERN.search(source)
    type_match = TYPE_PATTERN.search(source)
    if namespace_match is None or type_match is None or "_TRANSLATION_SOURCES" not in source:
        return {}

    fully_qualified_name = namespace_match.group(1).strip() + "\\" + type_match.group(1)
    php = r'''
$path = $argv[1];
$type = $argv[2];
require_once $path;
$reflection = new ReflectionClass($type);
$result = [];
foreach ($reflection->getReflectionConstants() as $constant) {
    if (!str_ends_with($constant->getName(), '_TRANSLATION_SOURCES')) {
        continue;
    }
    $value = $constant->getValue();
    if (!is_array($value)) {
        fwrite(STDERR, $constant->getName() . " must be an array.\n");
        exit(2);
    }
    foreach ($value as $key => $text) {
        if (!is_string($key) || !is_string($text)) {
            fwrite(STDERR, $constant->getName() . " must map string keys to string texts.\n");
            exit(3);
        }
        $result[$key] = $text;
    }
}
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
'''
    result = subprocess.run(
        ["php", "-r", php, str(path), fully_qualified_name],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    decoded = json.loads(result.stdout)
    if not isinstance(decoded, dict) or not all(isinstance(k, str) and isinstance(v, str) for k, v in decoded.items()):
        raise RuntimeError(f"Unexpected translation sources in {path}")

    return decoded


def load_catalog(helper: str) -> dict[str, Any]:
    path = TRANSLATION_DIR / f"{helper}.json"
    if not path.is_file():
        return {
            "schema": 1,
            "sourceLanguage": "en",
            "requiredLanguages": ["en", "de"],
            "translations": {},
        }
    decoded = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(decoded, dict):
        raise RuntimeError(f"{path} must contain a JSON object.")
    return decoded


def placeholders(text: str) -> list[str]:
    return sorted(PLACEHOLDER_PATTERN.findall(text))


def translate_with_github_models(texts: dict[str, str], target_language: str) -> dict[str, str]:
    token = os.environ.get("GITHUB_MODELS_TOKEN") or os.environ.get("GITHUB_TOKEN")
    if not token:
        raise RuntimeError(
            "Missing GITHUB_MODELS_TOKEN/GITHUB_TOKEN. Automatic translation requires GitHub Models access."
        )

    model = os.environ.get("TRANSLATION_MODEL", DEFAULT_MODEL)
    prompt = {
        "targetLanguage": target_language,
        "texts": texts,
    }
    system_message = (
        "You translate configuration-form UI text for the Symcon home-automation platform. "
        "Translate English to natural, concise German suitable for software labels and help text. "
        "Keep the product names IPSView, Symcon, WebContent, HTML-Box and Popup unchanged. "
        "Use these preferred terms: opacity=Deckkraft, control=Bedienelement, view background=View-Hintergrund, "
        "page background=Seitenhintergrund, border=Rahmen, line=Linie, style source=Stilquelle. "
        "Preserve placeholders, punctuation, percent signs and technical identifiers exactly. "
        "Return only a JSON object with exactly the input keys and German string values."
    )
    payload = {
        "model": model,
        "temperature": 0.1,
        "response_format": {"type": "json_object"},
        "messages": [
            {"role": "system", "content": system_message},
            {"role": "user", "content": json.dumps(prompt, ensure_ascii=False)},
        ],
    }
    request = urllib.request.Request(
        MODELS_ENDPOINT,
        method="POST",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
            "X-GitHub-Api-Version": "2026-03-10",
            "User-Agent": "Burki24-Helper-Translation",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=120) as response:
            decoded = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        body = error.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"GitHub Models translation failed: HTTP {error.code}: {body}") from error

    try:
        content = decoded["choices"][0]["message"]["content"]
    except (KeyError, IndexError, TypeError) as error:
        raise RuntimeError(f"Unexpected GitHub Models response: {decoded}") from error
    if not isinstance(content, str):
        raise RuntimeError("GitHub Models returned non-text content.")
    content = content.strip()
    if content.startswith("```"):
        content = re.sub(r"^```(?:json)?\s*|\s*```$", "", content, flags=re.IGNORECASE | re.DOTALL)
    translated = json.loads(content)
    if not isinstance(translated, dict):
        raise RuntimeError("GitHub Models did not return a JSON object.")

    expected = set(texts)
    actual = set(translated)
    if expected != actual:
        raise RuntimeError(
            f"GitHub Models returned different keys. Missing={sorted(expected - actual)}, extra={sorted(actual - expected)}"
        )

    result: dict[str, str] = {}
    for key, source in texts.items():
        value = translated[key]
        if not isinstance(value, str) or not value.strip():
            raise RuntimeError(f"GitHub Models returned an empty translation for {key}.")
        if placeholders(source) != placeholders(value):
            raise RuntimeError(f"Placeholder mismatch for {key}: source={source!r}, translation={value!r}")
        result[key] = value.strip()

    return result


def synchronize_catalog(
    helper: str,
    sources: dict[str, str],
    target_language: str,
    update: bool,
) -> tuple[dict[str, Any], list[str]]:
    catalog = load_catalog(helper)
    translations = catalog.setdefault("translations", {})
    if not isinstance(translations, dict):
        raise RuntimeError(f"The translations member in {helper}.json must be an object.")

    issues: list[str] = []
    missing_for_translation: dict[str, str] = {}
    synchronized: dict[str, dict[str, str]] = {}

    for key, source in sources.items():
        current = translations.get(key)
        entry = dict(current) if isinstance(current, dict) else {}
        source_changed = entry.get("en") != source
        entry["en"] = source
        if source_changed and target_language in entry:
            entry.pop(target_language, None)
        if not isinstance(entry.get(target_language), str) or not str(entry[target_language]).strip():
            missing_for_translation[key] = source
        synchronized[key] = entry

    obsolete = sorted(set(translations) - set(sources))
    if obsolete and not update:
        issues.append(f"{helper}: obsolete translation keys: {', '.join(obsolete)}")

    if missing_for_translation:
        if update:
            generated = translate_with_github_models(missing_for_translation, target_language)
            for key, value in generated.items():
                synchronized[key][target_language] = value
        else:
            issues.append(
                f"{helper}: missing {target_language} translations: {', '.join(sorted(missing_for_translation))}"
            )

    required_languages = catalog.get("requiredLanguages", ["en", target_language])
    if not isinstance(required_languages, list) or not all(isinstance(item, str) for item in required_languages):
        issues.append(f"{helper}: requiredLanguages must be a string array.")
        required_languages = ["en", target_language]

    for key, entry in synchronized.items():
        for language in required_languages:
            value = entry.get(language)
            if not isinstance(value, str) or not value.strip():
                issues.append(f"{helper}: {key} is missing language {language}.")
                continue
            if placeholders(entry["en"]) != placeholders(value):
                issues.append(f"{helper}: placeholder mismatch in {key}/{language}.")

    catalog = {
        "schema": 1,
        "sourceLanguage": "en",
        "requiredLanguages": list(dict.fromkeys(["en", *required_languages])),
        "translations": synchronized,
    }

    return catalog, issues


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--update", action="store_true", help="Update catalogs and generate missing translations.")
    parser.add_argument("--target-language", default="de")
    args = parser.parse_args()

    TRANSLATION_DIR.mkdir(parents=True, exist_ok=True)
    all_issues: list[str] = []
    found = 0
    changed = 0

    for path in sorted(SOURCE_DIR.glob("*Helper.php")):
        sources = helper_sources(path)
        if not sources:
            continue
        found += 1
        helper = path.stem
        catalog, issues = synchronize_catalog(helper, sources, args.target_language, args.update)
        all_issues.extend(issues)
        catalog_path = TRANSLATION_DIR / f"{helper}.json"
        rendered = json.dumps(catalog, ensure_ascii=False, indent=4) + "\n"
        current = catalog_path.read_text(encoding="utf-8") if catalog_path.is_file() else ""
        if current != rendered:
            if args.update:
                catalog_path.write_text(rendered, encoding="utf-8")
                changed += 1
            else:
                all_issues.append(f"{helper}: catalog is not synchronized with its source texts.")

    if found == 0:
        raise SystemExit("No helper translation sources found.")
    if all_issues:
        for issue in all_issues:
            print(issue, file=sys.stderr)
        raise SystemExit(1)

    action = "updated" if args.update else "verified"
    print(f"Helper translations {action} ({found} catalogs, {changed} changed).")


if __name__ == "__main__":
    main()
