import {resetQaRateLimits} from './qa-throttles';
test.beforeEach(resetQaRateLimits);
import {test,expect,type Page} from '@playwright/test';
import {randomUUID} from 'node:crypto';

async function login(page:Page,role='sender'){
    await page.goto('/login');
    await page.getByLabel('Email address').fill('qa-'+role+'@example.test');
    await page.getByLabel('Password',{exact:true}).fill('Botanical12345');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    await expect(page).toHaveURL(/workspace/);
}
async function select(page:Page,label:string,option:string){await page.getByRole('combobox',{name:label,exact:true}).click();await page.getByRole('option',{name:option,exact:true}).click()}
async function json(page:Page,url:string,data:Record<string,unknown>){return page.evaluate(async({url,data})=>{const token=decodeURIComponent(document.cookie.split('; ').find(cookie=>cookie.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=')??'');const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify(data)});return {status:response.status,data:await response.json()}},{url,data})}

test('settings password modal, local city review, preferences and full history',async({page,context})=>{
    await login(page);
    await page.goto('/settings');
    await page.getByRole('button',{name:'Change password',exact:true}).click();
    await expect(page.getByRole('dialog',{name:'Change password'})).toBeVisible();
    await page.getByLabel('Current password',{exact:true}).fill('Wrong123456');
    await page.getByLabel('New password',{exact:true}).fill('NewBotanical12345');
    await page.getByLabel('Confirm new password',{exact:true}).fill('NewBotanical12345');
    await page.getByRole('dialog').getByRole('button',{name:'Change password',exact:true}).click();
    await expect(page.getByRole('dialog').getByRole('alert')).toBeVisible();
    await page.keyboard.press('Escape');
    await page.getByLabel('Display name',{exact:true}).fill('Name 123');
    await expect(page.getByLabel('Display name',{exact:true})).toHaveAttribute('aria-invalid','true');
    await page.getByLabel('Display name',{exact:true}).fill('QA Sender');
    await context.grantPermissions(['geolocation']);await context.setGeolocation({latitude:14,longitude:121});
    await page.route('**/location/municipality',route=>route.fulfill({json:{municipality:'Santa Cruz, Laguna',attribution:'OpenStreetMap'}}));
    await page.getByLabel('Municipality or city · optional',{exact:true}).fill('Manual City');
    await page.getByRole('button',{name:'Use my current location',exact:true}).click();
    await expect(page.getByText('Suggested city: Santa Cruz, Laguna.',{exact:false})).toBeVisible();
    await expect(page.getByLabel('Municipality or city · optional',{exact:true})).toHaveValue('Manual City');
    await page.getByRole('button',{name:'Use this city',exact:true}).click();
    await select(page,'Purpose','Grow');await select(page,'Experience','Experienced');
    await page.getByRole('button',{name:'Save changes',exact:true}).click();
    await expect(page.getByRole('status')).toContainText('Settings saved.');
    await page.reload();await expect(page.getByRole('combobox',{name:'Purpose',exact:true})).toContainText('Grow');
    await page.goto('/workspace');await expect(page.getByRole('link',{name:'Open your collection',exact:true})).toBeVisible();
    await page.goto('/workspace/history');
    await page.getByLabel('Search all conversation titles').fill('Old orchid field study');
    await page.getByRole('button',{name:'Search history',exact:true}).click();await expect(page.locator('.history-row')).toHaveCount(1);
    await expect(page.locator('.history-row').filter({hasText:'Old orchid field study'})).toBeVisible();
    await page.locator('.history-row').filter({hasText:'Old orchid field study'}).getByRole('button',{name:'Archive',exact:true}).click();
    await expect(page.getByText('No matching conversations',{exact:true})).toBeVisible();
    await page.locator('.heading-actions summary').click();await select(page,'Status','Archived');
    await page.getByRole('button',{name:'Apply filters',exact:true}).click();
    await expect(page.getByRole('button',{name:'Restore',exact:true})).toBeVisible();
    await page.getByRole('button',{name:'Restore',exact:true}).click();
    await page.locator('.heading-actions summary').click();await page.getByRole('button',{name:'Reset to defaults',exact:true}).click();await expect(page.locator('.history-row')).toHaveCount(20);
});

test('notes save images, keep draft on AI cancel, preview apply and undo',async({page})=>{
    await login(page);await page.goto('/notes');await page.getByRole('button',{name:'New note',exact:true}).click();
    const title='QA note '+randomUUID().slice(0,6);
    await page.getByRole('textbox',{name:'Note title'}).fill(title);
    await page.getByRole('textbox',{name:'Note body'}).fill('Plant measured 12 cm on October 20.');
    await page.route('**/notes/assist',route=>route.fulfill({json:{text:'The plant measured 12 cm on October 20.'}}));
    await page.getByRole('button',{name:'Polish writing',exact:true}).click();
    await expect(page.getByRole('dialog',{name:'Review writing suggestion'})).toBeVisible();
    await page.getByRole('dialog').getByRole('button',{name:'Cancel',exact:true}).click();
    await expect(page.getByRole('textbox',{name:'Note body'})).toHaveValue('Plant measured 12 cm on October 20.');
    await page.getByRole('button',{name:'Polish writing',exact:true}).click();await page.getByRole('button',{name:'Apply suggestion',exact:true}).click();
    await expect(page.getByRole('textbox',{name:'Note body'})).toHaveValue('The plant measured 12 cm on October 20.');
    await page.getByRole('button',{name:'Undo AI edit',exact:true}).click();
    await page.locator('input[type=file]').setInputFiles('tests/browser/fixture-plant.jpg');
    await page.getByRole('button',{name:'Save note',exact:true}).click();
    await expect(page.getByRole('status').filter({hasText:'Note saved.'})).toBeVisible();
    await page.reload();await page.getByRole('button').filter({hasText:title}).click();
    await expect(page.locator('.note-images img')).toHaveCount(1);
    await page.getByRole('textbox',{name:'Note body'}).fill('Preserved image after a text edit.');await page.getByRole('button',{name:'Save note',exact:true}).click();
    await expect(page.locator('.note-images img')).toHaveCount(1);
});

test('long timeline footer and responsive workspace remain usable',async({page},testInfo)=>{
    await login(page);await page.goto('/workspace/history?q=QA%20long%20timeline&status=all');
    await page.locator('.history-row').getByRole('link').first().click();await expect(page).toHaveURL(/\/workspace\/\d+/);
    for(const width of [1440,1920,900,390]){
        await page.setViewportSize({width,height:650});await page.reload();
        await expect(page.getByRole('textbox',{name:'Message PlantifierAI'})).toBeVisible();
        expect(await page.evaluate(()=>document.body.scrollWidth<=window.innerWidth)).toBe(true);
        if(width>=1051){
            const footer=await page.locator('.timeline-bottom').boundingBox();expect(footer).not.toBeNull();expect(footer!.y+footer!.height).toBeLessThanOrEqual(651);
            expect(await page.locator('.timeline-events').evaluate(element=>element.scrollHeight>element.clientHeight)).toBe(true);
            await page.locator('.timeline-events li').last().scrollIntoViewIfNeeded();await expect(page.locator('.timeline-bottom')).toBeVisible();
        }
        await page.screenshot({path:testInfo.outputPath('workspace-'+width+'.png')});
    }
    await page.getByRole('button',{name:'Help',exact:true}).click();await expect(page.getByRole('dialog',{name:'About your AI Workspace'})).toBeVisible();await page.keyboard.press('Escape');
});
test('sidebar chat filters stay fully visible and Chat History opens complete results',async({page},testInfo)=>{
    await login(page);
    for(const width of [1440,1920,900,390]){
        await page.setViewportSize({width,height:650});await page.goto('/workspace');
        if(width<801)await page.getByRole('button',{name:'Open navigation',exact:true}).click();
        const trigger=page.locator('.sidebar summary[aria-label="Filter conversations"]');
        await trigger.click();
        const panel=page.getByRole('region',{name:'Conversation filters',exact:true});await expect(panel).toBeVisible();
        const bounds=await panel.boundingBox();expect(bounds).not.toBeNull();expect(bounds!.x).toBeGreaterThanOrEqual(0);expect(bounds!.y).toBeGreaterThanOrEqual(0);expect(bounds!.x+bounds!.width).toBeLessThanOrEqual(width);expect(bounds!.y+bounds!.height).toBeLessThanOrEqual(651);
        expect(await panel.evaluate(element=>{const rect=element.getBoundingClientRect();return [[rect.left+12,rect.top+12],[rect.right-12,rect.bottom-12]].every(([x,y])=>element.contains(document.elementFromPoint(x,y)))})).toBe(true);
        for(const label of ['Status','Last activity','Group by','Sort by'])await expect(panel.getByRole('combobox',{name:label,exact:true})).toBeVisible();
        await panel.getByRole('combobox',{name:'Status',exact:true}).click();await page.getByRole('option',{name:'All',exact:true}).click();await expect(panel.getByRole('combobox',{name:'Status',exact:true})).toContainText('All');await expect(panel).toBeVisible();
        await panel.getByRole('button',{name:'Reset to defaults',exact:true}).scrollIntoViewIfNeeded();await page.screenshot({path:testInfo.outputPath('sidebar-filters-'+width+'.png')});
        await page.keyboard.press('Escape');await expect(panel).toHaveCount(0);await expect(trigger).toBeFocused();
        await trigger.click();await panel.getByRole('combobox',{name:'Last activity',exact:true}).click();await page.keyboard.press('Escape');await expect(panel).toBeVisible();await page.keyboard.press('Escape');await expect(panel).toHaveCount(0);
    }
    await page.getByRole('link',{name:'Chat History',exact:true}).click();await expect(page).toHaveURL(/\/workspace\/history/);await expect(page.getByRole('heading',{name:'Chat history',exact:true})).toBeVisible();
});