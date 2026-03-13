import React from "react";
import styled from "styled-components";
import { Home, Search, FileText } from "lucide-react";
import { usePage, Link } from "@inertiajs/react";

const SidebarContainer = styled.aside`
  width: 16rem;
  height: 100vh;
  background: #f2ede4;
  position: fixed;
  display: flex;
  flex-direction: column;
  padding: 1rem;
  box-sizing: border-box;
`;

const Brand = styled.div`
  padding: 1.5rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
`;

const BrandIcon = styled.div`
  background: #4a6741;
  padding: 0.6rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  svg { height: 1.25rem; width: 1.25rem; color: white; }
`;

const BrandText = styled.div`
  display: flex;
  flex-direction: column;
  line-height: 1.2;
  span:first-child { color: #4a6741; font-weight: 800; font-size: 1.1rem; letter-spacing: 0.02em; }
  span:last-child { font-size: 9px; color: #8c92ac; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; }
`;

const Nav = styled.nav`
  flex: 1;
  margin-top: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
`;

const NavSection = styled.p`
  font-size: 10px;
  font-weight: 800;
  color: #9ca3af;
  padding: 0 1rem;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
`;

const NavLink = styled(Link)`
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.8rem 1.2rem;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.9rem;
  text-decoration: none;
  color: #4b5563;
  transition: all 0.2s ease-in-out;

  /* HOVER State: Light background, dark text */
  &:hover {
    background: #ffffff90; /* Light semi-transparent white */
    color: #4a6741;
    svg { color: #4a6741; }
  }

  /* ACTIVE State: Solid Green */
  &.active {
    background: #4a6741;
    color: white;
    box-shadow: 0 4px 12px rgba(74, 103, 65, 0.2);
    svg { color: white; }
    
    /* Ensure hover doesn't change active state back to light */
    &:hover {
        background: #4a6741;
        color: white;
        svg { color: white; }
    }
  }

  svg { width: 1.1rem; height: 1.1rem; color: #4b5563; }
`;

const AccountBox = styled.div`
  padding: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-radius: 1.25rem;
  /* Updated to a lighter, subtle background to match image 2 */
  background: #ede8dc; 
  cursor: pointer;
  margin-top: auto;
  margin-bottom: 1rem;
`;

const Avatar = styled.div`
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background: #4a6741;
  color: white;
  font-weight: bold;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
`;

const Info = styled.div`
  display: flex;
  flex-direction: column;
  line-height: 1.3;
  p:first-child { font-size: 0.85rem; font-weight: 800; color: #4a6741; margin: 0; }
  p:last-child { 
    font-size: 8px; 
    font-weight: 600; 
    color: #6b7280; 
    margin: 0; 
    text-transform: uppercase; 
    opacity: 0.8;
  }
`;

export function AppSidebar () {
  const { url } = usePage();
  
  const fullName = sessionStorage.getItem("loggedUserName") || "Admin";
  const email = sessionStorage.getItem("loggedUserEmail") || "ADMIN123@GMAIL.COM";

  return (
    <SidebarContainer>
      <Brand>
        <BrandIcon>
          <Search size={20} strokeWidth={3} />
        </BrandIcon>
        <BrandText>
          <span>HERBARIUM</span>
          <span>HERBARIUM PLANT</span>
        </BrandText>
      </Brand>

      <Nav>
        <NavSection>Main Menu</NavSection>

        <NavLink href="/" className={url === '/' || url === '/dashboard' ? 'active' : ''}>
          <Home size={18} />
          Home
        </NavLink>

        <NavLink href="/identification" className={url === '/identification' ? 'active' : ''}>
          <Search size={18} />
          Identification
        </NavLink>

        <NavLink href="/digitalisation" className={url === '/digitalisation' ? 'active' : ''}>
          <FileText size={18} />
          Digitalisation
        </NavLink>

        <NavLink href="/digitalisation1" className={url === '/digitalisation1' ? 'active' : ''}>
          <FileText size={18} />
          Digitalisation
        </NavLink>
      </Nav>

      <AccountBox>
        <Avatar>{fullName.substring(0, 2).toUpperCase()}</Avatar>
        <Info>
          <p>{fullName}</p>
          <p>{email}</p>
        </Info>
      </AccountBox>
    </SidebarContainer>
  );
};
export { Sidebar as AppSidebar };
