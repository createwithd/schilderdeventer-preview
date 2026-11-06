<?php
/* Offerteformulier van de stadsite (Hostnet / PHP-hosting).
   1. Mailt elke aanvraag naar Voortman, met site, plaats en dienst in de onderwerpregel.
   2. Bewaart elke aanvraag in leads/leads.csv (map is afgeschermd voor bezoekers).
   Voorwaarde: maak bij Hostnet een mailbox noreply@<domein> aan, anders wordt de mail niet verstuurd. */

$TO = 'info@voortmanschilderwerken.nl';
$FROM = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{"ok":false}'); }

$d = json_decode(file_get_contents('php://input'), true);
if (!is_array($d)) { http_response_code(400); exit('{"ok":false}'); }
if (!empty($d['website'])) { exit('{"ok":true}'); } // honeypot: bot

$f = function ($k) use ($d) { return trim(mb_substr(is_array($d[$k] ?? '') ? implode(', ', $d[$k]) : (string)($d[$k] ?? ''), 0, 2000)); };
$lead = [
  'site' => $f('site'), 'dienst' => $f('dienst'), 'postcode' => $f('postcode'), 'plaats' => $f('plaats'),
  'omschrijving' => $f('omschrijving'), 'naam' => $f('naam'), 'telefoon' => $f('telefoon'), 'email' => $f('email'), 'bron' => $f('bron'),
];
if ($lead['naam'] === '' || $lead['telefoon'] === '' || !filter_var($lead['email'], FILTER_VALIDATE_EMAIL) || empty($d['akkoord'])) {
  http_response_code(400); exit('{"ok":false}');
}

/* Back-up in CSV */
$dir = __DIR__ . '/leads';
if (!is_dir($dir)) mkdir($dir, 0750, true);
if (!file_exists("$dir/.htaccess")) file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
$csv = "$dir/leads.csv";
$new = !file_exists($csv);
$fh = fopen($csv, 'a');
if ($new) fputcsv($fh, array_merge(['datum'], array_keys($lead)));
fputcsv($fh, array_merge([date('Y-m-d H:i')], array_values($lead)));
fclose($fh);

/* Mail */
$h = function ($v) { return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); };
$rows = '';
foreach (['Site' => 'site', 'Dienst' => 'dienst', 'Plaats' => 'plaats', 'Postcode' => 'postcode', 'Omschrijving' => 'omschrijving', 'Naam' => 'naam', 'Telefoon' => 'telefoon', 'E-mail' => 'email', 'Bron' => 'bron'] as $label => $k) {
  if ($lead[$k] !== '') $rows .= '<tr><td style="padding:6px 14px 6px 0;color:#5b5f67;vertical-align:top">' . $label . '</td><td style="padding:6px 0">' . $h($lead[$k]) . '</td></tr>';
}
$subject = 'Offerte · ' . $lead['site'] . ' · ' . ($lead['plaats'] ?: $lead['postcode']) . ' · ' . ($lead['dienst'] ?: 'schilderwerk');
$body = '<h2 style="font-family:Arial">Nieuwe offerteaanvraag via ' . $h($lead['site']) . '</h2><table style="font-family:Arial;font-size:14px">' . $rows . '</table>';
$headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: " . $lead['site'] . " <$FROM>\r\nReply-To: " . $lead['email'] . "\r\n";
$ok = mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, "-f$FROM");

echo $ok ? '{"ok":true}' : '{"ok":false,"saved":true}';
if (!$ok) http_response_code(502);
