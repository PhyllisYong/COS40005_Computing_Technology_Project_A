from transformers import pipeline
from rapidfuzz import process, fuzz
import json
import re


class NEREngine:

    def __init__(self, model_path, taxonomy_path):

        print("Loading NER model...")

        self.ner = pipeline(
            "token-classification",
            model=model_path,
            tokenizer=model_path,
            aggregation_strategy="simple"
        )

        print("Loading taxonomy database...")

        with open(taxonomy_path, "r", encoding="utf-8") as f:
            taxonomy_list = json.load(f)

        taxonomy = {
            "genus": list(set(item["genus"] for item in taxonomy_list if item.get("genus"))),
            "species": list(set(item["species"] for item in taxonomy_list if item.get("species"))),
            "family": list(set(item["family"] for item in taxonomy_list if item.get("family")))
        }

        self.genus_list = taxonomy["genus"]
        self.species_list = taxonomy["species"]
        self.family_list = taxonomy["family"]

        # RapidFuzz threshold
        self.threshold = 85

    # =============================
    # WORDPIECE MERGE
    # =============================

    @staticmethod
    def merge_wordpieces(results):

        merged = []
        current = None

        for r in results:

            word = r["word"]
            label = r["entity_group"]

            if word.startswith("##") and current is not None:

                current["word"] += word[2:]

            else:

                if current:
                    merged.append(current)

                current = {
                    "word": word,
                    "entity_group": label
                }

        if current:
            merged.append(current)

        return merged

    # =============================
    # STRUCTURED FORMAT
    # =============================

    @staticmethod
    def herbarium_format(results):

        record = {
            "genus": None,
            "species": None,
            "family": None,
            "collector": [],
            "location": [],
            "country": None
        }

        for r in results:

            label = r["entity_group"]
            value = r["word"].strip()

            if label == "GENUS":
                record["genus"] = value

            elif label == "SPECIES":
                record["species"] = value

            elif label == "FAMILY":
                record["family"] = value

            elif label == "COLLECTOR":
                record["collector"].append(value)

            elif label == "LOCATION":
                record["location"].append(value)

            elif label == "COUNTRY":
                record["country"] = value

        return record

    # =============================
    # RAPIDFUZZ TAXONOMY MATCH
    # =============================

    def fuzzy_match(self, query, database):

        match = process.extractOne(
            query,
            database,
            scorer=fuzz.token_sort_ratio
        )

        if match is None:
            return query

        best_match, score, _ = match

        if score >= self.threshold:
            return best_match

        return query

    # =============================
    # TAXONOMY VALIDATION
    # =============================

    def validate_taxonomy(self, record):

        if record["genus"]:
            record["genus"] = self.fuzzy_match(
                record["genus"],
                self.genus_list
            )

        if record["species"]:
            record["species"] = self.fuzzy_match(
                record["species"],
                self.species_list
            )

        if record["family"]:
            record["family"] = self.fuzzy_match(
                record["family"],
                self.family_list
            )

        return record

    # =============================
    # REGEX FALLBACK RULES
    # =============================

    @staticmethod
    def extract_species(text):

        pattern = r"\b([A-Z][a-z]{3,})\s+(?:aff\.|cf\.|sp\.)?\s*([a-z\-]{3,})\b"

        matches = re.findall(pattern, text)

        if matches:
            genus, species = matches[0]
            return genus, f"{genus} {species}"

        return None, None

    # =============================
    # MAIN NER PIPELINE
    # =============================

    def run(self, text):

        ner_raw = self.ner(text)

        ner_clean = self.merge_wordpieces(ner_raw)

        record = self.herbarium_format(ner_clean)

        # taxonomy validation
        record = self.validate_taxonomy(record)

        # fallback rules if NER fails
        if not record["species"]:

            genus, species = self.extract_species(text)

            if genus:
                record["genus"] = genus

            if species:
                record["species"] = species

        return record