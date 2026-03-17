import requests
import json
import chromadb
from chromadb.utils import embedding_functions

class LLMVerifier:
    """
    Local LLM verifier using Ollama for herbarium label verification.
    """

    def __init__(self, ollama_url="http://localhost:11434/api/generate", model_name="llama3:8b", timeout_seconds=180):
        self.OLLAMA_URL = ollama_url
        self.MODEL_NAME = model_name
        self.TIMEOUT_SECONDS = timeout_seconds

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

        
        self.client = chromadb.PersistentClient(path="./gbif_vector_db")

        
        embedding_function = embedding_functions.SentenceTransformerEmbeddingFunction(
            model_name="all-mpnet-base-v2"
        )
        self.collection = self.client.get_collection(name="gbif_botanical_records", embedding_function=embedding_function)

    def retrieve_context(self, query_text: str, top_k: int = 5):
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

            # 🔍 Build a query for retrieval
        query = ocr_text

        # 🔍 Get context from ChromaDB
        retrieved_context = self.retrieve_context(query)
        field_context = self.retrieve_per_field(ner_output)
            

        prompt = f"""
            You are a botanist verifying structured data extracted from a herbarium label.
            Your job is to check whether each extracted field correctly matches the OCR text.

            You may use the following reference knowledge:

            {retrieved_context}.

            Field-specific reference knowledge:

            Family:
            {field_context.get("family", [])}

            Genus:
            {field_context.get("genus", [])}

            Species:
            {field_context.get("species", [])}

            Location:
            {field_context.get("location", [])}

            Collector:
            {field_context.get("collector", [])}

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

        try:
            response = requests.post(
                self.OLLAMA_URL,
                json={
                    "model": self.MODEL_NAME,
                    "prompt": prompt,
                    "stream": False,
                    "format": "json",
                    "options": {"temperature": 0.1},
                },
                timeout=self.TIMEOUT_SECONDS,
            )
            response.raise_for_status()

            api_payload = response.json()
            result_json = self._extract_json_payload(api_payload)
            if not result_json:
                print(f"LLM verification warning: unexpected response shape from {self.OLLAMA_URL}: {api_payload}")
            return result_json
        except Exception as e:
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