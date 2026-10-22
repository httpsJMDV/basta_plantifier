import {resetQaRateLimits} from './qa-throttles';
test.beforeEach(resetQaRateLimits);
import {test, expect, type Page} from '@playwright/test';
import {randomUUID} from 'node:crypto';

async function register(page: Page, name: string) {
    await page.goto('/register');
    await page.getByLabel('Your name', {exact: true}).fill(name);
    await page.getByLabel('Email address').fill(randomUUID()+'@example.test');
    await page.getByLabel('Password', {exact: true}).fill('Botanical12345');
    await page.getByLabel('Confirm password').fill('Botanical12345');
    await page.getByRole('button', {name: 'Create my account'}).click();
    await expect(page).toHaveURL(/onboarding/);
    await page.getByRole('button', {name: 'Skip for now'}).click();
    await expect(page).toHaveURL(/workspace/);
}

test('a recipient on the inbox sees a new thread and message through Reverb', async ({browser, page}) => {
    const secondContext = await browser.newContext();
    const second = await secondContext.newPage();
    const suffix=randomUUID().replace(/[^a-f]/g,'').slice(0,6);
    const sender='Sender '+suffix;
    const recipient='Receiver '+suffix;
    try {
        await register(page, sender);
        await register(second, recipient);
        await second.goto('/messages');
        await page.goto('/people?tab=search&q='+encodeURIComponent(recipient));
        await page.locator('.person-card').filter({hasText: recipient}).getByRole('button', {name: 'Message '+recipient, exact: true}).click();
        await expect(page).toHaveURL(/messages\/[0-9]+/);
        await page.getByRole('textbox', {name: 'Message', exact: true}).fill('An inbox update without refreshing');
        await page.getByRole('button', {name: 'Send message', exact: true}).click();
        await expect(second.locator('.thread-preview').filter({hasText: sender})).toBeVisible({timeout: 8000});
        await second.locator('.thread-preview').filter({hasText: sender}).click();
        await expect(second.getByText('An inbox update without refreshing', {exact: true})).toBeVisible();
    } finally {
        await secondContext.close();
    }
});
