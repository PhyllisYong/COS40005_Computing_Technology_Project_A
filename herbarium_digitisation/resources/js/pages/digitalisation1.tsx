import React, { useEffect, useMemo, useState } from "react";
import styled, { keyframes } from "styled-components";
import { AppSidebar } from "@/components/app-sidebar";

type OcrImageResult = {
  image_id: number;
  original_filename: string;
  stored_path: string;
  preview_url?: string;
  ocr_status: string;
  llm_verified: Record<string, unknown>;
  editable_details?: FormData;
};

type OcrResultsResponse = {
  job_id: string;
  ocr_status: string;
  ocr_progress_step: string | null;
  ocr_error_message: string | null;
  images: OcrImageResult[];
};

type FormData = {
  name: string;
  scientific: string;
  location: string;
  date: string;
};

const pulse = keyframes`
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.2); opacity: 0.7; }
  100% { transform: scale(1); opacity: 1; }
`;

const PageContainer = styled.div`
  display: flex;
  background: #f3f4f6;
  min-height: 100vh;
`;

const MainContent = styled.main`
  margin-left: 16rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
`;

const ContentWrapper = styled.div`
  max-width: 88rem;
  width: 100%;
  margin: 0 auto;
  padding: 0 2.5rem 2.5rem 2.5rem;
  display: flex;
  flex-direction: column;
  flex: 1;
`;

const Header = styled.header`
  padding: 1.5rem 0 2.5rem 0;

  h1 {
    font-size: 1.875rem;
    font-weight: 800;
    color: #4a6741;
    margin: 0;
  }

  p {
    color: #6b7280;
    font-size: 0.75rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    font-weight: 700;
    margin-top: 0.25rem;
  }
`;

const StatusPanel = styled.section`
  // background: white;
  // border-radius: 1rem;
  // border: 1px solid #e5e7eb;
  // padding: 0.85rem 1rem;
  // display: flex;
  // justify-content: space-between;
  // align-items: center;
  // gap: 0.75rem;
  // flex-wrap: wrap;
  // margin-bottom: 1.5rem;
`;

const StatusBadge = styled.span<{ $tone: "ok" | "warn" | "error" }>`
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  padding: 0.4rem 0.8rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: ${({ $tone }) => ($tone === "ok" ? "#dcfce7" : $tone === "error" ? "#fee2e2" : "#fef3c7")};
  color: ${({ $tone }) => ($tone === "ok" ? "#166534" : $tone === "error" ? "#991b1b" : "#92400e")};
`;

const LayoutGrid = styled.div`
  display: flex;
  gap: 2rem;
  flex: 1;
  min-height: 0;
  padding-bottom: 2rem;
`;

const PreviewCard = styled.div`
  flex: 1;
  background: white;
  border-radius: 1.5rem;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  position: relative;
  display: flex;
`;

const ImageStage = styled.div`
  flex: 1;
  background: #fdfcf9;
  border: 1px solid #f9f9f7;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
  border-radius: 1.25rem;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
`;

const PlaceholderText = styled.p`
  color: #9ca3af;
  font-weight: 800;
  font-size: 1.5rem;
  text-transform: uppercase;
  opacity: 0.2;
`;

const StageImage = styled.img`
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 0.75rem;
`;

const ValidationOverlay = styled.div`
  position: absolute;
  bottom: 1.5rem;
  right: 1.5rem;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border: 1px solid #f3f4f6;
  padding: 1rem;
  border-radius: 1.25rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  min-width: 160px;
  z-index: 10;
  text-align: right;
`;

const StatusText = styled.div<{ $tone: "ok" | "warn" | "error" }>`
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  color: #4b5563;
  margin-top: 0.3rem;

  .dot {
    width: 8px;
    height: 8px;
    background: ${({ $tone }) => ($tone === "ok" ? "#22c55e" : $tone === "error" ? "#ef4444" : "#f59e0b")};
    border-radius: 50%;
    box-shadow: 0 0 8px ${({ $tone }) => ($tone === "ok" ? "#22c55e" : $tone === "error" ? "#ef4444" : "#f59e0b")};
    animation: ${pulse} 2s infinite ease-in-out;
  }

  .highlight {
    color: ${({ $tone }) => ($tone === "ok" ? "#16a34a" : $tone === "error" ? "#dc2626" : "#b45309")};
  }
`;

const DetailsSidebar = styled.div`
  width: 400px;
  background: white;
  border-radius: 1.5rem;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  padding: 2rem;
  display: flex;
  flex-direction: column;
`;

const DetailsTitle = styled.h2`
  text-align: center;
  font-size: 0.7rem;
  font-weight: 800;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.3em;
  margin-bottom: 1.5rem;
`;

const SelectWrap = styled.div`
  margin-bottom: 1rem;

  label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
  }

  select {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 0.6rem;
    padding: 0.5rem 0.65rem;
    font-size: 0.85rem;
    color: #374151;
    background: #fff;
  }
`;

const Form = styled.form`
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 1.5rem;
`;

const InputGroup = styled.div`
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0.5rem;

  label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
  }

  input {
    width: 100%;
    border: none;
    outline: none;
    padding: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #374151;
    background: transparent;

    &::placeholder {
      color: #e5e7eb;
    }

    &.italic {
      font-style: italic;
    }
  }
`;

const SaveButton = styled.button`
  width: 100%;
  background: #5d7356;
  color: white;
  padding: 1rem;
  border-radius: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 0.8rem;
  border: none;
  cursor: pointer;
  box-shadow: 0 10px 15px -3px rgba(93, 115, 86, 0.3);
  margin-top: auto;
  transition: all 0.2s;

  &:hover {
    background: #4a6741;
    transform: translateY(-2px);
    box-shadow: 0 12px 20px -5px rgba(74, 103, 65, 0.4);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
  }
`;

const ErrorText = styled.p`
  margin: 0;
  color: #b91c1c;
  font-size: 0.8rem;
`;

const SaveMessage = styled.p<{ $tone: "success" | "error" }>`
  margin: 0 0 0.75rem 0;
  font-size: 0.8rem;
  font-weight: 600;
  color: ${({ $tone }) => ($tone === "success" ? "#166534" : "#b91c1c")};
`;

function toneForStatus(status: string): "ok" | "warn" | "error" {
  if (status === "completed") return "ok";
  if (status === "failed") return "error";
  return "warn";
}

function firstString(source: Record<string, unknown>, keys: string[]): string {
  for (const key of keys) {
    const value = source[key];
    if (value === null || value === undefined) continue;
    if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") {
      const output = String(value).trim();
      if (output.length > 0) {
        return output;
      }
    }
  }
  return "";
}

function mapVerifiedToForm(fields: Record<string, unknown>): FormData {
  return {
    name: firstString(fields, ["specimen_name", "collector_name", "name"]),
    scientific: firstString(fields, ["scientific_name", "taxon", "species"]),
    location: firstString(fields, ["location", "locality", "country", "state"]),
    date: firstString(fields, ["date_collected", "event_date", "date"]),
  };
}

function normalizeEditableDetails(payload: unknown): FormData {
  const asRecord = (payload && typeof payload === "object") ? payload as Record<string, unknown> : {};
  return {
    name: firstString(asRecord, ["name"]),
    scientific: firstString(asRecord, ["scientific"]),
    location: firstString(asRecord, ["location"]),
    date: firstString(asRecord, ["date"]),
  };
}

export default function Digitalisation1() {
  const jobId = useMemo(() => {
    if (typeof window === "undefined") {
      return null;
    }

    const params = new URLSearchParams(window.location.search);
    return params.get("job_id");
  }, []);

  const [data, setData] = useState<OcrResultsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedImageId, setSelectedImageId] = useState<number | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveSuccess, setSaveSuccess] = useState<string | null>(null);
  const [formData, setFormData] = useState<FormData>({
    name: "",
    scientific: "",
    location: "",
    date: "",
  });
  const csrfToken = useMemo(
    () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? "",
    []
  );

  useEffect(() => {
    if (!jobId) {
      setLoading(false);
      setError("Missing job id. Return to Digitisation and submit a validated batch.");
      return;
    }

    let cancelled = false;
    let timerId: number | null = null;

    const fetchResults = async (): Promise<string | null> => {
      try {
        const response = await fetch(`/api/digitisation/jobs/${encodeURIComponent(jobId)}/ocr-results`, {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          credentials: "same-origin",
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(payload.message || "Failed to load OCR results.");
        }

        if (cancelled) {
          return null;
        }

        const typedPayload = payload as OcrResultsResponse;
        setData(typedPayload);
        setError(null);
        return typedPayload.ocr_status;
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : "Failed to load OCR results.");
        }
        return null;
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    const poll = async () => {
      const status = await fetchResults();

      if (cancelled) {
        return;
      }

      const shouldContinue = !status || (status !== "completed" && status !== "failed");

      if (shouldContinue) {
        timerId = window.setTimeout(() => {
          void poll();
        }, 4000);
      }
    };

    void poll();

    return () => {
      cancelled = true;
      if (timerId !== null) {
        window.clearTimeout(timerId);
      }
    };
  }, [jobId]);

  const cards = data?.images ?? [];
  const statusText = data?.ocr_status ?? "pending";

  useEffect(() => {
    if (cards.length === 0) {
      setSelectedImageId(null);
      return;
    }

    if (!cards.some((card) => card.image_id === selectedImageId)) {
      setSelectedImageId(cards[0].image_id);
    }
  }, [cards, selectedImageId]);

  const selectedCard = cards.find((card) => card.image_id === selectedImageId) ?? null;

  useEffect(() => {
    if (!selectedCard) {
      setFormData({ name: "", scientific: "", location: "", date: "" });
      setSaveError(null);
      setSaveSuccess(null);
      return;
    }

    setFormData(
      selectedCard.editable_details
        ? normalizeEditableDetails(selectedCard.editable_details)
        : mapVerifiedToForm(selectedCard.llm_verified ?? {})
    );
    setSaveError(null);
    setSaveSuccess(null);
  }, [selectedCard]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handleSave = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    if (!jobId || !selectedCard) {
      setSaveError("No image selected.");
      return;
    }

    setIsSaving(true);
    setSaveError(null);
    setSaveSuccess(null);

    try {
      const response = await fetch(
        `/api/digitisation/jobs/${encodeURIComponent(jobId)}/images/${selectedCard.image_id}/details`,
        {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": csrfToken,
          },
          credentials: "same-origin",
          body: JSON.stringify(formData),
        }
      );

      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.message || "Failed to save image details.");
      }

      const nextEditable = normalizeEditableDetails(payload.editable_details);
      const nextLlmVerified = (payload.llm_verified && typeof payload.llm_verified === "object")
        ? payload.llm_verified as Record<string, unknown>
        : selectedCard.llm_verified;

      setData((prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          images: prev.images.map((img) =>
            img.image_id === selectedCard.image_id
              ? {
                  ...img,
                  editable_details: nextEditable,
                  llm_verified: nextLlmVerified,
                }
              : img
          ),
        };
      });

      setFormData(nextEditable);
      setSaveSuccess(payload.message || "Image details saved.");
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : "Failed to save image details.");
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <PageContainer>
      <AppSidebar />

      <MainContent>
        <ContentWrapper>
          <Header>
            <h1>Plant Details</h1>
            <p>Analyze and Save Specimen Data</p>
          </Header>

          <StatusPanel>
            <div>
              <strong>Job:</strong> {jobId ?? "-"}
            </div>
            <StatusBadge $tone={toneForStatus(statusText)}>
              OCR: {statusText}
            </StatusBadge>
            <div>{data?.ocr_progress_step ? `Step: ${data.ocr_progress_step}` : "Waiting for OCR callback..."}</div>
          </StatusPanel>

          {error ? <ErrorText>{error}</ErrorText> : null}
          {!error && data?.ocr_error_message ? <ErrorText>{data.ocr_error_message}</ErrorText> : null}

          <LayoutGrid>
            <PreviewCard>
              <ImageStage>
                {selectedCard?.preview_url ? (
                  <StageImage
                    src={selectedCard.preview_url}
                    alt={selectedCard.original_filename}
                  />
                ) : selectedCard ? (
                  <PlaceholderText>{selectedCard.original_filename}</PlaceholderText>
                ) : loading ? (
                  <PlaceholderText>Loading OCR Results</PlaceholderText>
                ) : (
                  <PlaceholderText>No Image Selected</PlaceholderText>
                )}
              </ImageStage>

              <ValidationOverlay>
                <p
                  style={{
                    fontSize: "9px",
                    color: "#9ca3af",
                    fontWeight: 800,
                    textTransform: "uppercase",
                    letterSpacing: "0.1em",
                    marginBottom: "4px",
                    borderBottom: "1px solid #f3f4f6",
                    paddingBottom: "2px",
                  }}
                >
                  OCR Validation
                </p>
                <StatusText $tone={toneForStatus(selectedCard?.ocr_status ?? statusText)}>
                  Status: <span className="highlight">{selectedCard?.ocr_status ?? statusText}</span>
                  <span className="dot" />
                </StatusText>
              </ValidationOverlay>

            </PreviewCard>

            <DetailsSidebar>
              <DetailsTitle>Details</DetailsTitle>

              <Form onSubmit={handleSave}>
                <div style={{ flex: 1, display: "flex", flexDirection: "column", gap: "1.5rem" }}>
                  {saveSuccess ? <SaveMessage $tone="success">{saveSuccess}</SaveMessage> : null}
                  {saveError ? <SaveMessage $tone="error">{saveError}</SaveMessage> : null}

                  <SelectWrap>
                    <label>Image</label>
                    <select
                      value={selectedImageId ?? ""}
                      onChange={(e) => setSelectedImageId(Number(e.target.value))}
                      disabled={cards.length === 0}
                    >
                      {cards.length === 0 ? <option value="">No OCR images yet</option> : null}
                      {cards.map((card) => (
                        <option key={card.image_id} value={card.image_id}>
                          {card.original_filename}
                        </option>
                      ))}
                    </select>
                  </SelectWrap>

                  <InputGroup>
                    <label>Specimen Name</label>
                    <input
                      type="text"
                      name="name"
                      placeholder="Enter name..."
                      value={formData.name}
                      onChange={handleInputChange}
                    />
                  </InputGroup>

                  <InputGroup>
                    <label>Scientific Name</label>
                    <input
                      type="text"
                      name="scientific"
                      className="italic"
                      placeholder="Genus species..."
                      value={formData.scientific}
                      onChange={handleInputChange}
                    />
                  </InputGroup>

                  <InputGroup>
                    <label>Location Found</label>
                    <input
                      type="text"
                      name="location"
                      placeholder="GPS or Site Name..."
                      value={formData.location}
                      onChange={handleInputChange}
                    />
                  </InputGroup>

                  <InputGroup>
                    <label>Date Collected</label>
                    <input
                      type="text"
                      name="date"
                      placeholder="YYYY-MM-DD"
                      value={formData.date}
                      onChange={handleInputChange}
                    />
                  </InputGroup>
                </div>

                <SaveButton type="submit" disabled={!selectedCard || isSaving}>
                  {isSaving ? "Saving..." : "Edit & Save"}
                </SaveButton>
              </Form>
            </DetailsSidebar>
          </LayoutGrid>
        </ContentWrapper>
      </MainContent>
    </PageContainer>
  );
}
