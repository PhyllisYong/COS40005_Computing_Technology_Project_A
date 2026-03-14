import React, { useRef } from "react";
import styled from "styled-components";
import { AppSidebar } from '@/components/app-sidebar';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const PageContainer = styled.div`
  display: flex;
  background: #f3f4f6;
  min-height: 100vh;
  width: 100%; /* Ensure container takes full width */
`;

const MainContent = styled.main`
  margin-left: 16rem; 
  flex: 1;
  padding: 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 3rem;
  height: 100vh;
  overflow-y: auto;
  position: relative;
  box-sizing: border-box; /* Includes padding in width calculation */
`;

const AboutSection = styled.section`
  background: white;
  padding: 2.5rem;
  border-radius: 1.5rem;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  width: 100%; /* Stretch to fill MainContent */
  box-sizing: border-box;
  
  h2 {
    color: #4a6741;
    font-size: 1.875rem;
    font-weight: 800;
    margin: 0 0 1rem 0;
  }
  p {
    color: #6b7280;
    line-height: 1.6;
    /* Removed max-width to let text fill the container */
    margin: 0;
  }
  
  p + p {
    margin-top: 1.5rem; 
  }
`;

const GalleryWrapper = styled.section`
  position: relative;
  display: flex;
  align-items: center;
  gap: 1rem;
  width: 100%;
`;

const CarouselContainer = styled.div`
  flex: 1;
  display: flex;
  gap: 1.5rem;
  overflow-x: auto;
  padding: 0.5rem 0;
  scroll-behavior: smooth;
  
  &::-webkit-scrollbar { display: none; }
  -ms-overflow-style: none;
  scrollbar-width: none;
`;

interface GalleryImageProps {
  src: string;
}

const GalleryImage = styled.div<GalleryImageProps>`
  min-width: 320px;
  height: 220px;
  background: #e5e7eb;
  border-radius: 1.25rem;
  background-image: url(${props => props.src});
  background-size: cover;
  background-position: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  flex-shrink: 0;
`;

const IconButton = styled.button`
  background: white;
  border: none;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  color: #5d7356;
  transition: all 0.2s;
  z-index: 2;
  flex-shrink: 0; /* Prevents button from squishing */

  &:hover {
    background: #5d7356;
    color: white;
    transform: scale(1.1);
  }
`;

const SpeciesGrid = styled.div`
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 2rem;
  width: 100%;
`;

const SpeciesCard = styled.div`
  background: white;
  border-radius: 1.25rem;
  overflow: hidden;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s, box-shadow 0.2s;

  &:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
  }

  .img-placeholder {
    height: 200px;
    background: #fdfcf9;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #e5e7eb;
    font-weight: 800;
  }

  .info {
    padding: 1.5rem;
    h3 { 
      margin: 0; 
      font-size: 1.1rem; 
      color: #374151; 
      font-weight: 700;
    }
    span { 
      display: block;
      margin-top: 0.25rem;
      font-size: 0.85rem; 
      color: #9ca3af; 
      font-style: italic; 
    }
  }
`;

const SectionTitle = styled.h3`
  color: #9ca3af; 
  text-transform: uppercase; 
  font-size: 0.75rem; 
  letter-spacing: 0.2em; 
  font-weight: 800;
  margin-bottom: 1.5rem;
`;

const FooterLogoContainer = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 5rem; 
  padding: 4rem 0;
  margin-top: auto; 
  width: 100%; /* Ensure it spans the full width of MainContent */

  img {
    height: 100px; 
    width: auto;
    object-fit: contain;
  }
`;

export default function home() {
  const scrollRef = useRef<HTMLDivElement>(null);

  const handleScroll = (direction: 'left' | 'right') => {
    if (scrollRef.current) {
      const scrollAmount = 350;
      scrollRef.current.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth'
      });
    }
  };

  const recentSpecies = [
    { name: "Swiss Cheese Plant", scientific: "Monstera deliciosa" },
    { name: "Snake Plant", scientific: "Dracaena trifasciata" },
    { name: "Fiddle Leaf Fig", scientific: "Ficus lyrata" },
    { name: "Rubber Plant", scientific: "Ficus elastica" },
  ];

  return (
    <PageContainer>
      <AppSidebar />
      
      <MainContent>
        <AboutSection>
          <h2>About</h2>
          <p>
            Automated Digitisation and Information Extraction from Herbarium Specimens is a project being developed in conjunction with the Sarawak Forest Department (FDS). By automatically extracting important information from specimen images such as species names, collection dates, and locations, the technology seeks to digitise herbarium specimens and convert physical plant records into easily accessible digital data.
          </p>
          <p>
            The software detects and extracts text from herbarium specimen labels using technologies like optical character recognition (OCR), and natural language processing (NLP). Additionally, it has a plant identification tool that allows users to submit field or herbarium photos to accurately predict the species name, helping in the conversion of physical plant records into digital data that can be used for conservation and research.
          </p>
          <p>
            Our goal is to increase access to herbarium data in order to help biodiversity study and conservation. The method makes it easier and more effective for researchers, conservationists, and educators to study Sarawak's botanical record by digitizing specimen data.
          </p>
        </AboutSection>

        <div>
          <SectionTitle>Specimen Gallery</SectionTitle>
          <GalleryWrapper>
            <IconButton onClick={() => handleScroll('left')}>
              <ChevronLeft size={24} />
            </IconButton>
            
            <CarouselContainer ref={scrollRef}>
              <GalleryImage src="https://images.unsplash.com/photo-1545241047-6083a3684587?auto=format&fit=crop&w=600" />
              <GalleryImage src="https://images.unsplash.com/photo-1463171379579-3fdfb86d6285?auto=format&fit=crop&w=600" />
              <GalleryImage src="https://images.unsplash.com/photo-1520412099551-62b6bafeb5bb?auto=format&fit=crop&w=600" />
              <GalleryImage src="https://images.unsplash.com/photo-1501004318641-729e8e26bd05?auto=format&fit=crop&w=600" />
            </CarouselContainer>
            
            <IconButton onClick={() => handleScroll('right')}>
              <ChevronRight size={24} />
            </IconButton>
          </GalleryWrapper>
        </div>

        <div>
          <SectionTitle>Recent Species Added</SectionTitle>
          <SpeciesGrid>
            {recentSpecies.map((plant, i) => (
              <SpeciesCard key={i}>
                <div className="img-placeholder">IMAGE PREVIEW</div>
                <div className="info">
                  <h3>{plant.name}</h3>
                  <span>{plant.scientific}</span>
                </div>
              </SpeciesCard>
            ))}
          </SpeciesGrid>
        </div>

        <FooterLogoContainer>
            <img src="/NeuonAi.png" alt="NeonAi Logo" />
            <img src="/Swinburne.png" alt="Swinburne Logo" />
        </FooterLogoContainer>
      </MainContent>
    </PageContainer>
  );
}