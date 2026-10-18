import {defineConfig} from '@playwright/test';
export default defineConfig({testDir:'./tests/browser',fullyParallel:false,workers:1,timeout:120000,expect:{timeout:12000},reporter:[['list']],use:{baseURL:'http://127.0.0.1:8025',browserName:'chromium',channel:'msedge',headless:true,viewport:{width:1440,height:1000},trace:'retain-on-failure',screenshot:'only-on-failure'}});
