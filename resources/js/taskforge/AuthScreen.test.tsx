import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { AuthScreen } from './AuthScreen';
import { api } from './api';

describe('Authentication screen', () => {
    it('shows sign-in and switches to registration with password confirmation', () => {
        render(<AuthScreen onAuthenticated={vi.fn()}/>);
        expect(screen.getByRole('heading', { name: /welcome back/i })).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: /create an account/i }));
        expect(screen.getByRole('heading', { name: /create your workspace/i })).toBeInTheDocument();
        expect(screen.getByLabelText('Confirm password')).toBeInTheDocument();
    });

    it('does not submit a registration with mismatched passwords', async () => {
        const submit = vi.spyOn(api, 'register');
        render(<AuthScreen onAuthenticated={vi.fn()}/>);
        fireEvent.click(screen.getByRole('button', { name: /create an account/i }));
        fireEvent.change(screen.getByLabelText('Full name'), { target: { value: 'Example Person' } });
        fireEvent.change(screen.getByLabelText('Email address'), { target: { value: 'hello@example.test' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'very-long-password-123' } });
        fireEvent.change(screen.getByLabelText('Confirm password'), { target: { value: 'different-long-password-123' } });
        fireEvent.click(screen.getByRole('button', { name: /create account/i }));
        await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Passwords do not match.'));
        expect(submit).not.toHaveBeenCalled();
    });
});
