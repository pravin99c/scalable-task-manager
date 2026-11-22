import React from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './taskforge.css';

const root = document.getElementById('taskforge-root');
if (!root) throw new Error('TaskForge root element is missing');

createRoot(root).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>,
);
