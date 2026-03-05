import { test as setup } from '@playwright/test';
import { seedTestData } from '../fixtures/seed-data';

setup('seed test data', async () => {
  await seedTestData();
});
