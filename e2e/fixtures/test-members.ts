/**
 * Re-exports TEST_MEMBERS for use in test files without importing
 * the entire seed module (which would trigger DB operations).
 */
export const TEST_MEMBERS = {
  individual: {
    email: process.env.TEST_MEMBER_EMAIL || 'testmember@example.com',
    password: process.env.TEST_MEMBER_PASS || 'TestPass123!',
    first: 'Test',
    last: 'Individual',
  },
  household: {
    email: process.env.TEST_HOUSEHOLD_EMAIL || 'testhousehold@example.com',
    password: process.env.TEST_HOUSEHOLD_PASS || 'TestPass123!',
    first: 'Test',
    last: 'Household',
  },
  duo: {
    email: process.env.TEST_DUO_EMAIL || 'testduo@example.com',
    password: process.env.TEST_DUO_PASS || 'TestPass123!',
    first: 'Test',
    last: 'Duo',
  },
  civic: {
    email: process.env.TEST_CIVIC_EMAIL || 'testcivic@example.com',
    password: process.env.TEST_CIVIC_PASS || 'TestPass123!',
    first: 'Test',
    last: 'Civic',
  },
  withBalance: {
    email: 'testbalance@example.com',
    password: 'TestPass123!',
    first: 'Test',
    last: 'Balance',
  },
  referrer: {
    email: 'testreferrer@example.com',
    password: 'TestPass123!',
    first: 'Test',
    last: 'Referrer',
    affiliateCode: 'REF-TESTREF-001',
  },
  inactiveReferrer: {
    email: 'testinactive@example.com',
    password: 'TestPass123!',
    first: 'Test',
    last: 'Inactive',
    affiliateCode: 'REF-INACTIVE-001',
  },
};
