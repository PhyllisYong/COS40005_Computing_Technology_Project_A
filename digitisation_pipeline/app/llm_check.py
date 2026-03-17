import requests
import json
from urllib.parse import urlparse

class LLMVerifier:
    """
    Local LLM verifier using Ollama for herbarium label verification.
    """

    def __init__(self, ollama_url="http://localhost:11434/api/generate", model_name="llama3"):
        self.OLLAMA_URL = ollama_url
        self.MODEL_NAME = model_name

    @staticmethod
    def _base_url(configured_url: str) -> str:
        parsed = urlparse(configured_url)
        return f"{parsed.scheme}://{parsed.netloc}" if parsed.scheme and parsed.netloc else configured_url.rstrip("/")

    def _installed_models(self) -> list[str]:
        tags_url = f"{self._base_url(self.OLLAMA_URL)}/api/tags"
        try:
            response = requests.get(tags_url, timeout=10)
            response.raise_for_status()
            payload = response.json()
            models = payload.get("models", []) if isinstance(payload, dict) else []
            names = []
            for model in models:
                if isinstance(model, dict) and isinstance(model.get("name"), str):
                    names.append(model["name"])
            return names
        except Exception:
            return []

    def _candidate_models(self) -> list[str]:
        candidates = [self.MODEL_NAME]
        installed = self._installed_models()

        # Common case: requested "llama3" while installed model is "llama3:8b".
        prefix_matches = [m for m in installed if m == self.MODEL_NAME or m.startswith(f"{self.MODEL_NAME}:")]
        for model in prefix_matches:
            if model not in candidates:
                candidates.append(model)

        if installed and installed[0] not in candidates:
            candidates.append(installed[0])

        return candidates

    def _detect_api_mode(self) -> str:
        base = self._base_url(self.OLLAMA_URL)

        try:
            resp = requests.get(f"{base}/api/tags", timeout=5)
            if resp.status_code == 200:
                return "ollama"
        except Exception:
            pass

        try:
            resp = requests.get(f"{base}/v1/models", timeout=5)
            if resp.status_code == 200:
                return "openai"
        except Exception:
            pass

        return "unknown"

    @staticmethod
    def _candidate_urls(configured_url: str, api_mode: str = "unknown") -> list[str]:
        base = LLMVerifier._base_url(configured_url)

        candidates = [configured_url]
        if api_mode == "ollama":
            paths = ["/api/chat", "/api/generate"]
        elif api_mode == "openai":
            paths = ["/v1/chat/completions"]
        else:
            paths = ["/api/chat", "/api/generate", "/v1/chat/completions"]

        for path in paths:
            candidate = f"{base}{path}"
            if candidate not in candidates:
                candidates.append(candidate)
        return candidates

    def _request_payload(self, url: str, prompt: str, model_name: str) -> dict:
        if url.endswith("/api/chat"):
            return {
                "model": model_name,
                "messages": [{"role": "user", "content": prompt}],
                "stream": False,
                "format": "json",
                "options": {"temperature": 0.1},
            }

        if url.endswith("/v1/chat/completions"):
            return {
                "model": model_name,
                "messages": [{"role": "user", "content": prompt}],
                "temperature": 0.1,
                "response_format": {"type": "json_object"},
            }

        return {
            "model": model_name,
            "prompt": prompt,
            "stream": False,
            "format": "json",
            "options": {"temperature": 0.1},
        }

    @staticmethod
    def _extract_json_payload(api_payload: dict) -> dict:
        """
        Support both generate-style payloads ({"response": "...json..."})
        and endpoints that already return the validated JSON object directly.
        """
        if not isinstance(api_payload, dict):
            return {}

        # Ollama /api/generate with stream=false returns a JSON string in "response".
        response_field = api_payload.get("response")
        if isinstance(response_field, str):
            return json.loads(response_field)
        if isinstance(response_field, dict):
            return response_field

        # Ollama /api/chat with stream=false usually returns "message": {"content": "...json..."}
        message_field = api_payload.get("message")
        if isinstance(message_field, dict):
            content = message_field.get("content")
            if isinstance(content, str):
                return json.loads(content)

        # OpenAI-compatible chat returns choices[0].message.content
        choices = api_payload.get("choices")
        if isinstance(choices, list) and choices:
            first = choices[0] if isinstance(choices[0], dict) else {}
            message = first.get("message") if isinstance(first, dict) else {}
            content = message.get("content") if isinstance(message, dict) else None
            if isinstance(content, str):
                return json.loads(content)

        # Some adapters expose a direct JSON object instead of a nested response string.
        if "field_validation" in api_payload or "confidence" in api_payload:
            return api_payload

        return {}

    def verify(self, ocr_text: str, ner_output: dict) -> dict:
        """
        Sends OCR text and NER-extracted labels to the local LLM
        and returns a validation/correction JSON.

        Parameters:
        - ocr_text: str, the raw OCR text from the image
        - ner_output: dict, structured labels extracted by NER

        Returns:
        - dict: field_validation results with corrections
        """

        prompt = f"""
            You are a botanist verifying structured data extracted from a herbarium label.

            Your job is to check whether each extracted field correctly matches the OCR text.

            Original OCR text:
            {ocr_text}

            Extracted data:
            {json.dumps(ner_output, indent=2)}

            For EACH field:

            1. Check if the value appears in the OCR text.
            2. Determine if the label type is correct (species, location, coordinates, etc).
            3. Detect OCR mistakes.
            4. Suggest corrections ONLY if necessary.

            IMPORTANT RULES:
            - Do NOT invent information.
            - Do NOT swap fields unless clearly incorrect.
            - Coordinates must contain degrees (°).
            - Elevation must contain "m" or "meters".
            - Species must follow botanical binomial format.

            Return JSON ONLY in this format:

            {{
            "field_validation": {{
            "species": {{"correct": true/false, "original": "","suggestion": ""}},
            "country": {{"correct": true/false, "original": "","suggestion": ""}},
            "region": {{"correct": true/false, "original": "","suggestion": ""}},
            "municipality": {{"correct": true/false, "original": "","suggestion": ""}},
            "locality": {{"correct": true/false, "original": "","suggestion": ""}},
            "elevation": {{"correct": true/false, "original": "","suggestion": ""}},
            "coordinates": {{"correct": true/false, "original": "","suggestion": ""}},
            "institution": {{"correct": true/false, "original": "","suggestion": ""}}
            }},
            "confidence": 0-1
            }}
            """

        last_error = None
        result_json = {}
        api_mode = self._detect_api_mode()

        for model_name in self._candidate_models():
            for candidate_url in self._candidate_urls(self.OLLAMA_URL, api_mode):
                try:
                    response = requests.post(
                        candidate_url,
                        json=self._request_payload(candidate_url, prompt, model_name),
                        timeout=45,
                    )

                    if response.status_code == 404:
                        body = ""
                        try:
                            body = response.text[:200]
                        except Exception:
                            pass
                        last_error = f"404 at {candidate_url} with model '{model_name}' {body}"
                        continue

                    response.raise_for_status()
                    api_payload = response.json()
                    result_json = self._extract_json_payload(api_payload)

                    if result_json:
                        return result_json

                    last_error = f"Unexpected response shape from {candidate_url}: {api_payload}"
                except Exception as exc:
                    last_error = str(exc)

        print("LLM verification failed:", last_error)

        return result_json


# Example usage (for testing)
# if __name__ == "__main__":

#     ocr_text = """
#         HERBARIUM OF IOWA STATE UNIVERSITY
#         BRAZIL

#         Chusquea all. baculifera da Silveira

#         Minas Gerais; Mun. Lima Duarte
#         Serra do Ibitipoca
#         Paredao do Rio Salto

#         1300 m
#         21°42'S 43°52'W
#         """

#     ner_output = {
#         "species": "Chusquea all. baculifera da Silveira",
#         "country": "Brazil",
#         "region": "",
#         "municipality": "Lima Duarte",
#         "locality": "Serra do Ibitioc",
#         "elevation": "21°42'S 43°52'W",
#         "coordinates": "1300 m"
#     }

#     verifier = LLMVerifier()
#     result = verifier.verify(ocr_text, ner_output)
#     print(json.dumps(result, indent=2))