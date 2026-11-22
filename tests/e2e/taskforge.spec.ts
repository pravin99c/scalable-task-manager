import { test, expect, type Page } from '@playwright/test';

const password = 'Test-Only-A7x9-S3cure-Pass!';
const owner = 'e2e-owner@example.test';
const viewer = 'e2e-viewer@example.test';
const invitee = 'e2e-invitee@example.test';
const pending = 'e2e-pending@example.test';

async function signIn(page: Page, email: string): Promise<void> {
    await page.goto('/app');
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByRole('button', { name: 'Sign out' })).toBeVisible();
}

test('guest cannot access API projects or bypass the real CSRF guard', async ({ page }) => {
    await page.goto('/app');
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
    const session = await page.request.get('/app/api/session');
    expect((await session.json()).data).toBeNull();

    const forbidden = await page.request.get('/api/v2/projects', {
        headers: { Accept: 'application/json' },
    });
    expect(forbidden.status()).toBe(401);

    // This request does not include the CSRF header or Laravel's form token.
    // The E2E browser server deliberately runs with APP_ENV=local, so the
    // actual CSRF middleware is enabled (unlike a PHPUnit request).
    const csrf = await page.request.post('/app/api/login', {
        headers: { Accept: 'application/json' },
        data: { email: owner, password },
    });
    expect(csrf.status()).toBe(419);
});

test('owner web session survives reload, uses HTTP-only cookie and logs out', async ({ page, context }) => {
    await signIn(page, owner);
    await expect(page.getByRole('heading', { name: 'E2E Launch Project' })).toBeVisible();

    const cookies = await context.cookies();
    expect(cookies.some(cookie => cookie.httpOnly && cookie.name.endsWith('_session'))).toBeTruthy();

    await page.reload();
    await expect(page.getByRole('heading', { name: 'E2E Launch Project' })).toBeVisible();
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();

    const unauthorized = await page.request.get('/api/v2/projects', {
        headers: { Accept: 'application/json' },
    });
    expect(unauthorized.status()).toBe(401);
});

test('browser registration creates an unverified, signed-in account', async ({ page }) => {
    await page.goto('/app');
    await page.getByRole('button', { name: 'Create an account' }).click();
    await page.getByLabel('Full name').fill('E2E Newly Registered');
    await page.getByLabel('Email address').fill('e2e-registered@example.test');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page.getByRole('heading', { name: 'Your workspace' })).toBeVisible();
    await page.getByRole('button', { name: 'Account & security' }).click();
    await expect(page.getByText('Email not verified')).toBeVisible();
});

test('owner can create projects and Kanban tasks and move them via accessible status control', async ({ page }) => {
    await signIn(page, owner);
    await page.getByRole('button', { name: 'New project' }).click();
    const project = page.getByRole('dialog', { name: 'Create project' });
    await project.getByLabel('Project name').fill('Browser QA Project');
    await project.getByLabel('Description').fill('Browser coverage');
    await project.getByRole('button', { name: 'Create project' }).click();
    await expect(page.getByRole('heading', { name: 'Browser QA Project' })).toBeVisible();

    await page.getByRole('button', { name: 'Add task', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Create a task' });
    await dialog.getByLabel('Task title').fill('Browser QA task');
    await dialog.getByLabel('Priority').selectOption('HIGH');
    await dialog.getByRole('button', { name: 'Create task' }).click();

    await expect(page.locator('section[aria-label="To do tasks"]').getByRole('button', { name: 'Browser QA task' })).toBeVisible();
    await page.getByRole('combobox', { name: 'Status of Browser QA task' }).selectOption('IN_PROGRESS');
    await expect(page.locator('section[aria-label="In progress tasks"]').getByRole('button', { name: 'Browser QA task' })).toBeVisible();

    // Editing through the detail form exercises the same v2 tasks API.
    await page.getByRole('button', { name: 'Browser QA task' }).click();
    const edit = page.getByRole('dialog', { name: 'Edit task' });
    await edit.getByLabel('Task title').fill('Browser QA task edited');
    await edit.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByRole('button', { name: 'Browser QA task edited' })).toBeVisible();
});

test('verified owner can queue invitation; viewer cannot edit tasks or invite people', async ({ page }) => {
    await signIn(page, owner);
    await page.getByRole('button', { name: 'Team & invitations' }).click();
    await expect(page.getByRole('heading', { name: 'Invite a teammate' })).toBeVisible();
    await page.getByLabel('Email', { exact: true }).fill('e2e-colleague@example.test');
    await page.getByLabel('Access').selectOption('viewer');
    await page.getByRole('button', { name: 'Send invitation' }).click();
    await expect(page.getByRole('status')).toContainText('Invitation queued for delivery.');
    await expect(page.getByText('e2e-colleague@example.test')).toBeVisible();

    // A separate web session has only viewer access to the seeded project.
    const guestPage = await page.context().browser()!.newPage();
    try {
        await signIn(guestPage, viewer);
        await expect(guestPage.getByRole('heading', { name: 'E2E Launch Project' })).toBeVisible();
        await expect(guestPage.getByRole('button', { name: 'Add task', exact: true })).toHaveCount(0);
        await guestPage.getByRole('button', { name: 'Team & invitations' }).click();
        await expect(guestPage.getByRole('heading', { name: 'Invite a teammate' })).toHaveCount(0);
    } finally {
        await guestPage.close();
    }
});

test('verification and invitation acceptance work through the real browser/API', async ({ page }) => {
    await signIn(page, invitee);
    await page.getByRole('button', { name: 'Account & security' }).click();
    await expect(page.getByText('Email not verified')).toBeVisible();
    await page.getByLabel('Verification token').fill('v'.repeat(80));
    await page.getByRole('button', { name: 'Verify email', exact: true }).click();
    await expect(page.getByText('Email verified', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Team & invitations' }).click();
    await page.getByLabel('Invitation token').fill('i'.repeat(80));
    await page.getByRole('button', { name: /Accept invitation/ }).click();
    await expect(page.getByRole('status')).toContainText('Invitation accepted');
    await page.getByRole('button', { name: 'Overview & board' }).click();
    await expect(page.getByRole('heading', { name: 'E2E Launch Project' })).toBeVisible();
});

test('unverified user can request verification without sending real email', async ({ page }) => {
    await signIn(page, pending);
    await page.getByRole('button', { name: 'Account & security' }).click();
    await page.getByRole('button', { name: 'Send verification email' }).click();
    await expect(page.getByRole('status')).toContainText('Verification message queued.');
});


test('email change revokes the current authenticated browser session', async ({ page }) => {
    await page.goto('/app');
    await page.getByRole('button', { name: 'Create an account' }).click();
    await page.getByLabel('Full name').fill('E2E Email Change');
    await page.getByLabel('Email address').fill('e2e-changed-start@example.test');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page.getByRole('button', { name: 'Sign out' })).toBeVisible();

    await page.getByRole('button', { name: 'Account & security' }).click();
    await page.getByLabel('New email').fill('e2e-changed-end@example.test');
    await page.getByLabel('Current password').fill(password);
    page.once('dialog', dialog => void dialog.accept());
    await page.getByRole('button', { name: 'Update email' }).click();
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();

    const response = await page.request.get('/app/api/session');
    expect((await response.json()).data).toBeNull();
});

test('mobile navigation and Kanban scrolling remain usable on narrow viewports', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await signIn(page, owner);
    await expect(page.getByRole('button', { name: 'Open navigation' })).toBeVisible();
    await page.getByRole('button', { name: 'Open navigation' }).click();
    await expect(page.locator('.sidebar')).toHaveClass(/sidebar-open/);
    await page.getByRole('button', { name: 'Team & invitations' }).click();
    await expect(page.locator('.sidebar')).not.toHaveClass(/sidebar-open/);
    await page.getByRole('button', { name: 'Open navigation' }).click();
    await page.getByRole('button', { name: 'Overview & board' }).click();
    await expect(page.locator('section[aria-label="To do tasks"]')).toBeVisible();
    const noBodyOverflow = await page.evaluate(() =>
        document.documentElement.scrollWidth <= window.innerWidth + 2,
    );
    expect(noBodyOverflow).toBeTruthy();
});
