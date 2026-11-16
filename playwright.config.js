const {defineConfig} = require(process.env.PRH_PLAYWRIGHT_PATH || '@playwright/test');
module.exports = defineConfig({
    testDir: './tests',
    testMatch: 'importacao.browser.cjs',
    timeout: 120000,
    workers: 1,
    use: {baseURL: 'http://127.0.0.1:8765', headless: true},
    webServer: {command: 'php -S 127.0.0.1:8765 -t .', url: 'http://127.0.0.1:8765/tests/browser-fixture.php', timeout: 20000},
    reporter: 'list',
});
