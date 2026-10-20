import type {FullConfig} from '@playwright/test';

export default async function globalSetup(config:FullConfig){
    const baseURL=config.projects[0].use.baseURL;
    if(baseURL!=='http://127.0.0.1:8025')throw new Error('Browser tests require the isolated QA server on port 8025.');
    const response=await fetch(baseURL+'/login');
    if(response.headers.get('x-plantifier-qa')!=='amaru-sqlite')throw new Error('QA isolation is not verified. Start scripts/Start-AmaruQA.ps1; never run these tests against the working database.');
}
