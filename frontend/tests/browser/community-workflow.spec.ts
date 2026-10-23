import {test,expect,type Page} from '@playwright/test';
import {randomUUID} from 'node:crypto';
import {resetQaRateLimits} from './qa-throttles';

test.beforeEach(resetQaRateLimits);
async function login(page:Page){
    await page.goto('/login');
    await page.getByLabel('Email address').fill('qa-sender@example.test');
    await page.getByLabel('Password',{exact:true}).fill('Botanical12345');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    await expect(page).toHaveURL(/workspace/);
}
test('Workspace keeps text and actual photo files across navigation, separates chats, and clears sent drafts',async({page})=>{
    await login(page);
    const prompt='Identify my draft '+randomUUID();
    await page.getByRole('textbox',{name:'Message PlantifierAI'}).fill(prompt);
    await page.locator('input[type=file]').setInputFiles('tests/browser/fixture-plant.jpg');
    await page.locator('.nav-link').filter({hasText:'Community'}).click();
    await expect(page.getByRole('button',{name:'Help',exact:true})).toBeVisible();
    await page.locator('.nav-link').filter({hasText:'AI Workspace'}).click();
    await expect(page.getByRole('textbox',{name:'Message PlantifierAI'})).toHaveValue(prompt);
    await expect(page.locator('.image-preview')).toHaveCount(1);
    await expect(page.locator('.image-preview img')).toBeVisible();
    await page.locator('.recent-chat').first().click();
    await expect(page.getByRole('textbox',{name:'Message PlantifierAI'})).toHaveValue('');
    await page.getByRole('textbox',{name:'Message PlantifierAI'}).fill('Separate conversation draft');
    await page.locator('.nav-link').filter({hasText:'AI Workspace'}).click();
    await expect(page.getByRole('textbox',{name:'Message PlantifierAI'})).toHaveValue(prompt);
    let submitted='';await page.route('**/chat',async route=>{submitted=route.request().postData()??'';await route.fulfill({contentType:'application/json',body:JSON.stringify({url:'/workspace'})})});
    await page.getByRole('button',{name:'Send message',exact:true}).click();
    await expect(page.getByRole('textbox',{name:'Message PlantifierAI'})).toHaveValue('');
    await expect(page.locator('.image-preview')).toHaveCount(0);
    expect(submitted).toContain(prompt);expect(submitted).toContain('fixture-plant.jpg');
    await page.locator('.nav-link').filter({hasText:'Community'}).click();
    await page.locator('.nav-link').filter({hasText:'AI Workspace'}).click();
    await expect(page.getByRole('textbox',{name:'Message PlantifierAI'})).toHaveValue('');
});

test('Community topics, reaction palette, share links, responsive spacing and reduced motion',async({page,context},testInfo)=>{
    await login(page);
    await page.locator('.nav-link').filter({hasText:'Community'}).click();
    await page.getByRole('button',{name:'Share a discovery',exact:true}).click();
    await page.getByRole('combobox',{name:'Post type'}).click();
    await page.getByRole('option',{name:'Growth update',exact:true}).click();
    const body='Growth update '+randomUUID();
    await page.getByLabel('What did you notice?').fill(body);
    await page.getByRole('button',{name:'Publish discovery',exact:true}).click();
    const post=page.locator('.feed-post').filter({hasText:body});
    await expect(post.locator('.post-topic')).toHaveText('Growth update');
    const picker=post.locator(':scope > .post-social-actions > .reaction-picker');
    await picker.locator('.reaction-trigger').focus();await page.keyboard.press('Enter');
    await expect(picker.locator('.reaction-palette')).toBeVisible();
    await page.keyboard.press('Tab');await page.keyboard.press('Enter');
    await expect(picker.locator('.reaction-trigger')).toContainText('Fascinating');
    await picker.locator('.reaction-trigger').hover();
    await expect(picker.locator('.reaction-emoji').first()).toHaveCSS('animation-name','emoji-sway');
    await page.keyboard.press('Escape');
    await expect(picker.locator('.reaction-palette')).toBeHidden();
    await post.getByRole('button',{name:'Share',exact:true}).click();
    const link=await page.getByLabel('Post link').inputValue();expect(link).toMatch(/\/community\?post=\d+$/);
    await context.grantPermissions(['clipboard-read','clipboard-write']);
    await page.getByRole('button',{name:'Copy link',exact:true}).click();
    await expect(page.getByRole('button',{name:'Copied',exact:true})).toBeVisible();
    expect(await page.evaluate(()=>navigator.clipboard.readText())).toBe(link);
    await page.keyboard.press('Escape');await page.goto(link);
    await expect(page.locator('.feed-post')).toHaveCount(1);await expect(page.locator('.post-body')).toHaveText(body);
    for(const url of ['/community','/community?tab=following','/community?tab=stories','/community?tab=requests','/people?tab=friends','/people?tab=requests','/people?tab=suggestions']){
        await page.goto(url);await expect(page.getByRole('button',{name:'Help',exact:true})).toBeVisible();
        // The page slides in; measure after the animation so a mid-transform offset is not counted.
        await page.locator('.page-content').evaluate(el=>Promise.all(el.getAnimations({subtree:true}).map(animation=>animation.finished)));
        expect(await page.locator('.page-heading').evaluate(el=>Math.round(el.getBoundingClientRect().left))).toBe(await page.locator('.page-content').evaluate(el=>Math.round(el.getBoundingClientRect().left)));
    }
    await page.emulateMedia({reducedMotion:'reduce'});await page.goto('/community');
    await expect(page.locator('.community-page-motion')).toHaveCSS('animation-name','none');
    for(const width of [1440,900,390]){
        await page.setViewportSize({width,height:1000});
        expect(await page.evaluate(()=>document.body.scrollWidth<=window.innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('community-'+width+'.png')});
    }
});

test('Map search controls, click-to-place, geolocation, manual fallback and private save',async({page,context},testInfo)=>{
    await context.grantPermissions(['geolocation']);await context.setGeolocation({latitude:14.28,longitude:121.42});
    await login(page);
    // Create an owned record through the existing UI to enable adding a location.
    await page.goto('/collection');
    await page.getByRole('button',{name:/Add a plant/}).click();
    await page.getByLabel('Common or personal name').fill('Map picker fern '+randomUUID().slice(0,6));
    await page.getByRole('button',{name:'Save record',exact:true}).click();
    await page.goto('/map');await expect(page.getByLabel('Search discoveries')).toBeVisible();
    await page.getByRole('button',{name:'Add a location',exact:true}).click();
    const modal=page.getByRole('dialog');
    await expect(modal.getByRole('button',{name:'Save location',exact:true})).toBeDisabled();
    await modal.getByLabel('Choose a location on the map').click({position:{x:150,y:120}});
    await expect(modal.locator('.chosen-map-pin')).toBeVisible();
    await expect(modal.getByRole('button',{name:'Save location',exact:true})).toBeEnabled();
    await modal.getByRole('button',{name:'Use my location',exact:true}).click();
    await modal.getByText('Enter coordinates manually',{exact:true}).click();
    await expect(modal.getByLabel('Latitude',{exact:true})).toHaveValue('14.28');
    await expect(modal.getByLabel('Longitude',{exact:true})).toHaveValue('121.42');
    const title='QA chosen location '+randomUUID().slice(0,6);
    await modal.getByLabel('Location title').fill(title);
    await expect(modal.getByRole('combobox',{name:'Who can see this location?'})).toContainText('Only me');
    await page.screenshot({path:testInfo.outputPath('location-picker.png')});
    await modal.getByRole('button',{name:'Save location',exact:true}).click();
    await expect(page.locator('.pin-preview').filter({hasText:title})).toBeVisible();
    await page.getByLabel('Search discoveries').fill(title);
    await page.getByRole('button',{name:'Search',exact:true}).click();await expect(page.locator('.pin-preview')).toHaveCount(1);
    await page.setViewportSize({width:390,height:844});await expect.poll(()=>page.locator('#main-navigation').evaluate(element=>element.getBoundingClientRect().right)).toBeLessThanOrEqual(1);expect(await page.evaluate(()=>document.body.scrollWidth<=window.innerWidth)).toBe(true);
    await page.screenshot({path:testInfo.outputPath('map-mobile.png')});
});
