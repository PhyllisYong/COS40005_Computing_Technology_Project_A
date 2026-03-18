import React, { useEffect, useMemo, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import styled, { keyframes } from "styled-components";
import {AppSidebar} from '@/components/app-sidebar'; 

const pulse = keyframes`
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
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
  height: 100vh;
  overflow-y: auto;
`;

const ContentWrapper = styled.div`
  max-width: 80rem;
  width: 100%;
  margin: 0 auto;
  padding: 0 2.5rem 2.5rem 2.5rem;
  display: flex;
  flex-direction: column;
  flex: 1;
`;

const Header = styled.header`
  padding: 1.5rem 0 2rem 0;

  h1 {
    font-size: 1.875rem;
    font-weight: 800;
    color: #4a6741;
    margin: 0;
  }

  p {
    color: #9ca3af;
    font-size: 0.75rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    font-weight: 700;
    margin-top: 0.25rem;
  }
`;

const LayoutGrid = styled.div`
  display: flex;
  gap: 2rem;
  flex: 1;
  min-height: 0;
  padding-bottom: 2rem;
`;

const GlassCard = styled.div`
  background: white;
  border-radius: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  border: 1px solid #f3f4f6;
  display: flex;
  flex-direction: column;
`;

const LeftColumn = styled(GlassCard)`
  flex: 1;
  padding: 2rem;
`;

const RightColumn = styled(GlassCard)`
  flex: 1.2;
  padding: 1.5rem;
`;

const SectionTitle = styled.h2`
  font-size: 0.7rem;
  font-weight: 800;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
`;

const DropzoneLabel = styled.label`
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2px dashed #e5e7eb;
  border-radius: 1.5rem;
  background: white;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-bottom: 1.5rem;

  &:hover {
    background: #f9fafb;
    border-color: #4a6741;
  }

  span.icon {
    font-size: 3rem;
    margin-bottom: 1rem;
  }

  span.text {
    color: #9ca3af;
    font-size: 0.875rem;
    font-weight: 600;
  }
`;

const ThumbnailRow = styled.div`
  display: flex;
  gap: 1rem;
  margin: 1rem 0 1.5rem 0;
  flex-wrap: wrap;
`;

const ThumbBox = styled.div<{ $isActive:boolean }>`
  width: 3.5rem;
  height: 3.5rem;
  background: #f9f9f7;
  border-radius: 0.75rem;
  border: 2px solid ${props => props.$isActive ? "#4a6741" : "transparent"};
  cursor: pointer;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;

  &:hover {
    border-color: ${props => props.$isActive ? "#4a6741" : "#d1d5db"};
  }

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
`;

const ThumbNumber = styled.span`
  font-size: 10px;
  font-weight: 800;
  color: #d1d5db;
`;

const PreviewContainer = styled.div`
  flex: 1;
  position: relative;
  background: #fdfcf9;
  border-radius: 1rem;
  border: 1px solid #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  margin-bottom: 1.5rem;
`;

const PreviewImage = styled.img`
  max-height: 100%;
  max-width: 100%;
  object-fit: contain;
`;

const EmptyPreviewText = styled.span`
  font-size: 1.5rem;
  font-weight: 900;
  color: #eee;
  text-transform: uppercase;
`;

const ImageLabel = styled.div`
  position: absolute;
  bottom: 1rem;
  left: 1rem;
  background: #f8f5f0;
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  border: 1px solid #eee;
  font-size: 9px;
  font-weight: 800;
  color: #666;
  text-transform: uppercase;
`;

const ValidationBadge = styled.div`
  position: absolute;
  bottom: 1rem;
  right: 1rem;
  background: white;
  padding: 1rem;
  border-radius: 0.75rem;
  border: 1px solid #f3f4f6;
  box-shadow: 0 10px 15px rgba(0,0,0,0.1);
  min-width: 150px;
`;

const ValidationTitle = styled.div`
  font-size: 9px;
  font-weight: 800;
  color: #9ca3af;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
  border-bottom: 1px solid #f3f4f6;
  padding-bottom: 0.2rem;
`;

const StatusText = styled.div<{ $tone: 'neutral' | 'success' | 'danger' }>`
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #4b5563;

  .dot {
    width: 8px;
    height: 8px;
    background: ${props => props.$tone === 'success' ? '#10b981' : props.$tone === 'danger' ? '#ef4444' : '#9ca3af'};
    border-radius: 50%;
    animation: ${pulse} 2s infinite;
  }

  .highlight {
    color: ${props => props.$tone === 'success' ? '#059669' : props.$tone === 'danger' ? '#dc2626' : '#6b7280'};
    margin-left: auto;
  }
`;

const NextButton = styled.button`
  width: 100%;
  background: #5d7356;
  color: white;
  padding: 1.1rem;
  border-radius: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  font-size: 0.75rem;
  border: none;
  cursor: pointer;

  &:hover:not(:disabled) {
    background: #4a6741;
  }

  &:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }
`;

const RunNameInput = styled.input`
  width: 100%;
  padding: 0.65rem 0.9rem;
  border-radius: 0.6rem;
  border: 1.5px solid #e5e7eb;
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
  outline: none;
  margin-bottom: 0.75rem;
  box-sizing: border-box;

  &::placeholder { color: #d1d5db; }
  &:focus { border-color: #4a6741; }
`;

const StatusBanner = styled.div<{ $type: 'success' | 'error' }>`
  margin-top: 0.75rem;
  padding: 0.65rem 0.9rem;
  border-radius: 0.6rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: ${p => p.$type === 'success' ? '#065f46' : '#991b1b'};
  background: ${p => p.$type === 'success' ? '#d1fae5' : '#fee2e2'};
`;

const AddThumbButton = styled(ThumbBox)`
  border: 2px dashed #d1d5db;
  color: #9ca3af;
  font-size: 1.5rem;
  font-weight: 400;
  &:hover { 
    border-color: #4a6741; 
    color: #4a6741; 
  }
`;

const DeleteButton = styled.button`
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  width: 2rem;
  height: 2rem;
  background: white;
  color: #666;
  border: 1px solid #eee;
  border-radius: 50%; /* Makes it a circle */
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  line-height: 1;
  cursor: pointer;
  z-index: 10;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  transition: all 0.2s;

  &:hover {
    background: #fee2e2; /* Light red background on hover */
    color: #991b1b;       /* Red 'X' on hover */
    border-color: #fecaca;
  }
`;

export default function Digitalisation() {

  type SlotIqcState = 'idle' | 'queued' | 'running' | 'accepted' | 'rejected';

  type JobImageResult = {
    original_filename?: string | null;
    iqc_status?: string | null;
    iqc_decision?: string | null;
    iqc_reasons?: Array<{ code?: string | null; message?: string | null }> | null;
  };

  const [images, setImages] = useState<(string | null)[]>([null]);
  const [files,  setFiles]  = useState<(File | null)[]>([null]);
  const [activeIndex, setActiveIndex] = useState(0);
  const [runName, setRunName] = useState('');
  const [statusMsg, setStatusMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [slotStates, setSlotStates] = useState<Record<number, SlotIqcState>>({});
  const [slotFailureStages, setSlotFailureStages] = useState<Record<number, string>>({});
  const [currentJobId, setCurrentJobId] = useState<string | null>(null);
  const [isValidating, setIsValidating] = useState(false);
  const [isSubmittingBatch, setIsSubmittingBatch] = useState(false);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const pollIntervalRef = useRef<number | null>(null);

  const csrfToken = useMemo(
    () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    []
  );

  const stopPolling = () => {
    if (pollIntervalRef.current !== null) {
      const intervalId = pollIntervalRef.current;
      window.clearInterval(intervalId);
      pollIntervalRef.current = null;
    }
  };

  useEffect(() => {
    return () => stopPolling();
  }, []);

  const resetIqcState = () => {
    stopPolling();
    setSlotStates({});
    setSlotFailureStages({});
    setCurrentJobId(null);
    setIsValidating(false);
    setIsSubmittingBatch(false);
  };

  const failureStageFromReasons = (reasons?: JobImageResult['iqc_reasons']) => {
    const codes = (Array.isArray(reasons) ? reasons : [])
      .map((reason) => (reason?.code ?? '').toLowerCase());

    if (codes.includes('decode_failed')) return 'decode check';
    if (codes.includes('resolution_hard_fail')) return 'resolution check';
    if (codes.includes('blur_detected')) return 'blur check';
    if (codes.includes('brisque_threshold_fail')) return 'BRISQUE check';
    if (codes.includes('brisque_failed') || codes.includes('brisque_unavailable')) return 'BRISQUE scoring';

    return 'quality checks';
  };

  const badgeLabelForFailureStage = (stage?: string) => {
    if (!stage) return 'Rejected';
    if (stage === 'resolution check') return 'Resolution Fail';
    if (stage === 'blur check') return 'Blur Fail';
    if (stage === 'BRISQUE check') return 'BRISQUE Fail';
    if (stage === 'BRISQUE scoring') return 'BRISQUE Error';
    if (stage === 'decode check') return 'Decode Fail';

    return 'Rejected';
  };

  const labelForState = (state: SlotIqcState) => {
    if (state === 'accepted') return { text: 'Clear', tone: 'success' as const };
    if (state === 'rejected') return { text: badgeLabelForFailureStage(slotFailureStages[activeIndex]), tone: 'danger' as const };
    if (state === 'running' || state === 'queued') return { text: 'Checking', tone: 'neutral' as const };
    return { text: files[activeIndex] ? 'Waiting' : 'No Image', tone: 'neutral' as const };
  };

  const pollIqcStatus = (jobId: string, activeSlots: number[]) => {
    const poll = async () => {
      try {
        const response = await fetch(`/api/digitisation/jobs/${encodeURIComponent(jobId)}/status`, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(payload.message || 'Failed to retrieve quality-check status.');
        }

        const latestIqcStatus = ((payload.iqc_status as string | null) ?? '').toLowerCase();
        const imageResults = Array.isArray(payload.images) ? payload.images as JobImageResult[] : [];
        const nextStates: Record<number, SlotIqcState> = {};
        const nextFailureStages: Record<number, string> = {};

        activeSlots.forEach((slotIndex, imageIndex) => {
          const imageResult = imageResults[imageIndex];
          const decision = (imageResult?.iqc_decision ?? '').toLowerCase();

          if (decision === 'accept') {
            nextStates[slotIndex] = 'accepted';
          } else if (decision === 'reject') {
            nextStates[slotIndex] = 'rejected';
            nextFailureStages[slotIndex] = failureStageFromReasons(imageResult?.iqc_reasons);
          } else if (latestIqcStatus === 'running' || latestIqcStatus === 'dispatching' || latestIqcStatus === 'queued' || latestIqcStatus === 'pending') {
            nextStates[slotIndex] = 'running';
          } else {
            nextStates[slotIndex] = 'queued';
          }
        });

        setSlotStates(nextStates);
        setSlotFailureStages(nextFailureStages);

        if (latestIqcStatus === 'completed' || latestIqcStatus === 'failed') {
          stopPolling();
          setIsValidating(false);

          const rejectedDetails = imageResults
            .map((img, index) => {
              if ((img.iqc_decision ?? '').toLowerCase() !== 'reject') return null;

              const name = (typeof img.original_filename === 'string' && img.original_filename !== '')
                ? img.original_filename
                : `Image ${index + 1}`;
              const stage = failureStageFromReasons(img.iqc_reasons);

              return `${name} (failed at ${stage})`;
            })
            .filter((value): value is string => value !== null);

          const accepted = imageResults.filter((img) => (img.iqc_decision ?? '').toLowerCase() === 'accept').length;

          if (accepted > 0) {
            if (rejectedDetails.length > 0) {
              setStatusMsg({
                type: 'error',
                text: `Rejected images: ${rejectedDetails.join(', ')}. Accepted images are ready for batch submission.`,
              });
            } else {
              setStatusMsg({
                type: 'success',
                text: 'All images passed quality validation. Press Next Step to submit the batch.',
              });
            }
          } else {
            setStatusMsg({
              type: 'error',
              text: rejectedDetails.length > 0
                ? `Rejected images: ${rejectedDetails.join(', ')}.`
                : (payload.error_message || 'All uploaded images were rejected. Please reupload clearer images.'),
            });
          }
        }
      } catch (error) {
        stopPolling();
        setIsValidating(false);
        setStatusMsg({
          type: 'error',
          text: error instanceof Error ? error.message : 'Unable to track quality check status.',
        });
      }
    };

    void poll();
    stopPolling();
    pollIntervalRef.current = window.setInterval(() => {
      void poll();
    }, 2000);
  };

  const submitForIqc = async (nextFiles: (File | null)[]) => {
    const uploadEntries = nextFiles
      .map((file, index) => ({ file, index }))
      .filter((entry): entry is { file: File; index: number } => entry.file !== null);

    if (uploadEntries.length === 0) {
      resetIqcState();
      setStatusMsg({ type: 'error', text: 'Please upload at least one image.' });
      return;
    }

    const activeSlots = uploadEntries.map(entry => entry.index);
    const name = runName.trim() || `Run ${new Date().toISOString().slice(0, 19).replace('T', ' ')}`;
    const data = new FormData();
    data.append('run_name', name);
    uploadEntries.forEach(({ file }) => data.append('files[]', file));

    stopPolling();
    setCurrentJobId(null);
    setIsValidating(true);
    setStatusMsg({ type: 'success', text: `Uploaded ${uploadEntries.length} image(s). Running quality check...` });
    setSlotStates(Object.fromEntries(activeSlots.map(slot => [slot, 'queued' as SlotIqcState])));

    try {
      const response = await fetch('/digitalisation', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: data,
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(payload.message || 'Upload failed. Please try again.');
      }

      const newJobId = payload.job_id as string | undefined;

      if (!newJobId) {
        throw new Error('Upload succeeded, but no job id was returned.');
      }

      setCurrentJobId(newJobId);
      pollIqcStatus(newJobId, activeSlots);
    } catch (error) {
      setIsValidating(false);
      setStatusMsg({
        type: 'error',
        text: error instanceof Error ? error.message : 'Upload failed. Please try again.',
      });
    }
  };

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFiles = event.target.files ? Array.from(event.target.files) : [];
    if (selectedFiles.length === 0) return;

    const startIndex = activeIndex;
    const requiredLength = startIndex + selectedFiles.length;

    const nextImages = [...images];
    const nextFiles = [...files];

    while (nextImages.length < requiredLength) {
      nextImages.push(null);
      nextFiles.push(null);
    }

    selectedFiles.forEach((file, offset) => {
      const slotIndex = startIndex + offset;
      nextImages[slotIndex] = URL.createObjectURL(file);
      nextFiles[slotIndex] = file;
    });

    setImages(nextImages);
    setFiles(nextFiles);
    setStatusMsg(null);
    void submitForIqc(nextFiles);

    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleNextStep = () => {
    const hasAccepted = Object.entries(slotStates)
      .some(([idx, value]) => Boolean(files[Number(idx)]) && value === 'accepted');

    if (!hasAccepted) {
      setStatusMsg({ type: 'error', text: 'Wait until quality validation is accepted before proceeding.' });
      return;
    }

    if (!currentJobId) {
      setStatusMsg({ type: 'error', text: 'No validated batch found. Please upload images again.' });
      return;
    }

    setIsSubmittingBatch(true);
    void fetch(`/api/digitisation/jobs/${encodeURIComponent(currentJobId)}/submit`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
    })
      .then(async (response) => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(payload.message || 'Batch submission failed.');
        }

        const rejectedImages = Array.isArray(payload.rejected_images) ? payload.rejected_images as string[] : [];
        if (rejectedImages.length > 0) {
          setStatusMsg({
            type: 'error',
            text: `Rejected images: ${rejectedImages.join(', ')}. Please reupload clearer images. Accepted images were submitted as one batch.`,
          });
        } else {
          setStatusMsg({
            type: 'success',
            text: payload.message || 'Accepted images submitted as one batch.',
          });
        }

        router.visit(`/digitalisation1?job_id=${encodeURIComponent(currentJobId)}`);
      })
      .catch((error: unknown) => {
        setStatusMsg({
          type: 'error',
          text: error instanceof Error ? error.message : 'Batch submission failed.',
        });
      })
      .finally(() => {
        setIsSubmittingBatch(false);
      });
  };

  const addImageSlot = () => {
    setImages(prev => [...prev, null]);
    setFiles(prev => [...prev, null]);
    setActiveIndex(images.length); 
  };

  const removeImageSlot = (index: number) => {
    if (images.length <= 1) {
      setImages([null]);
      setFiles([null]);
      resetIqcState();
      return;
    }
    const newImages = images.filter((_, i) => i !== index);
    const newFiles = files.filter((_, i) => i !== index);
    setImages(newImages);
    setFiles(newFiles);
    setActiveIndex(Math.max(0, index - 1));

    stopPolling();
    setCurrentJobId(null);
    setIsValidating(false);

    setSlotStates(prev => {
      const next: Record<number, SlotIqcState> = {};
      Object.entries(prev).forEach(([rawKey, value]) => {
        const key = Number(rawKey);
        if (key === index) {
          return;
        }

        const shiftedKey = key > index ? key - 1 : key;
        next[shiftedKey] = value;
      });

      return next;
    });

    if (!newFiles.some(Boolean)) {
      resetIqcState();
      setStatusMsg(null);
      return;
    }

    setStatusMsg({ type: 'error', text: 'Image set changed. Quality check will rerun for the updated batch.' });
    void submitForIqc(newFiles);
  };

  const activeState = slotStates[activeIndex] ?? 'idle';
  const badge = labelForState(activeState);
  const filledIndices = files
    .map((file, index) => ({ file, index }))
    .filter(item => item.file !== null)
    .map(item => item.index);
  const acceptedSlots = filledIndices.filter(index => slotStates[index] === 'accepted').length;
  const rejectedSlots = filledIndices.filter(index => slotStates[index] === 'rejected').length;
  const pendingSlots = filledIndices.filter(index => {
    const state = slotStates[index];
    return !state || state === 'queued' || state === 'running' || state === 'idle';
  }).length;
  const isValidatingAny = isValidating;
  const canProceed = acceptedSlots > 0;

  return (
    <PageContainer>
      <AppSidebar/>
      <MainContent>
        <ContentWrapper>

          <Header>
            <h1>Plant Digitisation</h1>
            <p>Upload a specimen image</p>
          </Header>

          <LayoutGrid>
            <LeftColumn>
              <PreviewContainer>
                {images[activeIndex] ? (
                  <>
                    <DeleteButton onClick={() => removeImageSlot(activeIndex)}>
                      &times;
                    </DeleteButton>
                    
                    <PreviewImage src={images[activeIndex]!} />
                    <ImageLabel>IMAGE {activeIndex + 1}</ImageLabel>
                  </>
                ) : (
                  <EmptyPreviewText>Image {activeIndex + 1}</EmptyPreviewText>
                )}

                <ValidationBadge>
                  <ValidationTitle>
                    Validation
                  </ValidationTitle>

                  <StatusText $tone={badge.tone}>
                    <span className="dot"/>
                    Quality:
                    <span className="highlight">{badge.text}</span>
                  </StatusText>

                </ValidationBadge>
              </PreviewContainer>
            </LeftColumn>

            <RightColumn>
              <SectionTitle>
                Single or Multiple Image
              </SectionTitle>

              <ThumbnailRow>
                {images.map((img,i)=>(
                  <ThumbBox
                    key={i}
                    $isActive={activeIndex===i}
                    onClick={()=>setActiveIndex(i)}
                  >

                    {img
                      ? <img src={img} alt="Specimen"/>
                      : <ThumbNumber>{i+1}</ThumbNumber>
                    }

                  </ThumbBox>
                ))}

                <AddThumbButton $isActive={false} onClick={addImageSlot}>
                  +
                </AddThumbButton>
              </ThumbnailRow>
              
              <DropzoneLabel>
                <input
                  type="file"
                  hidden
                  accept="image/*"
                  multiple
                  onChange={handleFileChange}
                  ref={fileInputRef}
                />

                <span className="icon">📸</span>
                <span className="text">Click to upload field photo</span>
              </DropzoneLabel>

              <RunNameInput
                type="text"
                placeholder="Run name (optional)"
                value={runName}
                onChange={e => setRunName(e.target.value)}
              />

              <NextButton onClick={handleNextStep} disabled={!canProceed || isSubmittingBatch || isValidatingAny}>
                {isSubmittingBatch ? 'Submitting Batch…' : canProceed ? 'Next Step' : isValidatingAny ? 'Validating…' : 'Waiting For Validation'}
              </NextButton>

              {statusMsg && (
                <StatusBanner $type={statusMsg.type}>{statusMsg.text}</StatusBanner>
              )}

              {filledIndices.length > 0 && (
                <div style={{ marginTop: '0.65rem', fontSize: '0.72rem', color: '#6b7280', fontWeight: 700 }}>
                  Accepted: {acceptedSlots} | Rejected: {rejectedSlots} | Checking: {pendingSlots}
                </div>
              )}

            </RightColumn>
          </LayoutGrid>
        </ContentWrapper>
      </MainContent>
    </PageContainer>
  );
}