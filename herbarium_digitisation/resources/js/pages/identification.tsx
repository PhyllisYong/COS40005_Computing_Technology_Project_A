import React, { useState, useRef, useEffect } from "react";
import styled from "styled-components";
import { AppSidebar } from '@/components/app-sidebar';
import { ChevronRight, Image as ImageIcon, X, Maximize2 } from "lucide-react";
import { heatmap } from "@/actions/App/Http/Controllers/PredictController";

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

const Header = styled.header`
  max-width: 80rem;
  width: 100%;
  margin: 0 auto;
  padding: 1.5rem 2.5rem 2.5rem 2.5rem;

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

const ContentGrid = styled.div`
  display: flex;
  flex: 1;
  gap: 2rem;
  max-width: 80rem;
  width: 100%;
  margin: 0 auto;
  padding: 0 2.5rem 2.5rem 2.5rem;
  overflow: visible; 
`;

const LeftColumn = styled.div`
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2.5rem;
  min-height: 0;
`;

const Card = styled.div<{ $height?: string; $isColumn?: boolean; $center?: boolean }>`
  background: white;
  border-radius: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid #f3f4f6;
  padding: 1.5rem;
  display: flex;
  position: relative;
  height: ${props => props.$height || 'auto'};
  flex-direction: ${props => props.$isColumn ? 'column' : 'row'};
  align-items: ${props => props.$center ? 'center' : 'stretch'};
  gap: ${props => props.$center ? '2.5rem' : '0'};
  min-height: 0;
  overflow: visible; 
`;

const TabContainer = styled.div`
  position: absolute;
  top: -20px; 
  right: 2.5rem;
  display: flex;
  gap: 0.5rem;
  z-index: 100;
`;

const TabButton = styled.button<{ $active: boolean }>`
  padding: 0.6rem 1.5rem;
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-radius: 0.75rem;
  border: 1px solid ${props => props.$active ? 'transparent' : '#f3f4f6'};
  background: ${props => props.$active ? '#ecece4' : 'white'};
  color: ${props => props.$active ? '#4a6741' : '#6b7280'};
  cursor: pointer;
  box-shadow: ${props => props.$active ? 'none' : '0 2px 4px rgba(0,0,0,0.05)'};
  transition: all 0.2s ease-in-out;

  &:hover {
    background: #f5f5f0;
    border-color: #e5e7eb;
  }
`;

const Dropzone = styled.div<{ $canClick: boolean }>`
  flex: 1;
  background: #f9f9f7;
  border: 1px solid #f3f4f6;
  border-radius: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: ${props => props.$canClick ? 'pointer' : 'default'};
  overflow: hidden;
  transition: all 0.2s;

  &:hover {
    background: ${props => props.$canClick ? '#f3f3ef' : '#f9f9f7'};
  }
`;

const EmptyStateText = styled.p`
  color: #7e91aa;
  font-size: 1.1rem;
  font-weight: 500;
  text-align: center;
`;

const ImageWrapper = styled.div`
  position: relative;
  height: 100%;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
`;

const PreviewImage = styled.img`
  max-height: 100%;
  max-width: 100%;
  object-fit: contain;
  border-radius: 0.5rem;
  transition: filter 0.3s ease;
  
`;

const ResultsList = styled.div`
  width: 450px;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  overflow-y: auto;
  padding: 15px 10px 15px 5px; 
  overflow-x: visible; 

  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 20px; }
`;

const ResultCardContainer = styled.div`
  background: white;
  border-radius: 1.25rem;
  border: 1px solid #f3f4f6;
  transition: all 0.3s ease;
  overflow: hidden;

  &:hover {
    border-color: #a3b18a;
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.05);
  }
`;

const ResultMainRow = styled.div`
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
`;


const ToggleIcon = styled.div<{ $isExpanded: boolean }>`
  color: #9ca3af;
  transition: transform 0.3s ease;
  transform: rotate(${props => props.$isExpanded ? '90deg' : '0deg'});
  display: flex;
  align-items: center;
`;

const SpeciesInfo = styled.div`
  flex: 1;
  h4 { font-size: 9px; font-weight: 800; color: #9ca3af; text-transform: uppercase; margin: 0; }
  p { font-size: 1.1rem; font-weight: 700; font-style: italic; color: #374151; margin: 2px 0; }
`;

const ScoreText = styled.span`
  color: #4a6741;
  font-weight: 800;
  font-size: 0.95rem;
`;

const ExpandedDetails = styled.div<{ $isExpanded: boolean }>`
  max-height: ${props => props.$isExpanded ? '180px' : '0'};
  opacity: ${props => props.$isExpanded ? '1' : '0'};
  padding: ${props => props.$isExpanded ? '0 1rem 1.25rem 2.85rem' : '0 1rem 0 2.85rem'};
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  background: #fcfcfb;
`;

const ReferenceGrid = styled.div`
  display: flex;
  gap: 0.8rem;
  margin-top: 0.75rem;
`;

const RefSquare = styled.div`
  width: 65px;
  height: 65px;
  background: #f3f4f6;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #cbd5e1;
  cursor: pointer;
  position: relative;
  transition: all 0.2s ease;

  &:hover {
    border-color: #a3b18a;
    color: #4a6741;
    background: #f0f4ee;
  }

  .hover-icon {
    position: absolute;
    opacity: 0;
    transition: opacity 0.2s;
  }

  &:hover .hover-icon {
    opacity: 1;
  }
`;

const ProgressBar = styled.div<{ $width: number }>`
  width: 100%;
  height: 5px;
  background: #f3f4f6;
  border-radius: 999px;
  overflow: hidden;
  margin-top: 0.5rem;

  div {
    width: ${props => props.$width}%;
    height: 100%;
    background: #5d7356;
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
  }
`;

const ModalOverlay = styled.div`
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
`;

const ModalContent = styled.div`
  background: white;
  padding: 2rem;
  border-radius: 2rem;
  max-width: 450px;
  width: 90%;
  position: relative;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  text-align: center;
`;

const ModalClose = styled.button`
  position: absolute;
  top: 1.25rem;
  right: 1.25rem;
  background: #f3f4f6;
  border: none;
  border-radius: 50%;
  padding: 0.5rem;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.2s;
  &:hover { background: #e5e7eb; color: #111827; }
`;

const LegendContainer = styled.div`
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 160px;
  overflow-y: auto;
  padding-right: 10px;
`;

const LegendItem = styled.div`
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #4b5563;
  white-space: nowrap;
`;

const Dot = styled.span<{ color: string }>`
  width: 0.65rem;
  height: 0.65rem;
  background: ${props => props.color};
  border-radius: 50%;
  flex-shrink: 0;
`;

const DonutSegment = styled.circle`
  cursor: pointer;
  transition: opacity 0.2s ease, stroke-width 0.2s ease;
  pointer-events: visibleStroke;
  &:hover {
    stroke-width: 22;
    opacity: 0.8;
  }
`;

const LoadingSkeleton = styled.div`
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 10px;
`;

const SkeletonCard = styled.div`
  height: 70px;
  border-radius: 10px;
  background: linear-gradient(
    90deg,
    #f3f4f6 25%,
    #e5e7eb 37%,
    #f3f4f6 63%
  );
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;

  @keyframes shimmer {
    0% { background-position: 100% 0 }
    100% { background-position: -100% 0 }
  }
`;

const ImageSkeleton = styled.div`
  width: 100%;
  height: 100%;
  border-radius: 0.5rem;

  background: linear-gradient(
    90deg,
    #f3f4f6 25%,
    #e5e7eb 37%,
    #f3f4f6 63%
  );
  background-size: 400% 100%;

  animation: shimmer 1.4s ease infinite;

  @keyframes shimmer {
    0% { background-position: 100% 0 }
    100% { background-position: -100% 0 }
  }
`;

const RefImage = styled.img`
  width: 100%;
  height: 100%;
  object-fit: cover;
`;

export default function Identification() {

  //Prediction type
  type Prediction = {
    name: string;
    score: number;
    refs: string[];
  };

  // Image upload
  const [imageUrl, setImageUrl] = useState<string | null>(null);
  const [heatmapUrl, setHeatmapUrl] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [currentFile, setCurrentFile] = useState<File | null>(null);

  // Loading Effect
  const [predictionloading, setPredictionLoading] = useState(false);
  const [imageloading, setImageLoading] = useState(false);

  // Prediction 
  const [predictions, setPredictions] = useState<Prediction[]>([]);

  // Raw image / GradCAM
  const [activeTab, setActiveTab] = useState<'raw' | 'gradcam'>('raw');

  // Result card expansion 
  const [expandedIndex, setExpandedIndex] = useState<number | null>(null);

  // Modal popup 
  const [selectedRef, setSelectedRef] = useState<{
    species: string;
    id: number;
    url: string;
  } | null>(null);

  // Donut chart 
  const [hoveredData, setHoveredData] = useState<{
    name: string;
    percent: number;
  } | null>(null);



  const chartConfig = [
    { percent: 50, color: '#4a6741' },
    { percent: 25, color: '#7a9471' },
    { percent: 15, color: '#a3b18a' },
    { percent: 7, color: '#ccd5ae' },
    { percent: 3, color: '#e9edc9' },
  ];

  const radius = 75;
  const circumference = 2 * Math.PI * radius;
  let currentOffset = 0;


  //Fucntion
  // Handle file upload
  async function handleFileSelect(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;

    setImageUrl(URL.createObjectURL(file));
    setCurrentFile(file);
    setHeatmapUrl(null); //reset previous heatmap 
    setPredictionLoading(true);
    setPredictions([]);

    const formData = new FormData();
    formData.append("file", file);

    const token = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute("content");

    try {
      const response = await fetch("/api/identify", {
        method: "POST",
        headers: { "X-CSRF-TOKEN": token || "" },
        body: formData,
        credentials: "include",
      });

      const data = await response.json();
      const formatted = (data.predictions || []).map((p: any) => ({
        name: p.name,
        score: p.score,
        refs: getRefs(p.classid)
      }));
      setPredictions(formatted);

      // If Grad-CAM tab is active, fetch the heatmap immediately
      if (activeTab === "gradcam") {
        fetchHeatmap(file);
      }
    } catch (err) {
      console.error("Identify API error:", err);
    } finally {
      setPredictionLoading(false);
    }
  }

  // Fetch heatmap
  async function fetchHeatmap(file: File) {
    try {
      setImageLoading(true);

      const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

      const formData = new FormData();
      formData.append("file", file);

      const response = await fetch("/api/heatmap", {
        method: "POST",
        headers: { "X-CSRF-TOKEN": token || "" },
        body: formData,
        credentials: "include",
      });

      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      setHeatmapUrl(data.heatmap);
    } catch (err) {
      console.error("Heatmap API error:", err);
    } finally {
      setImageLoading(false);
    }
  }

  // Handle tab change
  useEffect(() => {
    if (
      activeTab === "gradcam" &&
      !heatmapUrl &&
      currentFile
    ) {
      setImageLoading(true);
      fetchHeatmap(currentFile);
    }
  }, [activeTab]);

  function getRefs(classId: number) {
    return [
      `/sample/${classId}/1.jpg`,
      `/sample/${classId}/2.jpg`,
      `/sample/${classId}/3.jpg`
    ];
  }


  return (
    <PageContainer>
      <AppSidebar />
      <MainContent>
        <Header>
          <h1>Species Identification</h1>
          <p>Analyze and Save Specimen Data</p>
        </Header>

        <ContentGrid>
          <LeftColumn>
            <Card $height="60%" $isColumn>
              <TabContainer>
                <TabButton $active={activeTab === 'gradcam'} onClick={() => setActiveTab('gradcam')}>
                  GradCAM
                </TabButton>
                <TabButton $active={activeTab === 'raw'} onClick={() => setActiveTab('raw')}>
                  Raw Image
                </TabButton>
              </TabContainer>

              <Dropzone $canClick={activeTab === 'raw'} onClick={() => activeTab === 'raw' && fileInputRef.current?.click()}>
                <input
                  type="file"
                  ref={fileInputRef}
                  hidden
                  onChange={handleFileSelect}
                />
                {!imageUrl ? (
                  <EmptyStateText>
                    {activeTab === 'raw' ? "📸 Click to upload field photo" : "No Image Selected"}
                  </EmptyStateText>
                ) : (
                  <ImageWrapper>

                    {imageloading && <ImageSkeleton />}

                    <PreviewImage
                      src={activeTab === "gradcam" ? heatmapUrl ?? imageUrl : imageUrl}
                      onLoad={() => setImageLoading(false)}
                      style={{ display: imageloading ? "none" : "block" }}
                    />

                  </ImageWrapper>
                )}
              </Dropzone>
            </Card>
            {imageUrl && (
              <Card $height="33%" $center>

                {/* STATE 1: Loading */}
                {predictionloading && <ImageSkeleton />}

                {/* STATE 2: Results */}
                {!predictionloading && predictions.length > 0 && (
                  <>
                    <div style={{ position: 'relative', width: '11rem', height: '11rem', flexShrink: 0 }}>
                      <svg viewBox="0 0 176 176" style={{ transform: 'rotate(-90deg)' }}>
                        <circle cx="88" cy="88" r={radius} stroke="#f0f0e8" strokeWidth="18" fill="transparent" />

                        {chartConfig.map((segment, index) => {
                          const strokeDasharray = (segment.percent / 100) * circumference;
                          const dashOffset = currentOffset;
                          currentOffset -= strokeDasharray;

                          return (
                            <DonutSegment
                              key={index}
                              cx="88"
                              cy="88"
                              r={radius}
                              stroke={segment.color}
                              strokeWidth="18"
                              fill="transparent"
                              strokeDasharray={`${strokeDasharray} ${circumference}`}
                              strokeDashoffset={dashOffset}
                              strokeLinecap="butt"
                              onMouseMove={() => setHoveredData({ name: predictions[index].name, percent: segment.percent })}
                              onMouseLeave={() => setHoveredData(null)}
                            />
                          );
                        })}
                      </svg>
                      <div style={{
                        position: 'absolute',
                        inset: 0,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                        textAlign: 'center',
                        padding: '1.5rem',
                        pointerEvents: 'none'
                      }}>
                        {hoveredData ? (
                          <>
                            <span style={{ fontSize: '1.25rem', fontWeight: 800, color: '#4a6741' }}>{hoveredData.percent}%</span>
                            <span style={{ fontSize: '0.6rem', fontWeight: 700, color: '#6b7280', textTransform: 'uppercase', marginTop: '2px' }}>{hoveredData.name}</span>
                          </>
                        ) : (
                          <span style={{ fontSize: '0.7rem', fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase' }}>Distribution</span>
                        )}
                      </div>
                    </div>

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', flex: 1 }}>
                      <h3 style={{ fontSize: '0.75rem', fontWeight: 800, color: '#9ca3af', textTransform: 'uppercase', marginBottom: '0.25rem' }}>Top 5 Species Distribution</h3>
                      <LegendContainer>
                        {predictions.slice(0, 5).map((res, idx) => (
                          <LegendItem key={idx}>
                            <Dot color={chartConfig[idx].color} />
                            <span style={{ flex: 1, overflow: 'hidden', textOverflow: 'ellipsis' }}>{res.name}</span>
                          </LegendItem>
                        ))}
                      </LegendContainer>
                    </div>

                  </>
                )}

              </Card>
            )}
          </LeftColumn>


          <ResultsList>

            {/* STATE 1: No image */}
            {!imageUrl && (
              <div style={{ textAlign: 'center', padding: '40px', color: '#9ca3af' }}>
                Upload an image to start identification
              </div>
            )}

            {/* STATE 2: Loading */}
            {imageUrl && predictionloading && (
              <LoadingSkeleton>
                <SkeletonCard />
                <SkeletonCard />
                <SkeletonCard />
                <SkeletonCard />
                <SkeletonCard />
              </LoadingSkeleton>
            )}

            {/* STATE 3: Results */}
            {!predictionloading && predictions.map((res, i) => (
              <ResultCardContainer key={i}>
                <ResultMainRow onClick={() => setExpandedIndex(expandedIndex === i ? null : i)}>
                  <ToggleIcon $isExpanded={expandedIndex === i}>
                    <ChevronRight size={20} />
                  </ToggleIcon>

                  <SpeciesInfo>
                    <h4>Species Name & Similarity</h4>
                    <p>{res.name}</p>
                    <ProgressBar $width={res.score}><div /></ProgressBar>
                  </SpeciesInfo>

                  <ScoreText>{res.score}%</ScoreText>
                </ResultMainRow>

                <ExpandedDetails $isExpanded={expandedIndex === i}>
                  <h5 style={{
                    fontSize: '9px',
                    textTransform: 'uppercase',
                    color: '#9ca3af',
                    margin: '0 0 5px 0'
                  }}>
                    Similar References
                  </h5>

                  <ReferenceGrid>
                    {res.refs.map((url, refIdx) => (
                      <RefSquare key={refIdx} onClick={(e) => {
                        e.stopPropagation();
                        setSelectedRef({ species: res.name, id: refIdx + 1, url: url });
                      }}>
                        <RefImage src={url} alt={`Reference ${refIdx}`} />
                        <Maximize2 size={16} className="hover-icon" />
                      </RefSquare>
                    ))}
                  </ReferenceGrid>

                </ExpandedDetails>
              </ResultCardContainer>
            ))}

          </ResultsList>
        </ContentGrid>

        {selectedRef && (
          <ModalOverlay onClick={() => setSelectedRef(null)}>
            <ModalContent onClick={(e) => e.stopPropagation()}>
              <ModalClose onClick={() => setSelectedRef(null)}><X size={20} /></ModalClose>
              <div style={{ width: '100%', height: '280px', background: '#f9f9f7', borderRadius: '1.5rem', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '1.5rem', border: '1px solid #f3f4f6' }}>
                <img
                  src={selectedRef.url}
                  style={{
                    width: "100%",
                    height: "100%",
                    objectFit: "contain",
                    borderRadius: "1rem"
                  }}
                />
              </div>
              <h2 style={{ color: '#4a6741', fontStyle: 'italic', margin: '0 0 0.5rem 0', fontSize: '1.5rem' }}>{selectedRef.species}</h2>
              <p style={{ color: '#9ca3af', fontSize: '0.75rem', textTransform: 'uppercase', fontWeight: 800, letterSpacing: '0.05em' }}>
                Specimen View #{selectedRef.id}
              </p>
            </ModalContent>
          </ModalOverlay>
        )}
      </MainContent>
    </PageContainer>
  );
}