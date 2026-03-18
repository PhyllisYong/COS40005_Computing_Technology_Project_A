import requests
import json
try:
    import chromadb  # type: ignore[import-not-found]
    from chromadb.utils import embedding_functions  # type: ignore[import-not-found]
except Exception:
    chromadb = None
    embedding_functions = None

class LLMVerifier:
    """
    Local LLM verifier using Ollama for herbarium label verification.
    """

    def __init__(self, ollama_url="http://localhost:11434/api/generate", model_name="llama3:8b", timeout_seconds=180):
        self.OLLAMA_URL = ollama_url
        self.MODEL_NAME = model_name
        self.TIMEOUT_SECONDS = timeout_seconds
        self._rag_status_logged = False
        self._rag_reason = "not_initialized"

        # Retrieval is optional. If vector DB init fails, verifier still runs with LLM-only mode.
        self.collection = None
        try:
            if chromadb is None or embedding_functions is None:
                raise RuntimeError("chromadb is not installed")

            self.client = chromadb.PersistentClient(path="./gbif_vector_db")
            embedding_function = embedding_functions.SentenceTransformerEmbeddingFunction(
                model_name="all-mpnet-base-v2"
            )
            self.collection = self.client.get_collection(
                name="gbif_botanical_records",
                embedding_function=embedding_function,
            )
            self._rag_reason = "ok"
        except Exception as exc:
            self._rag_reason = str(exc)
            print(f"LLMVerifier retrieval disabled: {exc}")

    def rag_status(self) -> dict:
        """
        Returns a lightweight RAG health snapshot.
        """
        return {
            "running": self.collection is not None,
            "reason": self._rag_reason,
        }

    @staticmethod
    def _truncate_text(value: str, max_chars: int) -> str:
        if len(value) <= max_chars:
            return value
        return value[:max_chars] + "\n...[truncated]"

    def _build_prompt(self, ocr_text: str, ner_output: dict, retrieved_context: str, field_context: dict) -> str:
        trimmed_ocr = self._truncate_text(ocr_text, 6000)
        trimmed_retrieved = self._truncate_text(retrieved_context, 5000)

        compact_field_context = {}
        for key, docs in field_context.items():
            joined = "\n".join([str(doc) for doc in (docs or [])])
            compact_field_context[key] = self._truncate_text(joined, 1000)

        return f"""
            You are a botanist verifying structured data extracted from a herbarium label.
            Your job is to check whether each extracted field correctly matches the OCR text.

            
            Original OCR text:
            {trimmed_ocr}

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
            "genus": {{"correct": true/false, "original": "","suggestion": ""}},
            "family": {{"correct": true/false, "original": "","suggestion": ""}},
            "country": {{"correct": true/false, "original": "","suggestion": ""}},
            "region": {{"correct": true/false, "original": "","suggestion": ""}},

            "locality": {{"correct": true/false, "original": "","suggestion": ""}},
            "elevation": {{"correct": true/false, "original": "","suggestion": ""}},
            "coordinates": {{"correct": true/false, "original": "","suggestion": ""}},
            "institution": {{"correct": true/false, "original": "","suggestion": ""}}
            }},
            "confidence": 0-1
            }}
            """

    @staticmethod
    def _build_fallback_prompt(ocr_text: str, ner_output: dict) -> str:
        trimmed_ocr = ocr_text[:4000]
        return f"""
            Verify the extracted herbarium fields against OCR text and return JSON only.

            OCR:
            {trimmed_ocr}

            Extracted:
            {json.dumps(ner_output, indent=2)}

            Required schema:
            {{
              "field_validation": {{
                "species": {{"correct": true/false, "original": "", "suggestion": ""}},
                "genus": {{"correct": true/false, "original": "", "suggestion": ""}},
                "family": {{"correct": true/false, "original": "", "suggestion": ""}},
                "country": {{"correct": true/false, "original": "", "suggestion": ""}},
                "region": {{"correct": true/false, "original": "", "suggestion": ""}},
                "locality": {{"correct": true/false, "original": "", "suggestion": ""}},
                "elevation": {{"correct": true/false, "original": "", "suggestion": ""}},
                "coordinates": {{"correct": true/false, "original": "", "suggestion": ""}},
                "institution": {{"correct": true/false, "original": "", "suggestion": ""}}
              }},
              "confidence": 0-1
            }}
            """

    @staticmethod
    def _extract_json_payload(api_payload: dict) -> dict:
        """
        Parse expected Ollama /api/generate payloads and direct JSON payloads.
        """
        if not isinstance(api_payload, dict):
            return {}

        # Ollama /api/generate with stream=false returns a JSON string in "response".
        response_field = api_payload.get("response")
        if isinstance(response_field, str):
            return json.loads(response_field)
        if isinstance(response_field, dict):
            return response_field

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

    def retrieve_context(self, query_text: str, top_k: int = 5):
        if self.collection is None:
            return ""

        results = self.collection.query(
            query_texts=[query_text],
            n_results=top_k
        )

        documents = results.get("documents", [[]])[0]
        distances = results.get("distances", [[]])[0]

        # Optional: pair doc + score (VERY useful for debugging)
        context_chunks = []
        for doc, dist in zip(documents, distances):
            context_chunks.append(f"[score={dist:.4f}] {doc}")

        return "\n\n".join(context_chunks)

    def retrieve_per_field(self, ner_output: dict):
        if self.collection is None:
            return {}

        contexts = {}

        for field, value in ner_output.items():
            if value:
                results = self.collection.query(
                    query_texts=[f"{field}: {value}"],
                    n_results=2
                )
                docs = results.get("documents", [[]])[0]
                contexts[field] = docs

        return contexts

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

        # Log RAG health once so runtime clearly shows if retrieval is active.
        if not self._rag_status_logged:
            status = self.rag_status()
            print(f"RAG status: running={status['running']} reason={status['reason']}")
            self._rag_status_logged = True

        # 🔍 Build a query for retrieval
        query = ocr_text

        # 🔍 Get context from ChromaDB
        retrieved_context = self.retrieve_context(query)
        field_context = self.retrieve_per_field(ner_output)
            

        prompt = self._build_prompt(ocr_text, ner_output, retrieved_context, field_context)

        try:
            for attempt in range(2):
                current_prompt = prompt if attempt == 0 else self._build_fallback_prompt(ocr_text, ner_output)
                response = requests.post(
                    self.OLLAMA_URL,
                    json={
                        "model": self.MODEL_NAME,
                        "prompt": current_prompt,
                        "stream": False,
                        "format": "json",
                        "options": {"temperature": 0.1},
                    },
                    timeout=self.TIMEOUT_SECONDS,
                )

                if response.status_code >= 500 and attempt == 0:
                    print("LLM verification warning: server returned 5xx, retrying with fallback prompt")
                    continue

                response.raise_for_status()

                api_payload = response.json()
                result_json = self._extract_json_payload(api_payload)
                if not result_json:
                    print(f"LLM verification warning: unexpected response shape from {self.OLLAMA_URL}: {api_payload}")
                return result_json

            return {}
        except Exception as e:
            response_body = ""
            if isinstance(e, requests.HTTPError) and e.response is not None:
                response_body = e.response.text[:400]
            if response_body:
                print("LLM verification failed:", e, "body:", response_body)
            else:
                print("LLM verification failed:", e)
            return {}


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

# You may use the following reference knowledge:

#             {trimmed_retrieved}.

#             Field-specific reference knowledge:

#             Family:
#             {compact_field_context.get("family", "")}

#             Genus:
#             {compact_field_context.get("genus", "")}

#             Species:
#             {compact_field_context.get("species", "")}

#             Location:
#             {compact_field_context.get("location", "")}

#             Collector:
#             {compact_field_context.get("collector", "")}
