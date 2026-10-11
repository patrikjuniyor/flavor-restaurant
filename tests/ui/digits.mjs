/*
 * Number-formatting parity: the theme's JS against the theme's PHP (prompt 22).
 *
 * tests/theme/test-digit-parity.php can prove the spec is consistent and that no
 * script keeps its own digit table. It cannot prove that digits.js, executed,
 * prints what PHP prints — that needs a JS engine. So this file runs both:
 *
 *   - the real flavor/assets/js/digits.js, in a vm sandbox, no DOM, no jQuery;
 *   - the real \Flavor\UI::digits_config() and the real core formatter
 *     \FlavorCore\WooCommerce\Currency::to_persian_digits( number_format_i18n() ),
 *     through a one-shot php -r, exactly as tests/theme/bootstrap.php loads them.
 *
 * A mismatch means a price on the page and the same price in the confirmation
 * e-mail disagree — the failure this sweep exists to prevent. It also covers the
 * three behaviours a locale-based implementation got wrong: no payload (a lost
 * flavorData must not change the shop's number style), the store's latin digit
 * mode, and a separator that is not a comma.
 *
 * Usage: node tests/ui/digits.mjs   (npm --prefix tests/ui run test:digits)
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const digitsFile = path.join(root, 'flavor/assets/js/digits.js');

let passed = 0;
let failed = 0;
function ok(condition, label) {
	if (condition) { passed++; console.log(`  [PASS] ${label}`); }
	else { failed++; console.log(`  [FAIL] ${label}`); }
}

/* ------------------------------------------------------------------ PHP side */

const AMOUNTS = [0, 7, 42, 100, 999, 1000, 12345, 100000, 1234567, 999999999];
const probe = `
require ${JSON.stringify(path.join(root, 'tests/theme/bootstrap.php'))};
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once ${JSON.stringify(path.join(root, 'flavor-core/includes/WooCommerce/Currency.php'))};
$amounts = ${JSON.stringify(AMOUNTS)};
$cases = array();
foreach ($amounts as $amount) {
	$cases[] = array(
		'amount'  => $amount,
		'php'     => \\FlavorCore\\WooCommerce\\Currency::to_persian_digits( number_format_i18n( $amount ) ),
		'raw'     => number_format_i18n( $amount ),
	);
}
echo json_encode(array(
	'digits' => \\Flavor\\UI::digits_config(),
	'cases'  => $cases,
));
`;
const php = JSON.parse(execFileSync('php', ['-r', probe], { encoding: 'utf8', cwd: root }).trim().split('\n').pop());
const spec = php.digits;
console.log(`php spec: zero=${JSON.stringify(spec.zero)} thousands=${JSON.stringify(spec.thousands)} mode=${spec.mode}`);

/* ------------------------------------------------------------------- JS side */

function load(windowData) {
	const source = fs.readFileSync(digitsFile, 'utf8');
	const sandbox = { window: windowData || {}, console };
	sandbox.globalThis = sandbox.window;
	vm.createContext(sandbox);
	vm.runInContext(source, sandbox, { filename: 'digits.js' });
	return sandbox.window.FlavorDigits;
}

let FD = load({});

ok(!!FD && typeof FD.digits === 'function', 'digits.js loads and exposes digits() in a bare window');
ok(FD.money.length >= 1 && FD.group.length >= 1, 'money() and group() take an amount first');

/* With no payload at all the fallback has to be the shipped Persian setup —
   "no data" must not silently turn the shop into Latin digits. */
const noPayload = { zero: 0x06f0, arabicZero: 0x0660, thousands: ',', mode: 'persian' };
ok(FD.digits('1234567') === '۱۲۳۴۵۶۷', 'offline: digits still come out Persian');
ok(FD.group(1234567) === '1,234,567', 'offline: grouping uses the shipped comma');

/* Now the real spec, and the real prices. */
FD = load({ flavorData: { digits: spec } });
for (const item of php.cases) {
	assert.equal(
		FD.digits(FD.group(item.amount)),
		item.php,
		`${item.amount}: JS "${FD.digits(FD.group(item.amount))}" vs PHP "${item.php}"`
	);
}
ok(true, `all ${php.cases.length} amounts group and shape identically in PHP and JS`);

/* A price the customer reads includes the unit; money() must not invent one. */
const withLabel = load({ flavorData: { digits: spec, currency: { label: 'تومان' } } });
ok(withLabel.money(12345) === withLabel.digits(withLabel.group(12345)) + ' تومان', 'money() appends the payload label, once');
ok(FD.money(12345) === FD.digits(FD.group(12345)), 'money() with no label does not fall back to a hand-written currency word');
ok(withLabel.money(12345, 'ریال') === withLabel.digits(withLabel.group(12345)) + ' ریال', 'an explicit label wins over the payload one');

/* The separator is data from PHP. fa_IR in WordPress groups with U+066C. */
const arabicSep = load({ flavorData: { digits: Object.assign({}, spec, { thousands: '\u066c' }) } });
ok(arabicSep.digits(arabicSep.group(1234567)) === '۱٬۲۳۴٬۵۶۷', 'a server that groups with U+066C gets U+066C, not a comma');
const noGroup = load({ flavorData: { digits: Object.assign({}, spec, { thousands: '' }) } });
ok(noGroup.group(1234567) === '1234567', 'and a server that does not group at all is obeyed');

/* The store's own digits setting: latin mode must not convert. */
const latin = load({ flavorData: { digits: Object.assign({}, spec, { mode: 'latin' }) } });
ok(latin.digits('1,234') === '1,234', 'mode=latin leaves the number the server wrote alone');
ok(latin.money(1234) === '1,234', 'money() in latin mode stays latin');
ok(FD.digits('1,234') === '۱,۲۳۴', 'mode=persian converts it');

/* Reading input back: whatever the visitor typed, and whatever the page shows. */
ok(FD.latin('۱۲۳۴۵۶۷۸۹۰') === '1234567890', 'latin() reads Persian digits back');
ok(FD.latin('٠١٢٣٤٥٦٧٨٩') === '0123456789', 'latin() reads Arabic-Indic digits back');
ok(FD.latin('تماس: ۰۹۱۲٣۴۵') === 'تماس: 0912345', 'latin() leaves the surrounding text alone');
for (let d = 0; d <= 9; d++) {
	assert.equal(FD.latin(FD.digits(String(d))), String(d), `${d} survives digits() then latin()`);
}
ok(true, 'digits() and latin() are exact inverses for 0-9');
ok(FD.latin(FD.digits('12.5')) === '12.5' && FD.digits('12.5') === '۱۲.۵', 'a decimal keeps its dot');
ok(FD.group(-250000) === '-250,000', 'a negative amount keeps its sign outside the groups');
ok(FD.group(1500.25, 2) === '1,500.25', 'explicit decimals are kept and grouped');
ok(FD.group('not a number') === '0', 'garbage becomes 0 rather than NaN on a price line');

/* Why not toLocaleString: it answers from the machine's ICU data, so the same
   code prints differently per host. Shown, not asserted — the point is that this
   file's expectations do not depend on it. */
const locale = (1234567).toLocaleString('fa-IR');
console.log(`  [info] toLocaleString('fa-IR') on this host: "${locale}" — the formatter ignores it on purpose`);

/* Every display site now goes through the module. */
const jsDir = path.join(root, 'flavor/assets/js');
const consumers = {
	'ui.js': /FD \? FD\.digits\(value\)/,
	'menu.js': /FlavorDigits\.money\(value, unit\)/,
	'navigation.js': /FD\?FD\.digits\(count\|\|0\)/,
	'pages.js': /FD\?FD\.digits\(count\)/,
	'bespoke-demos.js': /FlavorDigits\.digits\(count\)/,
	'catering.js': /FlavorDigits\.digits\(text\)/,
	'search.js': /FlavorDigits\.digits\(value\)/,
	'motion.js': /FlavorDigits\.digits\(value\)/,
	'demo-animations.js': /FlavorDigits\.digits\(str\)/,
};
for (const [file, pattern] of Object.entries(consumers)) {
	const text = fs.readFileSync(path.join(jsDir, file), 'utf8');
	ok(pattern.test(text), `${file} delegates to FlavorDigits instead of shaping its own numbers`);
}

console.log('\n=======================================================');
console.log(`Digits parity: ${passed} checks passed, ${failed} failed`);
console.log('=======================================================');
process.exit(failed > 0 ? 1 : 0);
