import { test as teardown } from '@playwright/test';

teardown('cleanup', async () => {
  // Intentionally minimal — leave test data for debugging.
  // Run `npm run seed:reset` to fully clean up.
  console.log('E2E tests complete. Run `npm run seed:reset` to clean up test data.');
});
