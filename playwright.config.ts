import { defineConfig, devices } from '@playwright/test';

const port = 8085;
const baseURL = 'http://127.0.0.1:' + port;

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 45_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        ...devices['Desktop Chrome'],
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=' + port,
        url: baseURL + '/app',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: { ...process.env, APP_ENV: 'local', APP_URL: baseURL },
    },
});
