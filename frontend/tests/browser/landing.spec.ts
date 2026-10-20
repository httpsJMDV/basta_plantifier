import {resetQaRateLimits} from './qa-throttles';
test.beforeEach(resetQaRateLimits);
import {test,expect} from '@playwright/test';

test('public landing page has working navigation, responsive layouts and accessible mobile controls',async({page},testInfo)=>{
    const errors:string[]=[];page.on('pageerror',error=>errors.push(error.message));
    await page.goto('/');
    await expect(page.getByRole('heading',{level:1})).toHaveText('Every planthas a story.Discover it.');
    await expect(page).toHaveTitle(/Every plant has a story/);
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link',{name:'Skip to content'})).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
    expect(await page.locator('main').count()).toBe(1);
    const anchors=await page.locator('a[href^="#"]').evaluateAll(links=>links.map(link=>link.getAttribute('href')!.slice(1)));
    for(const anchor of anchors){await expect(page.locator('[id="'+anchor+'"]')).toHaveCount(1)}
    for(const width of [1920,1440,768,390,360]){
        await page.setViewportSize({width,height:1000});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await expect(page.getByRole('heading',{level:1})).toBeVisible();
        if(width===1440||width===390){
            await page.screenshot({path:testInfo.outputPath('landing-'+width+'.png')});
        }
    }
    const toggle=page.locator('.landing-menu-toggle');
    await toggle.click();
    const nav=page.getByRole('navigation',{name:'Main navigation'});
    await expect(nav).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded','true');
    await page.keyboard.press('Escape');
    await expect(nav).toBeHidden();await expect(toggle).toBeFocused();
    await toggle.click();await nav.getByRole('link',{name:'Features',exact:true}).click();
    await expect(page).toHaveURL(/#features$/);await expect(nav).toBeHidden();
    await page.emulateMedia({reducedMotion:'reduce'});
    await expect(page.locator('.landing-button').first()).toHaveCSS('transition-duration','0s');
    await page.setViewportSize({width:1440,height:1000});
    for(const section of ['features','privacy','about','contact']){await page.locator('#'+section).scrollIntoViewIfNeeded()}
    const images=page.locator('.landing-page img');
    for(const image of await images.all()){
        await image.scrollIntoViewIfNeeded();await expect(image).toHaveJSProperty('complete',true);
        expect(await image.evaluate(image=>(image as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
        await expect(image).toHaveAttribute('alt',/.+/);
    }
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.screenshot({path:testInfo.outputPath('landing-desktop-full.png'),fullPage:true});
    await page.setViewportSize({width:390,height:844});
    await page.screenshot({path:testInfo.outputPath('landing-mobile-full.png'),fullPage:true});
    await page.goto('/');
    await page.getByRole('link',{name:'Get Started',exact:true}).last().click();
    await expect(page).toHaveURL(/\/register$/);
    await page.goto('/');await page.getByRole('link',{name:'Log In',exact:true}).last().click();
    await expect(page).toHaveURL(/\/login$/);
    expect(errors).toEqual([]);
});
