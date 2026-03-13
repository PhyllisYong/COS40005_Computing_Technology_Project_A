import React from "react";
import styled, { createGlobalStyle } from "styled-components";

const MainContent = styled.div`
  body{
    margin: 0; 
    background-color: #f7f7f7; 
    font-family: 'Segoe UI', Tahoma, sans-serif; 
  }
`;

const AuthContainer = styled.div`
  display: flex; 
  align-items: center; 
  justify-content: center; 
  min-height: 100vh; 
  padding: 1.5rem;
`;

const LoginCard = styled.div`
  max-width: 480px; 
  width: 100%; 
  background: white; 
  border-radius: 1.5rem;
  padding: 3rem 2rem; 
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03); 
  border: 1px solid #eee;
`;

const Header = styled.div`
  text-align: center; 
  margin-bottom: 2rem;
  h1 { 
    color: #4a6741; 
    font-size: 1.75rem; 
    font-weight: 700; 
    margin: 0 0 0.5rem 0; 
  }
  p { 
    color: #888; 
    font-size: 0.85rem; 
    line-height: 1.4; 
    margin: 0; 
    padding: 0 10px; 
  }
`;

const Form = styled.form` 
  display: flex; 
  flex-direction: column; 
  gap: 1.25rem; 
`;

const FormGroup = styled.div` 
  display: flex; 
  flex-direction: column; 
  gap: 0.4rem; 
`;

const Label = styled.label` 
  font-size: 0.65rem; 
  font-weight: 800; 
  color: #a0aab4; 
  text-transform: uppercase; 
  letter-spacing: 0.05em; 
`;

const Input = styled.input`
  width: 100%; 
  padding: 0.9rem 1.1rem; 
  background: #f9f9f9; 
  border: none; 
  border-radius: 0.75rem;
  font-size: 0.9rem; 
  color: #333;
  box-sizing: border-box;
  &::placeholder { 
    color: #ccc;
  }
  &:focus { 
    outline: 2px solid #4a67411a; 
    background: #f5f5f5; 
  }
`;

const SubmitButton = styled.button`
  width: 100%; 
  padding: 1rem; 
  background: #556b43; 
  color: white; 
  font-weight: 700; 
  border: none; 
  border-radius: 0.75rem; 
  cursor: pointer; 
  margin-top: 0.5rem;
  box-shadow: 0 4px 10px rgba(85, 107, 67, 0.2); 
  transition: all 0.2s;
  &:hover { 
    background: #445635; 
  }
`;

const SwitchText = styled.p`
  text-align: center; 
  margin-top: 2rem; 
  color: #777; 
  font-size: 0.8rem;
  a { 
    color: #4a6741; 
    font-weight: 700; 
    text-decoration: none; 
    &:hover { 
      text-decoration: underline; 
    } 
  }
`;

export default function Login() {
  return (
    <>
      <MainContent />
      <AuthContainer>
        <LoginCard>
          <Header>
            <h1>Welcome Back</h1>
            <p>Please provide your credentials to continue to your portal.</p>
          </Header>

          <Form onSubmit={(e) => e.preventDefault()}>
            <FormGroup>
              <Label>Email Address</Label>
              <Input type="email" placeholder="admin@example.com" required />
            </FormGroup>

            <FormGroup>
              <Label>Password</Label>
              <Input type="password" placeholder="••••••••" required />
            </FormGroup>

            <SubmitButton type="submit">Sign In</SubmitButton>
          </Form>

          <SwitchText>
            Don't have an account? <a href="/register">Create Account</a>
          </SwitchText>
        </LoginCard>
      </AuthContainer>
    </>
  );
}

