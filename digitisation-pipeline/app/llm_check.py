import requests
import json

class LLMVerifier:
    """
    Local LLM verifier using Ollama for herbarium label verification.
    """

    def __init__(self, ollama_url="http://localhost:11434/api/generate", model_name="llama3"):
        self.OLLAMA_URL = ollama_url
        self.MODEL_NAME = model_name

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

        response = requests.post(
            self.OLLAMA_URL,
            json={
                "model": self.MODEL_NAME,
                "prompt": prompt,
                "stream": False,
                "format": "json",
                "options": {"temperature": 0.1}
            }
        )

        try:
            # Ollama returns a "response" string
            result_json = json.loads(response.json()["response"])
        except Exception as e:
            print("LLM verification failed:", e)
            result_json = {}

        return result_json


# # Example usage (for testing)
# if __name__ == "__main__":

#     ocr_text = """
# HERBARIUM OF IOWA STATE UNIVERSITY
# BRAZIL

# Chusquea all. baculifera da Silveira

# Minas Gerais; Mun. Lima Duarte
# Serra do Ibitipoca
# Paredao do Rio Salto

# 1300 m
# 21°42'S 43°52'W
# """

    # ner_output = {
    #     "species": "Chusquea all. baculifera da Silveira",
    #     "country": "Brazil",
    #     "region": "",
    #     "municipality": "Lima Duarte",
    #     "locality": "Serra do Ibitioc",
    #     "elevation": "21°42'S 43°52'W",
    #     "coordinates": "1300 m"
    # }

    # verifier = LLMVerifier()
    # result = verifier.verify(ocr_text, ner_output)
    # print(json.dumps(result, indent=2))