import React, { useState } from "react";
import styled, { keyframes } from "styled-components";
import {AppSidebar} from '@/components/app-sidebar'; 

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
  margin-left: 16rem; /* Sidebar width */
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
  padding: 1.5rem 0 2.5rem 0;
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

// --- UI Elements ---

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
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
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
  min-width: 150px;
  z-index: 10;
  text-align: right;
`;

const StatusText = styled.div`
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  color: #4b5563;
  margin-top: 0.25rem;

  .dot {
    width: 8px;
    height: 8px;
    background: #22c55e;
    border-radius: 50%;
    box-shadow: 0 0 8px #22c55e;
    animation: ${pulse} 2s infinite ease-in-out;
  }
  
  .highlight { color: #16a34a; }
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
  margin-bottom: 2rem;
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
`;

export default function PlantDetails() {
  const [formData, setFormData] = useState({
    name: "",
    scientific: "",
    location: "",
    date: ""
  });

  const sessionImage = null; 

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;

    setFormData({ 
      ...formData,      
      [name]: value   
    });
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

          <LayoutGrid>
            {/* Left: Image Preview Area */}
            <PreviewCard>
              <ImageStage>
                {sessionImage ? (
                  <img 
                    src={sessionImage} 
                    alt="Specimen preview" 
                    style={{ maxHeight: '100%', maxWidth: '100%', objectFit: 'contain' }} 
                  />
                ) : (
                  <PlaceholderText>No Image Selected</PlaceholderText>
                )}
              </ImageStage>

              <ValidationOverlay>
                <p style={{ fontSize: '9px', color: '#9ca3af', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: '4px', borderBottom: '1px solid #f3f4f6', paddingBottom: '2px' }}>
                  Validation
                </p>
                <StatusText>
                   Quality: <span className="highlight">Clear</span>
                   <span className="dot" />
                </StatusText>
                <p style={{ fontSize: '10px', color: '#d1d5db', textTransform: 'uppercase', marginRight: '1.4rem' }}>Blurry</p>
              </ValidationOverlay>
            </PreviewCard>

            {/* Right: Details Form */}
            <DetailsSidebar>
              <DetailsTitle>Details</DetailsTitle>
              
              <Form onSubmit={(e) => e.preventDefault()}>
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                  <InputGroup>
                    <label>Specimen Name</label>
                    <input 
                      type="text" 
                      name="name"
                      placeholder="Enter name..." 
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
                      onChange={handleInputChange}
                    />
                  </InputGroup>

                  <InputGroup>
                    <label>Location Found</label>
                    <input 
                      type="text" 
                      name="location"
                      placeholder="GPS or Site Name..." 
                      onChange={handleInputChange}
                    />
                  </InputGroup>

                  <InputGroup>
                    <label>Date Collected</label>
                    <input 
                      type="date" 
                      name="date"
                      onChange={handleInputChange}
                    />
                  </InputGroup>
                </div>

                <SaveButton type="submit">
                  Edit & Save
                </SaveButton>
              </Form>
            </DetailsSidebar>
          </LayoutGrid>
        </ContentWrapper>
      </MainContent>
    </PageContainer>
  );
}