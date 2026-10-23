import {test,expect,type Page} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {createHmac,randomUUID} from 'node:crypto';
import {resetQaRateLimits} from './qa-throttles';
const backend=fileURLToPath(new URL('../../../backend/',import.meta.url));
test.beforeEach(()=>{resetQaRateLimits();execFileSync('php',['artisan','db:seed','--class=AdminQaSeeder','--env=amaru-qa','--force','--no-interaction'],{cwd:backend,stdio:'pipe'})});
function otp(){const alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';const bits=[...'JBSWY3DPEHPK3PXP'].map(char=>alphabet.indexOf(char).toString(2).padStart(5,'0')).join('');const key=Buffer.from(bits.match(/.{8}/g)!.map(byte=>parseInt(byte,2)));const counter=Buffer.alloc(8);counter.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));const hash=createHmac('sha1',key).update(counter).digest();return ((hash.readUInt32BE(hash[19]&15)&0x7fffffff)%1000000).toString().padStart(6,'0')}
async function signIn(page:Page,role='admin'){await page.goto('/login');await page.getByLabel('Email address').fill('qa-'+role+'@example.test');await page.getByLabel('Password',{exact:true}).fill('Botanical12345');await page.getByRole('button',{name:'Sign in',exact:true}).click();await expect(page).toHaveURL(role==='admin'?/\/admin/:/workspace/)}
// Signs in the Super Admin. Returns true when the server requires step-up checks (ADMIN_MFA on); local mode skips them.
async function admin(page:Page){await signIn(page);if(!/admin\/mfa/.test(page.url())){await expect(page).toHaveURL(/\/admin$/);return false}await page.getByLabel('Authentication or recovery code').fill(otp());await page.getByLabel('Account password').fill('Botanical12345');await page.getByRole('button',{name:'Verify & continue',exact:true}).click();await expect(page).toHaveURL(/\/admin$/);return true}
async function confirm(page:Page,stepUp:boolean){const field=page.getByLabel('Confirm your password',{exact:true});if(stepUp)await field.fill('Botanical12345');else await expect(field).toHaveCount(0)}
async function stepUpDialog(page:Page,stepUp:boolean,label:string){if(!stepUp)return;const dialog=page.getByRole('dialog');await dialog.getByLabel('Confirm your password',{exact:true}).fill('Botanical12345');await dialog.getByRole('button',{name:label,exact:true}).click()}

test('admin navigation, filters and responsive sections expose only permitted summaries',async({page},testInfo)=>{
 const stepUp=await admin(page);await expect(page.getByRole('heading',{name:'Overview',exact:true})).toBeVisible();
 await expect(page.locator('.admin-security-label')).toHaveCount(stepUp?1:0);await expect(page.locator('.admin-verify-link')).toHaveCount(stepUp?1:0);
 const sections=['dashboard','users','reports','maps','ai','providers','audit','settings'];
 for(const section of sections){await page.goto('/admin?section='+section);await expect(page.locator('.admin-panel').first()).toBeVisible();expect(await page.evaluate(()=>document.body.scrollWidth<=innerWidth)).toBe(true)}
 await page.goto('/admin?section=maps');await expect(page.getByText('QA admin private location',{exact:true})).toHaveCount(0);
 await expect(page.getByText('QA admin sensitive location',{exact:true})).toBeVisible();
 await page.screenshot({path:testInfo.outputPath('admin-public-locations.png')});
 await page.goto('/admin?section=users');await expect(page.locator('.admin-filter-reset')).toHaveCount(0);await page.getByRole('textbox',{name:'Search admin records'}).fill('qa-sender@example.test');await page.getByRole('button',{name:'Apply filters',exact:true}).click();await expect(page.locator('.admin-table tbody tr')).toHaveCount(1);
 await page.locator('.admin-filter-reset').click();await expect(page).toHaveURL(/section=users$/);
 await page.goto('/admin?section=audit');await page.getByLabel('From date').fill('2026-01-01');await page.getByLabel('To date').fill('2099-12-31');await page.getByRole('button',{name:'Apply filters',exact:true}).click();await expect(page).toHaveURL(/date_from=2026-01-01/);await expect(page.locator('.admin-filter-reset')).toBeVisible();
 for(const width of [1440,1920,900,390]){await page.setViewportSize({width,height:900});await page.goto('/admin');expect(await page.evaluate(()=>document.body.scrollWidth<=innerWidth)).toBe(true);await page.screenshot({path:testInfo.outputPath('admin-overview-'+width+'.png')})}
 for(const section of sections){await page.goto('/admin?section='+section);expect(await page.evaluate(()=>document.body.scrollWidth<=innerWidth)).toBe(true)}
 await page.getByRole('button',{name:'Open admin menu',exact:true}).click();await page.getByRole('navigation',{name:'Admin sections'}).getByRole('link',{name:'Moderation',exact:true}).click();await expect(page.locator('.admin-nav-link.is-active')).toContainText('Moderation');await page.locator('.admin-table-scroll').evaluate(el=>el.scrollLeft=el.scrollWidth);expect(await page.evaluate(()=>document.body.scrollWidth<=innerWidth)).toBe(true);
});

test('warning delivery, step-up retry without losing decision, and protected account review',async({page,browser})=>{
 const recipientContext=await browser.newContext(),recipient=await recipientContext.newPage();
 try{
  const stepUp=await admin(page);await signIn(recipient,'receiver');await page.goto('/admin?section=users&q=qa-receiver%40example.test');await page.getByRole('button',{name:'Review',exact:true}).click();
  const warning='QA account notice '+randomUUID();await page.getByLabel('Reason',{exact:true}).fill(warning);
  if(stepUp){
   await page.getByLabel('Confirm your password',{exact:true}).fill('wrong');await page.getByRole('button',{name:'Apply account action',exact:true}).click();await expect(page.getByRole('alert')).toContainText('password');
   await page.getByLabel('Confirm your password',{exact:true}).fill('Botanical12345');let first=true;await page.route('**/admin/users/*',async route=>{if(route.request().method()==='POST'&&first){first=false;await route.fulfill({status:403,contentType:'application/json',body:JSON.stringify({message:'Admin MFA verification is required.'})})}else await route.continue()});
   await page.getByRole('button',{name:'Apply account action',exact:true}).click();await expect(page.getByRole('alert')).toContainText('MFA');await expect(page.getByLabel('Reason',{exact:true})).toHaveValue(warning);
   const popupPromise=page.waitForEvent('popup');await page.getByRole('dialog').getByRole('link',{name:/Verify identity in another tab/}).click();const popup=await popupPromise;await popup.getByLabel('Authentication or recovery code').fill('qa-admin-recovery-only');await popup.getByLabel('Account password').fill('Botanical12345');await popup.getByRole('button',{name:'Verify & continue',exact:true}).click();await expect(popup).toHaveURL(/\/admin$/);await popup.close();
  }else await confirm(page,false);
  await page.getByRole('button',{name:'Apply account action',exact:true}).click();await expect(page.getByRole('dialog')).toHaveCount(0);await recipient.reload();await expect(recipient.locator('.account-warning').filter({hasText:warning})).toBeVisible();await recipient.locator('.account-warning').filter({hasText:warning}).getByRole('button',{name:'Acknowledge',exact:true}).click();await expect(recipient.locator('.account-warning').filter({hasText:warning})).toHaveCount(0);
  await page.goto('/admin?section=users&q=qa-admin%40example.test');await page.getByRole('button',{name:'Review',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('singleton Super Admin account is protected');await expect(page.getByRole('button',{name:'Apply account action',exact:true})).toHaveCount(0);
 }finally{await recipientContext.close()}
});

test('reported-item review, public-map action, correction and announcement publishing work end to end',async({page,browser},testInfo)=>{
 const stepUp=await admin(page);
 await page.goto('/admin?section=reports&type=post&q=QA%20admin%20post%20review');await page.getByRole('button',{name:'Review item',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('QA admin review discovery');await page.getByRole('combobox',{name:'Decision',exact:true}).click();await page.getByRole('option',{name:'Resolved',exact:true}).click();await page.getByRole('combobox',{name:'Content action',exact:true}).click();await page.getByRole('option',{name:'Remove from public visibility',exact:true}).click();await page.getByLabel('Decision explanation').fill('QA report reviewed and the specific post removed.');await confirm(page,stepUp);await page.getByRole('button',{name:'Save decision',exact:true}).click();await expect(page.locator('.status-resolved')).toBeVisible();
 await page.goto('/admin?section=reports&type=message');await page.getByRole('button',{name:'Review item',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('QA reported message only');await expect(page.locator('.admin-evidence-image')).toBeVisible();await page.getByRole('combobox',{name:'Decision',exact:true}).click();await page.getByRole('option',{name:'Resolved',exact:true}).click();await page.getByRole('combobox',{name:'Content action',exact:true}).click();await expect(page.getByRole('option',{name:'Remove from public visibility',exact:true})).toHaveCount(0);await page.keyboard.press('Escape');await page.keyboard.press('Escape');
 await page.goto('/admin?section=maps&q=QA%20admin%20exact%20location');await page.getByRole('button',{name:'Review location',exact:true}).click();await page.getByLabel('Reason for location action').fill('QA sensitive habitat: reduce public coordinate precision.');await confirm(page,stepUp);await page.getByRole('button',{name:'Apply location action',exact:true}).click();await expect(page.locator('.status-approximate')).toBeVisible();
 await page.goto('/admin?section=ai');const correction=page.locator('.admin-table tbody tr').filter({hasText:'QA proposed species'});await correction.getByRole('button',{name:'Review',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('Jasminum sambac');await page.getByLabel('Review notes').fill('QA review complete; original identification preserved.');await confirm(page,stepUp);await page.getByRole('button',{name:'Save review',exact:true}).click();await expect(correction.locator('.status-reviewed')).toBeVisible();
 await page.goto('/admin?section=settings');const toggle=page.getByRole('switch',{name:'Pause AI requests'});
 if(await page.getByRole('button',{name:'Remove announcement',exact:true}).count()){await page.getByRole('button',{name:'Remove announcement',exact:true}).click();await stepUpDialog(page,stepUp,'Remove announcement');await expect(page.getByRole('button',{name:'Remove announcement',exact:true})).toHaveCount(0)}
 if(await toggle.getAttribute('aria-checked')==='true'){await toggle.click();await stepUpDialog(page,stepUp,'Resume AI requests');await expect(toggle).toHaveAttribute('aria-checked','false')}
 const text='QA platform announcement '+randomUUID().slice(0,6);await page.getByLabel('Announcement text').fill(text);await expect(page.locator('.admin-announcement-preview .announcement')).toHaveText(text);
 await page.getByRole('button',{name:'Publish announcement',exact:true}).click();await stepUpDialog(page,stepUp,'Publish announcement');await expect(page.getByText('Announcement published.',{exact:true})).toBeVisible();await expect(page.locator('.status-live')).toBeVisible();
 await toggle.click();await stepUpDialog(page,stepUp,'Pause AI requests');await expect(page.getByText('AI requests paused.',{exact:true})).toBeVisible();await expect(toggle).toHaveAttribute('aria-checked','true');await page.screenshot({path:testInfo.outputPath('admin-settings.png')});
 const userContext=await browser.newContext(),user=await userContext.newPage();try{await signIn(user,'sender');await expect(user.locator('.announcement')).toHaveText(text);await expect(user.getByRole('link',{name:'Admin System'})).toHaveCount(0);await user.locator('.recent-chat').filter({hasText:'QA admin correction context'}).click();await expect(user.locator('.correction-review-note')).toContainText('reviewed');await expect(user.locator('.message-body')).toContainText('QA original identification unchanged');await user.goto('/admin');await expect(user.locator('.admin-app')).toHaveCount(0)}finally{await userContext.close()}
 await page.getByRole('button',{name:'Remove announcement',exact:true}).click();await stepUpDialog(page,stepUp,'Remove announcement');await expect(page.getByText('Announcement removed.',{exact:true})).toBeVisible();await expect(page.locator('.status-not_published')).toBeVisible();
 await toggle.click();await stepUpDialog(page,stepUp,'Resume AI requests');await expect(toggle).toHaveAttribute('aria-checked','false');
});

test('AI providers manage the connection and keys, and backups save and pass the restore check',async({page},testInfo)=>{
 test.setTimeout(240000);
 const stepUp=await admin(page);
 await page.goto('/workspace');await expect(page.getByRole('link',{name:'Admin System'})).toBeVisible();await page.getByRole('link',{name:'Admin System'}).click();await expect(page).toHaveURL(/\/admin$/);
 await page.goto('/admin?section=providers');await expect(page.getByRole('heading',{name:'AI providers',exact:true})).toBeVisible();expect(await page.locator('.admin-ai-health-card').count()).toBeGreaterThanOrEqual(2);
 const claude=page.getByRole('radio',{name:/^Claude/}),groq=page.getByRole('radio',{name:/^Groq/});
 await claude.click();await expect(claude).toHaveAttribute('aria-checked','true');await page.getByRole('button',{name:'Save connection',exact:true}).click();await stepUpDialog(page,stepUp,'Save connection');await expect(page.getByText('AI connection saved. Claude now answers first.',{exact:true})).toBeVisible();await expect(page.locator('.admin-ai-current')).toContainText('Currently using Claude');
 await groq.click();await page.getByRole('button',{name:'Save connection',exact:true}).click();await stepUpDialog(page,stepUp,'Save connection');await expect(page.locator('.admin-ai-current')).toContainText('Currently using Groq');await expect(page.getByRole('button',{name:'Save connection',exact:true})).toBeDisabled();
 await page.getByRole('tab',{name:/Pl@ntNet/}).click();await expect(page.getByRole('tab',{name:/Pl@ntNet/})).toHaveAttribute('aria-selected','true');await expect(page.getByLabel('Pl@ntNet API key')).toBeVisible();
 await page.getByRole('tab',{name:/Groq/}).click();const before=await page.locator('.admin-ai-key').count();
 await page.getByLabel('Groq API key').fill('gsk_qa_definitely_not_a_real_key_0000');await page.getByRole('button',{name:'Add key',exact:true}).click();await stepUpDialog(page,stepUp,'Add key');await expect(page.getByRole('alert')).toContainText('Groq rejected this key');await expect(page.locator('.admin-ai-key')).toHaveCount(before);
 await page.screenshot({path:testInfo.outputPath('admin-ai-providers.png'),fullPage:true});
 await page.goto('/admin?section=audit');
 for(const [button,message] of [['Back up source','Source code backup saved'],['Back up database','Database backup saved'],['Back up media','Media backup saved'],['Run restore check','Restore check passed']]){await page.getByRole('button',{name:button,exact:true}).click();await stepUpDialog(page,stepUp,button);await expect(page.locator('.toast').filter({hasText:message})).toBeVisible({timeout:120000});await page.locator('.toast button').click()}
 await expect(page.locator('.admin-backup-grid .status-verified')).toBeVisible();await expect(page.locator('.admin-backup-grid .status-saved')).toHaveCount(3);
 await page.screenshot({path:testInfo.outputPath('admin-backups.png'),fullPage:true});
});
