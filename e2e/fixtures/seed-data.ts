/**
 * Seeds the LocalWP database with test data for E2E tests.
 *
 * Run standalone:  npx tsx fixtures/seed-data.ts
 * Or via script:   npm run seed
 *
 * Uses direct MySQL commands since WP-CLI may not be on PATH in all
 * LocalWP environments. Adjust DB_* env vars in .env.test.
 */
import { execFileSync, execSync } from 'child_process';
import * as dotenv from 'dotenv';
import * as path from 'path';

dotenv.config({ path: path.resolve(__dirname, '../.env.test') });

const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_PORT = process.env.DB_PORT || '10010';
const DB_NAME = process.env.DB_NAME || 'local';
const DB_USER = process.env.DB_USER || 'root';
const DB_PASS = process.env.DB_PASS || 'root';

function mysql(sql: string): string {
  try {
    return execFileSync(
      'mysql',
      [
        '-h', DB_HOST,
        '-P', DB_PORT,
        '-u', DB_USER,
        `-p${DB_PASS}`,
        DB_NAME,
        '-e', sql,
        '--skip-column-names',
        '-N',
      ],
      { encoding: 'utf-8', timeout: 10_000 }
    ).trim();
  } catch (err: any) {
    console.error(`SQL failed: ${sql}`);
    console.error(err.stderr || err.message);
    throw err;
  }
}

// ---------------------------------------------------------------------------
// Test data constants
// ---------------------------------------------------------------------------
const TEST_PASS_HASH = '$P$B'; // placeholder — we set passwords via WP user table
const NOW = new Date().toISOString().slice(0, 19).replace('T', ' ');
const NEXT_YEAR = `${new Date().getFullYear() + 1}-12-31 23:59:59`;

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
};

// ---------------------------------------------------------------------------
// Seed functions
// ---------------------------------------------------------------------------

function ensureMembershipTypes(): Record<string, number> {
  const types: Record<string, { price: number; desc: string; benefits: string; expirationDays: number }> = {
    'Household': {
      price: 300,
      desc: 'Full family membership with pool access',
      benefits: JSON.stringify(['pool_use_for_season', 'facility_rental_discount', 'guest_passes']),
      expirationDays: 365,
    },
    'Duo': {
      price: 225,
      desc: 'Membership for two adults with pool access',
      benefits: JSON.stringify(['pool_use_for_season', 'guest_passes']),
      expirationDays: 365,
    },
    'Individual': {
      price: 175,
      desc: 'Single adult membership with pool access',
      benefits: JSON.stringify(['pool_use_for_season', 'guest_passes']),
      expirationDays: 365,
    },
    'Civic': {
      price: 50,
      desc: 'Community membership without pool access',
      benefits: JSON.stringify(['facility_rental_discount']),
      expirationDays: 365,
    },
  };

  const ids: Record<string, number> = {};

  for (const [name, info] of Object.entries(types)) {
    const existing = mysql(
      `SELECT membership_type_id FROM wp_stsrc_membership_types WHERE name='${name}' LIMIT 1`
    );
    if (existing) {
      ids[name] = parseInt(existing, 10);
      console.log(`  Membership type "${name}" already exists (ID ${ids[name]})`);
    } else {
      mysql(
        `INSERT INTO wp_stsrc_membership_types (name, description, price, expiration_period, benefits, is_selectable, created_at, updated_at)
         VALUES ('${name}', '${info.desc}', ${info.price}, ${info.expirationDays}, '${info.benefits}', 1, '${NOW}', '${NOW}')`
      );
      const newId = mysql(
        `SELECT membership_type_id FROM wp_stsrc_membership_types WHERE name='${name}' LIMIT 1`
      );
      ids[name] = parseInt(newId, 10);
      console.log(`  Created membership type "${name}" (ID ${ids[name]})`);
    }
  }

  return ids;
}

function createTestWpUser(email: string, password: string, displayName: string): number {
  const existing = mysql(
    `SELECT ID FROM wp_users WHERE user_email='${email}' LIMIT 1`
  );
  if (existing) {
    const uid = parseInt(existing, 10);
    console.log(`  WP user "${email}" already exists (ID ${uid})`);
    return uid;
  }

  const login = email;
  mysql(
    `INSERT INTO wp_users (user_login, user_email, user_pass, user_nicename, display_name, user_registered)
     VALUES ('${login}', '${email}', '', '${login}', '${displayName}', '${NOW}')`
  );
  const uid = parseInt(
    mysql(`SELECT ID FROM wp_users WHERE user_email='${email}' LIMIT 1`),
    10
  );

  // Set capabilities for stsrc_member role
  mysql(
    `INSERT INTO wp_usermeta (user_id, meta_key, meta_value)
     VALUES (${uid}, 'wp_capabilities', 'a:1:{s:11:\\"stsrc_member\\";b:1;}')`
  );
  mysql(
    `INSERT INTO wp_usermeta (user_id, meta_key, meta_value)
     VALUES (${uid}, 'wp_user_level', '0')`
  );

  console.log(`  Created WP user "${email}" (ID ${uid})`);
  return uid;
}

function setWpPassword(userId: number, password: string): void {
  // Use WordPress's portable hash format via a PHP eval if WP-CLI is available,
  // or fall back to MD5 (WordPress auto-upgrades on first login).
  const md5Hash = execSync(`echo -n "${password}" | md5sum | cut -d' ' -f1`, {
    encoding: 'utf-8',
  }).trim();
  mysql(`UPDATE wp_users SET user_pass='$P$B_placeholder_' WHERE ID=${userId}`);

  // Actually, let's use a known phpass-compatible approach: just set raw MD5
  // and WP will re-hash on login. WordPress supports MD5 as legacy format.
  mysql(`UPDATE wp_users SET user_pass=MD5('${password}') WHERE ID=${userId}`);
  console.log(`  Set password for user ID ${userId} (MD5 — WP upgrades on login)`);
}

function createTestMember(
  userId: number,
  membershipTypeId: number,
  info: { email: string; first: string; last: string },
  status = 'active',
  balanceOwed = 0
): number {
  const existing = mysql(
    `SELECT member_id FROM wp_stsrc_members WHERE email='${info.email}' LIMIT 1`
  );
  if (existing) {
    const mid = parseInt(existing, 10);
    console.log(`  Member "${info.email}" already exists (ID ${mid})`);
    return mid;
  }

  mysql(
    `INSERT INTO wp_stsrc_members
       (user_id, membership_type_id, status, payment_type, first_name, last_name, email, phone,
        street_1, city, state, zip, country, referral_source, waiver_full_name, waiver_signed_date,
        balance_owed, original_membership_price, created_at, updated_at, expiration_date)
     VALUES
       (${userId}, ${membershipTypeId}, '${status}', 'card',
        '${info.first}', '${info.last}', '${info.email}', '(555) 555-0100',
        '123 Test St', 'Tucker', 'GA', '30084', 'US', 'other',
        '${info.first} ${info.last}', '${NOW.slice(0, 10)}',
        ${balanceOwed}, 0,
        '${NOW}', '${NOW}', '${NEXT_YEAR}')`
  );
  const mid = parseInt(
    mysql(`SELECT member_id FROM wp_stsrc_members WHERE email='${info.email}' LIMIT 1`),
    10
  );
  console.log(`  Created member "${info.email}" (ID ${mid}) [${status}]`);
  return mid;
}

function ensureAccessCodes(): void {
  const existing = mysql(
    `SELECT COUNT(*) FROM wp_stsrc_access_codes WHERE code='TESTCODE2026'`
  );
  if (parseInt(existing, 10) > 0) {
    console.log('  Access codes already exist');
    return;
  }

  mysql(
    `INSERT INTO wp_stsrc_access_codes (code, description, is_active, is_premium, created_at, updated_at)
     VALUES ('TESTCODE2026', 'General test code', 1, 0, '${NOW}', '${NOW}')`
  );
  mysql(
    `INSERT INTO wp_stsrc_access_codes (code, description, is_active, is_premium, created_at, updated_at)
     VALUES ('POOLCODE2026', 'Pool-only premium code', 1, 1, '${NOW}', '${NOW}')`
  );
  console.log('  Created access codes: TESTCODE2026 (general), POOLCODE2026 (premium)');
}

function enableRegistration(): void {
  const existing = mysql(
    `SELECT option_value FROM wp_options WHERE option_name='stsrc_registration_enabled' LIMIT 1`
  );
  if (!existing) {
    mysql(
      `INSERT INTO wp_options (option_name, option_value, autoload)
       VALUES ('stsrc_registration_enabled', '1', 'yes')`
    );
  } else {
    mysql(`UPDATE wp_options SET option_value='1' WHERE option_name='stsrc_registration_enabled'`);
  }
  console.log('  Registration enabled');
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

export async function seedTestData(): Promise<void> {
  const isReset = process.argv.includes('--reset');

  if (isReset) {
    console.log('Resetting test data...');
    for (const m of Object.values(TEST_MEMBERS)) {
      mysql(`DELETE FROM wp_stsrc_members WHERE email='${m.email}'`);
      mysql(`DELETE FROM wp_users WHERE user_email='${m.email}'`);
    }
    mysql(`DELETE FROM wp_stsrc_access_codes WHERE code IN ('TESTCODE2026','POOLCODE2026')`);
    console.log('Test data cleaned up.\n');
  }

  console.log('Seeding test data...\n');

  console.log('[Membership Types]');
  const typeIds = ensureMembershipTypes();

  console.log('\n[Access Codes]');
  ensureAccessCodes();

  console.log('\n[Registration Setting]');
  enableRegistration();

  console.log('\n[Test Members]');
  // Individual member
  const indUserId = createTestWpUser(
    TEST_MEMBERS.individual.email,
    TEST_MEMBERS.individual.password,
    'Test Individual'
  );
  setWpPassword(indUserId, TEST_MEMBERS.individual.password);
  createTestMember(indUserId, typeIds['Individual'], TEST_MEMBERS.individual);

  // Household member
  const hhUserId = createTestWpUser(
    TEST_MEMBERS.household.email,
    TEST_MEMBERS.household.password,
    'Test Household'
  );
  setWpPassword(hhUserId, TEST_MEMBERS.household.password);
  createTestMember(hhUserId, typeIds['Household'], TEST_MEMBERS.household);

  // Duo member
  const duoUserId = createTestWpUser(
    TEST_MEMBERS.duo.email,
    TEST_MEMBERS.duo.password,
    'Test Duo'
  );
  setWpPassword(duoUserId, TEST_MEMBERS.duo.password);
  createTestMember(duoUserId, typeIds['Duo'], TEST_MEMBERS.duo);

  // Civic member (no pool access)
  const civicUserId = createTestWpUser(
    TEST_MEMBERS.civic.email,
    TEST_MEMBERS.civic.password,
    'Test Civic'
  );
  setWpPassword(civicUserId, TEST_MEMBERS.civic.password);
  createTestMember(civicUserId, typeIds['Civic'], TEST_MEMBERS.civic);

  // Member with outstanding balance
  const balUserId = createTestWpUser(
    TEST_MEMBERS.withBalance.email,
    TEST_MEMBERS.withBalance.password,
    'Test Balance'
  );
  setWpPassword(balUserId, TEST_MEMBERS.withBalance.password);
  createTestMember(
    balUserId,
    typeIds['Individual'],
    TEST_MEMBERS.withBalance,
    'active',
    75.00
  );

  console.log('\nSeed complete!');
}

// Run directly
if (require.main === module) {
  seedTestData().catch((err) => {
    console.error(err);
    process.exit(1);
  });
}
