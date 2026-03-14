import React, { useState, useRef } from "react";
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

const StatusText = styled.div`
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
    background: #10b981;
    border-radius: 50%;
    animation: ${pulse} 2s infinite;
  }

  .highlight {
    color: #059669;
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

  const [images, setImages] = useState<(string | null)[]>([null]);
  const [files,  setFiles]  = useState<(File | null)[]>([null]);
  const [activeIndex, setActiveIndex] = useState(0);
  const [runName, setRunName] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [statusMsg, setStatusMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const imageUrl = URL.createObjectURL(file);
    setImages(prev => { const n = [...prev]; n[activeIndex] = imageUrl; return n; });
    setFiles(prev  => { const n = [...prev]; n[activeIndex] = file;     return n; });
    setStatusMsg(null);
  };

  const handleSubmit = () => {
    const uploadFiles = files.filter((f): f is File => f !== null);
    if (uploadFiles.length === 0) {
      setStatusMsg({ type: 'error', text: 'Please upload at least one image.' });
      return;
    }
    const name = runName.trim() || `Run ${new Date().toISOString().slice(0, 19).replace('T', ' ')}`;
    const data = new FormData();
    data.append('run_name', name);
    uploadFiles.forEach(f => data.append('files[]', f));
    setSubmitting(true);
    setStatusMsg(null);
    router.post('/digitalisation', data, {
      forceFormData: true,
      onSuccess: () => {
        setStatusMsg({ type: 'success', text: `Job "${name}" submitted successfully.` });
        setTimeout(() => {
            router.visit('/digitalisation1'); 
        }, 1000);
      },
      //   setImages([null, null, null, null]);
      //   setFiles([null, null, null, null]);
      //   setRunName('');
      //   setActiveIndex(0);
      //   if (fileInputRef.current) fileInputRef.current.value = '';
      // },
      onError: (errors) => {
        setStatusMsg({ type: 'error', text: Object.values(errors).join(' ') });
      },
      onFinish: () => setSubmitting(false),
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
      return;
    }
    const newImages = images.filter((_, i) => i !== index);
    const newFiles = files.filter((_, i) => i !== index);
    setImages(newImages);
    setFiles(newFiles);
    setActiveIndex(Math.max(0, index - 1));
  };

  return (
    <PageContainer>
      <AppSidebar/>
      <MainContent>
        <ContentWrapper>

          <Header>
            <h1>Plant Digitalisation</h1>
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

                  <StatusText>
                    <span className="dot"/>
                    Quality:
                    <span className="highlight">Clear</span>
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
                disabled={submitting}
              />

              <NextButton onClick={handleSubmit} disabled={submitting || statusMsg?.type === 'error'}>
                {submitting ? 'Submitting…' : 'Next Step'}
              </NextButton>

              {statusMsg && (
                <StatusBanner $type={statusMsg.type}>{statusMsg.text}</StatusBanner>
              )}

            </RightColumn>
          </LayoutGrid>
        </ContentWrapper>
      </MainContent>
    </PageContainer>
  );
}