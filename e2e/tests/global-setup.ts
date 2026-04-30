import { test as setup } from '@playwright/test';
import { execFileSync } from 'child_process';
import * as dotenv from 'dotenv';
import * as path from 'path';
import { seedTestData } from '../fixtures/seed-data';

dotenv.config({ path: path.resolve(__dirname, '../.env.test') });

const DB_HOST   = process.env.DB_HOST   || 'localhost';
const DB_PORT   = process.env.DB_PORT   || '10010';
const DB_NAME   = process.env.DB_NAME   || 'local';
const DB_USER   = process.env.DB_USER   || 'root';
const DB_PASS   = process.env.DB_PASS   || 'root';
const MYSQL_BIN = process.env.MYSQL_BIN || 'mysql';

function mysql(sql: string): void {
  try {
    execFileSync(
      MYSQL_BIN,
      ['-h', DB_HOST, '-P', DB_PORT, '-u', DB_USER, `-p${DB_PASS}`, DB_NAME, '-e', sql, '--skip-column-names', '-N'],
      { encoding: 'utf-8', timeout: 10_000 }
    );
  } catch {
    // Non-fatal setup queries
  }
}

function clearRateLimits(): void {
  mysql("DELETE FROM wp_options WHERE option_name LIKE '_transient_stsrc_rate_%' OR option_name LIKE '_transient_timeout_stsrc_rate_%';");
}

function enablePayLater(): void {
  // wp_options key for non-ACF fallback
  mysql("INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('stsrc_payment_plan_enabled', '1', 'yes') ON DUPLICATE KEY UPDATE option_value = '1';");
  // ACF options page stores with options_ prefix
  mysql("INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('options_stsrc_payment_plan_enabled', '1', 'yes') ON DUPLICATE KEY UPDATE option_value = '1';");
}

setup('seed test data', async () => {
  clearRateLimits();
  enablePayLater();
  await seedTestData();
});
