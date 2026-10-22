import {test,expect,type Page,type WebSocketRoute} from '@playwright/test';
import {randomUUID} from 'node:crypto';
import {resetQaRateLimits} from './qa-throttles';
test.beforeEach(resetQaRateLimits);
async function login(page:Page,role:string){await page.goto('/login');await page.getByLabel('Email address').fill('qa-'+role+'@example.test');await page.getByLabel('Password',{exact:true}).fill('Botanical12345');await page.getByRole('button',{name:'Sign in',exact:true}).click();await expect(page).toHaveURL(/workspace/)}
async function json(page:Page,url:string,data:Record<string,unknown>){return page.evaluate(async({url,data})=>{const token=decodeURIComponent(document.cookie.split('; ').find(cookie=>cookie.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=')??'');const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify(data)});return {status:response.status,data:await response.json()}},{url,data})}
async function start(page:Page,name:string){await page.goto('/people?tab=search&q='+encodeURIComponent(name));await page.locator('.person-card').filter({hasText:name}).getByRole('button',{name:'Message '+name,exact:true}).click();await expect(page).toHaveURL(/messages\/[0-9]+/)}

test('live inbox, other thread, drafts, text/photo/card delivery, read receipts and reconnect',async({page,browser},testInfo)=>{
 const receiverContext=await browser.newContext(),thirdContext=await browser.newContext();const receiver=await receiverContext.newPage(),third=await thirdContext.newPage();let paused=false,socket:WebSocketRoute|undefined;
 await receiverContext.routeWebSocket('ws://127.0.0.1:8085/**',route=>{if(paused){void route.close({code:1012});return}socket=route;route.connectToServer()});
 try{
  await login(page,'sender');await login(receiver,'receiver');await login(third,'third');
  await start(page,'QA Receiver');const threadUrl=page.url();
  await receiver.goto(threadUrl);await expect(receiver.getByText('Live connection',{exact:true})).toBeVisible();
  await start(receiver,'QA Third');await receiver.getByRole('textbox',{name:'Message',exact:true}).fill('Keep this typed draft');
  const text='Realtime field note '+randomUUID().slice(0,6);
  await page.getByRole('textbox',{name:'Message',exact:true}).fill(text);await page.getByRole('button',{name:'Send message',exact:true}).click();
  await expect(receiver.locator('.thread-preview').filter({hasText:'QA Sender'}).locator('.unread-badge')).toHaveText('1');
  await expect(receiver.getByRole('textbox',{name:'Message',exact:true})).toHaveValue('Keep this typed draft');
  await receiver.locator('.thread-preview').filter({hasText:'QA Sender'}).click();await expect(receiver.getByText(text,{exact:true})).toBeVisible();
  await expect(page.locator('.dm.outgoing').filter({hasText:text}).getByText('Read',{exact:true})).toBeVisible();
  await page.locator('input[type=file]').setInputFiles('tests/browser/fixture-plant.jpg');await page.getByRole('button',{name:'Send message',exact:true}).click();
  await expect(receiver.locator('.dm.incoming').last().locator('img')).toBeVisible();
  const plantName='QA shared fern '+randomUUID().slice(0,6);const result=await json(page,'/plants',{common_name:plantName,relationship:'growing',description:'A selected, owned botanical record.',care:'Review watering conditions.',safety:'Do not consume based on a suggestion.'});expect(result.status).toBe(200);
  await page.reload();await page.getByRole('combobox',{name:'Attach a field card'}).click();await page.getByRole('option',{name:plantName,exact:true}).click();await page.getByRole('checkbox').check();await page.getByRole('button',{name:'Send message',exact:true}).click();
  const card=receiver.locator('.share-card').filter({hasText:plantName});await expect(card).toBeVisible();await card.click();
  const modal=receiver.getByRole('dialog',{name:plantName,exact:true});await expect(modal).toBeVisible();expect((await modal.boundingBox())!.width).toBeGreaterThan(600);await modal.getByRole('tab',{name:'Safety',exact:true}).click();await expect(modal.getByRole('tabpanel')).toContainText('Do not consume');await receiver.keyboard.press('Escape');
  await receiver.screenshot({path:testInfo.outputPath('messenger-live-cards.png')});
  const unauthorized=await json(third,'/broadcasting/auth',{socket_id:'123.456',channel_name:'private-thread.'+threadUrl.split('/').at(-1)});expect(unauthorized.status).toBe(403);
  paused=true;expect(socket).toBeDefined();await socket!.close({code:1012});await expect(receiver.getByText(/Reconnecting/)).toBeVisible({timeout:20000});
  const offline='Saved during reconnect '+randomUUID().slice(0,6);await page.getByRole('textbox',{name:'Message',exact:true}).fill(offline);await page.getByRole('button',{name:'Send message',exact:true}).click();await expect(page.getByText(offline,{exact:true})).toBeVisible();
  paused=false;await expect(receiver.getByText(offline,{exact:true})).toBeVisible({timeout:30000});await expect(receiver.getByText('Live connection',{exact:true})).toBeVisible();
  await receiver.setViewportSize({width:390,height:844});await expect.poll(()=>receiver.locator('#main-navigation').evaluate(element=>element.getBoundingClientRect().right)).toBeLessThanOrEqual(1);await expect(receiver.getByRole('button',{name:'Send message',exact:true})).toBeVisible();expect(await receiver.evaluate(()=>document.body.scrollWidth<=window.innerWidth)).toBe(true);await receiver.screenshot({path:testInfo.outputPath('messenger-mobile.png')});
  const mobileText='Mobile field note '+randomUUID().slice(0,6);const composer=receiver.getByRole('textbox',{name:'Message',exact:true});
  await composer.fill(mobileText);await composer.press('Shift+Enter');await expect(composer).toHaveValue(mobileText+'\n');await composer.press('Enter');await expect(page.getByText(mobileText,{exact:true})).toBeVisible();
  await receiver.locator('input[type=file]').setInputFiles('tests/browser/fixture-plant.jpg');await receiver.getByRole('button',{name:'Send message',exact:true}).click();await expect(page.locator('.dm.incoming').last().locator('img')).toBeVisible();
  const mobilePlant='QA mobile fern '+randomUUID().slice(0,6);expect((await json(receiver,'/plants',{common_name:mobilePlant,relationship:'growing',safety:'Review before use.'})).status).toBe(200);
  await receiver.reload();await receiver.getByRole('combobox',{name:'Attach a field card'}).click();await receiver.getByRole('option',{name:mobilePlant,exact:true}).click();await receiver.getByRole('checkbox').check();await receiver.getByRole('button',{name:'Send message',exact:true}).click();await expect(page.locator('.share-card').filter({hasText:mobilePlant})).toBeVisible();
  await receiver.locator('.share-card').filter({hasText:mobilePlant}).click();const mobileModal=receiver.getByRole('dialog',{name:mobilePlant,exact:true});await expect(mobileModal).toBeVisible();expect((await mobileModal.boundingBox())!.width).toBeLessThanOrEqual(390);await mobileModal.getByRole('tab',{name:'Safety',exact:true}).click();await expect(mobileModal.getByRole('tabpanel')).toContainText('Review before use.');await receiver.keyboard.press('Escape');await expect(mobileModal).toHaveCount(0);
 }finally{await receiverContext.close();await thirdContext.close()}
});

test('two-account post editing, persisted reactions, replies, report modal and feed layouts',async({page,browser},testInfo)=>{
 const context=await browser.newContext(),other=await context.newPage();
 try{
  await login(page,'sender');await login(other,'receiver');
  const title='QA discovery '+randomUUID().slice(0,6);expect((await json(page,'/posts',{body:title,kind:'post',area:'Laguna'})).status).toBe(200);
  await page.goto('/community');let post=page.locator('.feed-post').filter({hasText:title});await post.getByRole('button',{name:'Edit post',exact:true}).click();await page.getByLabel('Post text').fill(title+' edited');await page.getByRole('dialog').getByRole('button',{name:'Cancel',exact:true}).click();await expect(post.locator('.post-body')).toHaveText(title);
  await post.getByRole('button',{name:'Edit post',exact:true}).click();await page.getByLabel('Post text').fill(title+' edited');await page.getByRole('button',{name:'Save post',exact:true}).click();await expect(post.locator('.post-body')).toHaveText(title+' edited');
  await other.goto('/community');const viewed=other.locator('.feed-post').filter({hasText:title});await viewed.locator(':scope > .reaction-picker').getByRole('button',{name:/Fascinating/}).click();await expect(viewed.locator(':scope > .reaction-picker').getByRole('button',{name:/Fascinating/})).toHaveAttribute('aria-pressed','true');await other.reload();await expect(viewed.locator(':scope > .reaction-picker').getByRole('button',{name:/Fascinating/})).toHaveAttribute('aria-pressed','true');
  await viewed.locator(':scope > .reaction-picker').getByRole('button',{name:/Crazy/}).click();await viewed.locator(':scope > .reaction-picker').getByRole('button',{name:/Crazy/}).click();await viewed.locator(':scope > .reaction-picker').getByRole('button',{name:/Loved It/}).click();
  await viewed.getByLabel('Comment with evidence').fill('A useful field observation');await viewed.getByRole('button',{name:'Post',exact:true}).click();await expect(viewed.getByText('A useful field observation',{exact:true})).toBeVisible();await viewed.getByRole('button',{name:'Replies (0)',exact:true}).click();await viewed.getByLabel('Reply with evidence').fill('A reply with more context');await viewed.getByRole('button',{name:'Reply',exact:true}).click();await expect(viewed.getByText('A reply with more context',{exact:true})).toBeVisible();
  await viewed.locator('header').getByRole('button',{name:'Report content',exact:true}).click();await other.getByRole('dialog').getByRole('button',{name:'Cancel',exact:true}).click();await viewed.locator('header').getByRole('button',{name:'Report content',exact:true}).click();await other.getByLabel('Details',{exact:true}).fill('QA authorized report for integration testing.');await other.getByRole('button',{name:'Submit report',exact:true}).click();await expect(other.getByText('Report submitted for review.',{exact:true})).toBeVisible();
  await other.reload();await expect(viewed.getByText('A reply with more context',{exact:true})).toBeVisible();
  for(const width of [1440,1920,900,390]){await other.setViewportSize({width,height:900});if(width<801)await expect.poll(()=>other.locator('#main-navigation').evaluate(element=>element.getBoundingClientRect().right)).toBeLessThanOrEqual(1);expect(await other.evaluate(()=>document.body.scrollWidth<=window.innerWidth)).toBe(true);await other.screenshot({path:testInfo.outputPath('feed-'+width+'.png')})}
 }finally{await context.close()}
});