import {resetQaRateLimits} from './qa-throttles';
test.beforeEach(resetQaRateLimits);
import {test,expect} from '@playwright/test';
import {randomUUID} from 'node:crypto';

test('workspace limits photo batches to three, saves attachments and remembers writing style',async({page},testInfo)=>{
    await page.goto('/register');
    await page.getByLabel('Your name',{exact:true}).fill('Photo Explorer');
    await page.getByLabel('Email address').fill('photos-'+randomUUID()+'@example.test');
    const password='Plants-'+randomUUID();
    await page.getByLabel('Password',{exact:true}).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button',{name:'Create my account'}).click();
    await expect(page.getByRole('button',{name:'Skip for now'})).toBeVisible({timeout:30000});
    await page.getByRole('button',{name:'Skip for now'}).click();
    await expect(page).toHaveURL(/workspace/,{timeout:30000});
    const buffer=Buffer.from(await page.evaluate(()=>{
        const canvas=document.createElement('canvas');canvas.width=100;canvas.height=100;
        const context=canvas.getContext('2d')!;context.fillStyle='#547344';context.fillRect(0,0,100,100);
        return canvas.toDataURL('image/png').split(',')[1];
    }),'base64');
    const photo=(name:string)=>({name,mimeType:'image/png',buffer});
    const picker=page.locator('input[type=file]');
    await picker.setInputFiles([photo('leaf.png'),photo('stem.png'),photo('flower.png')]);
    await expect(page.locator('.image-preview')).toHaveCount(3);
    await picker.setInputFiles(photo('fourth.png'));
    await expect(page.getByRole('alert')).toContainText('up to 3 photos');
    await expect(page.locator('.image-preview')).toHaveCount(3);
    await page.getByRole('button',{name:'Remove selected image 2'}).click();
    await expect(page.locator('.image-preview')).toHaveCount(2);
    await picker.setInputFiles(photo('new-stem.png'));
    const style=page.getByRole('combobox',{name:'Response style'});
    await style.click();await page.getByRole('option',{name:'Formal',exact:true}).click();
    for(const width of [1440,390]){
        await page.setViewportSize({width,height:1000});
        if(width===390){await page.getByRole('button',{name:'Hide session timeline'}).click();await expect(page.locator('#session-timeline')).toBeHidden();}
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await expect(style).toBeVisible();
        await page.screenshot({path:testInfo.outputPath('attachments-'+width+'.png')});
    }
    await page.getByRole('textbox',{name:'Message PlantifierAI'}).fill('Identify this plant from these three angles.');
    const response=page.waitForResponse(response=>response.url().endsWith('/chat')&&response.request().method()==='POST');
    await page.getByRole('button',{name:'Send message'}).click();
    expect((await response).status()).toBe(200);
    await expect(page).toHaveURL(/workspace\/\d+/,{timeout:30000});
    await expect(page.locator('.chat-image')).toHaveCount(3);
    await expect(page.locator('.image-preview')).toHaveCount(0);
    await page.reload();
    await expect(page.locator('.chat-image')).toHaveCount(3);
    await expect(style).toHaveText('Formal');
    await page.screenshot({path:testInfo.outputPath('saved-attachments-mobile.png')});
});
