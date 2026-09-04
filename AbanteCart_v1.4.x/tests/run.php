<?php
/**
 * Standalone unit tests for AbanteCart 1.4 PlugnPay payment extensions.
 * Does not boot AbanteCart. Run: php AbanteCart_v1.4.x/tests/run.php
 */
define('DIR_CORE', 'tests');

$root = dirname(__DIR__);
require $root . '/src/extensions/plugnpay_api_cc/core/PnPFilter.php';
require $root . '/src/extensions/plugnpay_ss2/core/PnPSs2Filter.php';
require $root . '/src/extensions/plugnpay_api_cc/core/PnPLogger.php';
require $root . '/src/extensions/plugnpay_ss2/core/PnPSs2Logger.php';

$failed = 0;
$passed = 0;

function expect($cond, $message) {
	global $failed, $passed;
	if ($cond) {
		$passed++;
		echo "ok  {$message}\n";
		return;
	}
	$failed++;
	echo "FAIL {$message}\n";
}

echo "== Remote API PnPFilter ==\n";
expect(PnPFilter::isValidPan('4111111111111111'), 'Visa test PAN passes Luhn');
expect(!PnPFilter::isValidPan('4111111111111112'), 'bad Luhn rejected');
expect(!PnPFilter::isValidPan('123'), 'short PAN rejected');
expect(!PnPFilter::isValidPan(str_repeat('1', 20)), 'PAN longer than 19 rejected');
expect(!PnPFilter::isValidPan('4111x11111111111'), 'PAN with invalid characters rejected');
expect(!PnPFilter::isValidPan(array('4111111111111111')), 'array-shaped PAN rejected');
expect(PnPFilter::isValidCvv('123'), '3-digit CVV ok');
expect(PnPFilter::isValidCvv('1234'), '4-digit CVV ok');
expect(!PnPFilter::isValidCvv('12a'), 'non-digit CVV rejected');
expect(!PnPFilter::isValidCvv('1a23'), 'CVV characters are not silently stripped');
expect(!PnPFilter::isValidCvv(array('123')), 'array-shaped CVV rejected');
expect(!PnPFilter::isValidCvv('12'), 'short CVV rejected');
expect(PnPFilter::isValidExpiry(12, 2099, 1, 2026), 'future expiry ok');
expect(!PnPFilter::isValidExpiry(13, 2026, 1, 2026), 'month 13 rejected');
expect(!PnPFilter::isValidExpiry(1, 2020, 9, 2026), 'past expiry rejected');
expect(!PnPFilter::isValidExpiry(array(1), 2099, 1, 2026), 'array-shaped expiry rejected');
expect(PnPFilter::formatCardExp(3, '2027') === '03/27', 'exp formatted MM/YY');
expect(PnPFilter::formatAmount('10.00evil') === '0.00', 'invalid API amount rejected');
expect(PnPFilter::sanitizeCardName('<b>Jane Q. O\'Neil</b>  ') === "Jane Q. O'Neil", 'card name stripped');
expect(PnPFilter::sanitizeEmail('not-an-email') === '', 'invalid email dropped');
expect(PnPFilter::sanitizeEmail('a@b.co') === 'a@b.co', 'valid email kept');
expect(PnPFilter::isApproved(array('FinalStatus' => 'success', 'success' => 'no')), 'approve only FinalStatus=success');
expect(!PnPFilter::isApproved(array('success' => 'yes')), 'success=yes alone is not approval');
expect(!PnPFilter::isApproved(array('FinalStatus' => 'badcard')), 'badcard not approved');

$redacted = PnPFilter::redact(array(
	'card-number' => '4111111111111111',
	'card-cvv' => '123',
	'publisher-password' => 'secret',
	'MErrMsg' => 'declined 4111111111111111',
	'FinalStatus' => 'badcard',
));
expect($redacted['card-number'] === '***REDACTED***', 'PAN key fully redacted (no last-4)');
expect($redacted['card-cvv'] === '***REDACTED***', 'CVV redacted');
expect($redacted['publisher-password'] === '***REDACTED***', 'password redacted');
expect($redacted['MErrMsg'] === '***REDACTED***', 'PAN-shaped value in MErrMsg redacted');
expect($redacted['FinalStatus'] === 'badcard', 'FinalStatus kept');

$allowed = PnPFilter::allowlistAuthorizeFields(array(
	'card-amount' => '10.00',
	'mode' => 'auth',
	'evil' => 'drop-me',
	'card-number' => '4111111111111111',
	'item99' => 'too-many',
	'item1' => '42',
));
expect(isset($allowed['card-amount']) && isset($allowed['item1']), 'allowlisted auth fields kept');
expect(!isset($allowed['evil']) && !isset($allowed['item99']), 'unknown and over-cap line items dropped');

$resp = PnPFilter::allowlistResponse(array(
	'FinalStatus' => 'success',
	'<script>' => 'xss',
	'MErrMsg' => '<b>hi</b>',
));
expect(isset($resp['FinalStatus']) && !isset($resp['<script>']), 'response allowlist drops extra keys');
expect($resp['MErrMsg'] === 'hi', 'MErrMsg tags stripped');

expect(PnPFilter::isHttpsRequest(array('HTTPS' => 'on')), 'HTTPS=on is https');
expect(!PnPFilter::isHttpsRequest(array('HTTPS' => 'off', 'SERVER_PORT' => '80')), 'HTTP not https');
expect(PnPFilter::isHttpsRequest(array('REQUEST_SCHEME' => 'https')), 'REQUEST_SCHEME https');
expect(PnPFilter::toCents('10.00') === 1000 && PnPFilter::toCents('10.009') === 1001, 'cents rounding');
expect(!(new PnPLogger('', true))->isEnabled(), 'API logging fails closed without configured directory');

echo "\n== Smart Screens PnPSs2Filter ==\n";
expect(PnPSs2Filter::amountsEqual('10.00', '10'), 'amount compare uses cents');
expect(!PnPSs2Filter::amountsEqual('10.00', '10.000'), 'over-precise callback amount rejected');
expect(!PnPSs2Filter::amountsEqual('10.00', '10.01'), 'amount mismatch detected');
expect(!PnPSs2Filter::amountsEqual('10.00', '10.00evil'), 'amount trailing text rejected');
expect(!PnPSs2Filter::amountsEqual('0.00', array('0.00')), 'array-shaped amount rejected');
expect(PnPSs2Filter::sanitizeCurrency('usd', array('USD', 'CAD')) === 'USD', 'currency allowlist');
expect(PnPSs2Filter::sanitizeCurrency('JPY', array('USD', 'CAD')) === '', 'unsupported currency dropped');

$token = PnPSs2Filter::newReturnToken();
expect(strlen($token) === 64 && ctype_xdigit($token), 'return token is 32-byte hex');
$mac = PnPSs2Filter::returnMac($token, '99', '12.34', 'acct', 'USD');
$mac2 = PnPSs2Filter::returnMac($token, '99', '12.34', 'acct', 'USD');
$mac3 = PnPSs2Filter::returnMac($token, '99', '12.35', 'acct', 'USD');
expect(PnPSs2Filter::tokenEquals($mac, $mac2), 'HMAC stable for same payload');
expect(!PnPSs2Filter::tokenEquals($mac, $mac3), 'HMAC changes when amount changes');
expect(!PnPSs2Filter::tokenEquals($mac, ''), 'empty token rejected');

$hashSecret = 'server-only-secret';
$hashSource = $hashSecret . 'acct' . '2008120816235912345' . '10.00';
expect(
	PnPSs2Filter::isValidResponseHash(
		$hashSecret,
		'acct',
		'2008120816235912345',
		'10.00',
		hash('sha256', $hashSource)
	),
	'SHA-256 gateway response hash accepted'
);
expect(
	PnPSs2Filter::isValidResponseHash(
		$hashSecret,
		'acct',
		'2008120816235912345',
		'10.00',
		md5($hashSource)
	),
	'legacy MD5 gateway response hash accepted'
);
expect(
	PnPSs2Filter::isValidResponseHash(
		'8d6c15304f86e136ed9dbaaea',
		'pnpdemo',
		'2008120816235912345',
		'10.00',
		'05fa2537460459b167ac946c9239636f'
	),
	'PlugnPay documented response hash vector accepted'
);
expect(
	!PnPSs2Filter::isValidResponseHash(
		$hashSecret,
		'acct',
		'2008120816235912345',
		'10.01',
		hash('sha256', $hashSource)
	),
	'gateway response hash binds amount'
);
expect(
	!PnPSs2Filter::isValidResponseHash('', 'acct', '1', '10.00', md5('x')),
	'gateway response hash requires server secret'
);

$host = PnPSs2Filter::allowlistHostedFields(array(
	'pt_transaction_amount' => '1.00',
	'pt_gateway_account' => 'acct',
	'card-number' => '4111111111111111',
	'pb_post_auth' => 'no',
));
expect(isset($host['pt_transaction_amount']) && !isset($host['card-number']), 'hosted allowlist drops CHD keys');

$store = PnPSs2Filter::allowlistStoreFields(array(
	'pi_response_status' => 'success',
	'pt_authorization_code' => '123456',
	'pi_error_message' => '<script>x</script>',
	'pt_card_number' => '4111111111111111',
	'pt_transaction_response_hash' => hash('sha256', $hashSource),
));
expect(
	isset($store['pi_response_status'])
	&& !isset($store['pt_card_number'])
	&& !isset($store['pi_error_message'])
	&& !isset($store['pt_transaction_response_hash']),
	'DB store allowlist excludes CHD, errors, and verification hashes'
);

$ss2redact = PnPSs2Filter::redact(array(
	'pt_card_number' => '4111111111111111',
	'abc_return_token' => $token,
	'pi_response_status' => 'success',
));
expect($ss2redact['pt_card_number'] === '***REDACTED***', 'SS2 PAN redacted');
expect($ss2redact['abc_return_token'] === '***REDACTED***', 'return token redacted');
expect($ss2redact['pi_response_status'] === 'success', 'status kept');
$ss2Expiry = PnPSs2Filter::redact(array('card-exp' => '12/29', 'cc_expiry' => '12/29'));
expect(
	$ss2Expiry['card-exp'] === '***REDACTED***' && $ss2Expiry['cc_expiry'] === '***REDACTED***',
	'SS2 expiry fields redacted'
);
expect(!(new PnPSs2Logger('', true))->isEnabled(), 'SS2 logging fails closed without configured directory');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
