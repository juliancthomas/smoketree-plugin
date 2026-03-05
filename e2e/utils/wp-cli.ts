import { execSync } from 'child_process';
import * as path from 'path';

const WP_PATH = path.resolve(__dirname, '../../../../..');
const WP_CLI = process.env.WP_CLI_PATH || 'wp';

export function wp(command: string): string {
  const full = `${WP_CLI} ${command} --path="${WP_PATH}"`;
  try {
    return execSync(full, {
      encoding: 'utf-8',
      timeout: 30_000,
      env: { ...process.env },
    }).trim();
  } catch (err: any) {
    const msg = err.stderr || err.stdout || err.message;
    throw new Error(`WP-CLI failed: ${full}\n${msg}`);
  }
}

export function wpEval(phpCode: string): string {
  const escaped = phpCode.replace(/"/g, '\\"');
  return wp(`eval "${escaped}"`);
}

export function wpQuery(sql: string): string {
  const escaped = sql.replace(/"/g, '\\"');
  return wp(`db query "${escaped}" --skip-column-names`);
}

export function createWpUser(
  email: string,
  password: string,
  role = 'stsrc_member',
  displayName = 'Test User'
): number {
  const result = wp(
    `user create "${email}" "${email}" --role=${role} --user_pass="${password}" --display_name="${displayName}" --porcelain`
  );
  return parseInt(result, 10);
}

export function deleteWpUser(emailOrId: string | number): void {
  try {
    wp(`user delete "${emailOrId}" --yes`);
  } catch {
    // User may not exist — that's fine
  }
}

export function getOption(key: string): string {
  return wp(`option get ${key}`);
}

export function setOption(key: string, value: string): void {
  wp(`option update ${key} "${value}"`);
}
