import { execSync } from 'child_process';

const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_PORT = process.env.DB_PORT || '10010';
const DB_NAME = process.env.DB_NAME || 'local';
const DB_USER = process.env.DB_USER || 'root';
const DB_PASS = process.env.DB_PASS || 'root';

function mysqlCmd(): string {
  return `mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} ${DB_NAME}`;
}

export function dbQuery(sql: string): string {
  const escaped = sql.replace(/"/g, '\\"');
  try {
    return execSync(`${mysqlCmd()} -e "${escaped}" --skip-column-names -N`, {
      encoding: 'utf-8',
      timeout: 10_000,
    }).trim();
  } catch (err: any) {
    throw new Error(`DB query failed: ${sql}\n${err.stderr || err.message}`);
  }
}

export function dbInsert(table: string, data: Record<string, string | number | null>): void {
  const cols = Object.keys(data).join(', ');
  const vals = Object.values(data)
    .map((v) => (v === null ? 'NULL' : `'${String(v).replace(/'/g, "\\'")}'`))
    .join(', ');
  dbQuery(`INSERT INTO ${table} (${cols}) VALUES (${vals})`);
}

export function dbGetRow(table: string, where: string): string {
  return dbQuery(`SELECT * FROM ${table} WHERE ${where} LIMIT 1`);
}

export function dbCount(table: string, where?: string): number {
  const clause = where ? ` WHERE ${where}` : '';
  const result = dbQuery(`SELECT COUNT(*) FROM ${table}${clause}`);
  return parseInt(result, 10) || 0;
}

export function dbTruncate(table: string): void {
  dbQuery(`TRUNCATE TABLE ${table}`);
}

export function getTablePrefix(): string {
  try {
    const result = dbQuery(
      "SELECT option_value FROM wp_options WHERE option_name = 'siteurl' LIMIT 1"
    );
    return result ? 'wp_' : 'wp_';
  } catch {
    return 'wp_';
  }
}
