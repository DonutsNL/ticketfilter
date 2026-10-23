<?php

/**
 * Builds locales/en_GB.po, locales/nl_NL.po and compiles them to binary .mo files.
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$localesDir = $baseDir . '/locales';
@mkdir($localesDir, 0755, true);

// Re-generate POT first
require_once __DIR__ . '/extract_locales.php';

$potFile = $localesDir . '/ticketfilter.pot';
if (!file_exists($potFile)) {
    die("Error: $potFile does not exist.\n");
}

$potContent = file_get_contents($potFile);

// Parse POT file
function parsePoEntries(string $content): array
{
    $entries = [];
    $blocks = preg_split('/\n\s*\n/', $content);

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '' || str_starts_with($block, 'msgid ""')) {
            continue;
        }

        $msgid = null;
        $msgidPlural = null;
        $comments = [];

        $lines = explode("\n", $block);
        $currentField = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, '#')) {
                $comments[] = $line;
                continue;
            }

            if (preg_match('/^msgid\s+"(.*)"$/', $line, $m)) {
                $currentField = 'msgid';
                $msgid = stripcslashes($m[1]);
            } elseif (preg_match('/^msgid_plural\s+"(.*)"$/', $line, $m)) {
                $currentField = 'msgid_plural';
                $msgidPlural = stripcslashes($m[1]);
            } elseif (preg_match('/^msgstr(\[\d+\])?\s+"(.*)"$/', $line, $m)) {
                $currentField = 'msgstr';
            } elseif (preg_match('/^"(.*)"$/', $line, $m)) {
                if ($currentField === 'msgid') {
                    $msgid .= stripcslashes($m[1]);
                } elseif ($currentField === 'msgid_plural') {
                    $msgidPlural .= stripcslashes($m[1]);
                }
            }
        }

        if ($msgid !== null && $msgid !== '') {
            $entries[$msgid] = [
                'comments' => $comments,
                'plural'   => $msgidPlural,
            ];
        }
    }

    return $entries;
}

$entries = parsePoEntries($potContent);

// Dutch dictionary
$nlDict = [
    'Access denied / Forbidden (HTTP 403)' => 'Toegang geweigerd / Verboden (HTTP 403)',
    'Access denied / Forbidden (HTTP 403). Please verify CSRF token and permissions.' => 'Toegang geweigerd / Verboden (HTTP 403). Controleer CSRF-token en rechten.',
    'Action' => 'Actie',
    'Actions' => 'Acties',
    'Active' => 'Actief',
    'Add as private followup to existing ticket' => 'Als privéreactie toevoegen aan bestaand ticket',
    'Add filter pattern' => 'Filterregel toevoegen',
    'Add new pattern' => 'Nieuwe regel toevoegen',
    'All system diagnostics passed successfully.' => 'Alle systeemdiagnoses zijn succesvol doorstaan.',
    'Also search email body (if ID not found in subject)' => 'Zoek ook in e-mailtekst (als ID niet in onderwerp staat)',
    'Always' => 'Altijd',
    'Always (allow any sender)' => 'Altijd (elke afzender toestaan)',
    'Ambiguous match: token "%s" matched %d tickets (%s). Merge aborted to prevent data corruption.' => 'Dubbelzinnige match: token "%s" kwam overeen met %d tickets (%s). Samenvoeging afgebroken om datacorruptie te voorkomen.',
    'An actor matches' => 'Een betrokkene komt overeen',
    'An actor matches (requester, technician, or observer)' => 'Een betrokkene komt overeen (aanvrager, technicus of waarnemer)',
    'Are you sure you want to delete this filter pattern?' => 'Weet u zeker dat u deze filterregel wilt verwijderen?',
    'Audit Logs' => 'Auditlogboeken',
    'Audit log' => 'Auditlogboek',
    'Audit log is currently empty' => 'Het auditlogboek is momenteel leeg',
    'Audit log table (%s) exists.' => 'Auditlogtabel (%s) bestaat.',
    'Audit log table (%s) is missing.' => 'Auditlogtabel (%s) ontbreekt.',
    'Auto-close' => 'Automatisch sluiten',
    'Auto-solve' => 'Automatisch oplossen',
    'Auto-solve / close (optional)' => 'Automatisch oplossen / sluiten (optioneel)',
    'Auto-solve / close private note' => 'Privénotitie bij automatisch oplossen / sluiten',
    'Auto-solve keyword (optional)' => 'Trefwoord voor automatisch oplossen (optioneel)',
    'Auto-solve keyword pattern' => 'Patroon voor automatisch oplossen',
    'Auto-solve keyword pattern (optional)' => 'Patroon voor automatisch oplossen (optioneel)',
    'Auto-solve keyword pattern (optional, must contain (?<solved>...))' => 'Patroon voor automatisch oplossen (optioneel, moet (?<solved>...) bevatten)',
    'Available placeholders (click to insert into active note field):' => 'Beschikbare variabelen (klik om in te voegen in actief veld):',
    'Candidate ticket' => 'Kandidaat-ticket',
    'Check email body' => 'E-mailtekst controleren',
    'Clear filter' => 'Filter wissen',
    'Clear sample' => 'Voorbeeld wissen',
    'Click to insert' => 'Klik om in te voegen',
    'Close' => 'Sluiten',
    'Closed' => 'Gesloten',
    'Closed ticket lifecycle' => 'Levenscyclus gesloten tickets',
    'Copy error' => 'Kopieer foutmelding',
    'Create a standard new ticket' => 'Standaard nieuw ticket aanmaken',
    'Database connection unavailable.' => 'Databaseverbinding niet beschikbaar.',
    'Date & time' => 'Datum & tijd',
    'Delete pattern' => 'Filterregel verwijderen',
    'Deleting...' => 'Bezig met verwijderen...',
    'Description / notes' => 'Omschrijving / notities',
    'Diagnostics' => 'Diagnose',
    'Disabled' => 'Uitgeschakeld',
    'Discard email (do not create ticket)' => 'E-mail negeren (geen ticket aanmaken)',
    'Do not send email notification when merging' => 'Geen e-mailnotificatie versturen bij samenvoegen',
    'Edit filter pattern' => 'Filterregel bewerken',
    'Edit pattern' => 'Filterregel bewerken',
    'Email merged via filter rule %s (match: %s, sender: %s)%s' => 'E-mail samengevoegd via filterregel %s (match: %s, afzender: %s)%s',
    'Emails arriving after this threshold will trigger the unmatched action instead of reopening.' => 'E-mails die na deze drempel binnenkomen activeren de terugvalactie in plaats van heropenen.',
    'Enable automated ticket resolution' => 'Automatische ticketoplossing inschakelen',
    'Enable auto-solve / close' => 'Automatisch oplossen / sluiten inschakelen',
    'Error' => 'Fout',
    'Error running simulator: ' => 'Fout bij uitvoeren simulator: ',
    'Execution channels' => 'Uitvoeringskanalen',
    'Fallback: discard email' => 'Terugval: e-mail negeren',
    'Fallback: new ticket' => 'Terugval: nieuw ticket',
    'Fallback: private followup' => 'Terugval: privéreactie',
    'Filter patterns' => 'Filterregels',
    'Help' => 'Help',
    'Identifier matching' => 'Identificatiematching',
    'If sender does not match' => 'Als afzender niet overeenkomt',
    'Incoming mail receivers' => 'Inkomende mailontvangers',
    'Interactive manual creation' => 'Interactieve handmatige invoer',
    'Intervention notes (ticket audit trail)' => 'Interventienotities (auditspoor op ticket)',
    'Invalid pattern ID' => 'Ongeldig regel-ID',
    'Invalid regex syntax in match pattern' => 'Ongeldige regex-syntaxis in match-patroon',
    'Invalid regex syntax in solved pattern' => 'Ongeldige regex-syntaxis in solved-patroon',
    'Mailgate' => 'Mailgate',
    'Match pattern' => 'Match-patroon',
    'Match pattern must include named group (?<match>...)' => 'Match-patroon moet de groep (?<match>...) bevatten',
    'Max ID length' => 'Max. lengte ID',
    'Max keyword length' => 'Max. lengte trefwoord',
    'Max length:' => 'Max. lengte:',
    'Maximum age for reopening:' => 'Maximale leeftijd voor heropenen:',
    'Merge when' => 'Samenvoegen wanneer',
    'Mute notifications' => 'Notificaties dempen',
    'Name' => 'Naam',
    'No match' => 'Geen overeenkomst',
    'No patterns configured' => 'Geen filterregels geconfigureerd',
    'Notifications & notes' => 'Notificaties & notities',
    'Options' => 'Opties',
    'Pattern "%s" (entity #%d) does not apply to ticket entity #%d.' => 'Filterregel "%s" (entiteit #%d) is niet van toepassing op ticketentiteit #%d.',
    'Pattern created successfully' => 'Filterregel succesvol aangemaakt',
    'Pattern deleted successfully' => 'Filterregel succesvol verwijderd',
    'Pattern name' => 'Naam filterregel',
    'Pattern name is required' => 'Naam van de filterregel is verplicht',
    'Pattern not found' => 'Filterregel niet gevonden',
    'Pattern rules' => 'Regeldetails',
    'Pattern simulator' => 'Regelsimulator',
    'Pattern updated successfully' => 'Filterregel succesvol bijgewerkt',
    'Patterns table (%s) exists.' => 'Filterregeltabel (%s) bestaat.',
    'Patterns table (%s) is missing.' => 'Filterregeltabel (%s) ontbreekt.',
    'PCRE regex engine failed to evaluate named capture groups.' => 'PCRE regex-engine kon benoemde groepen niet verwerken.',
    'PCRE2 regex engine supports named capture groups.' => 'PCRE2 regex-engine ondersteunt benoemde capture-groepen.',
    'Permissions' => 'Machtigingen',
    'Please add at least one active filter pattern to enable automated ticket matching.' => 'Voeg ten minste één actieve filterregel toe om automatische ticketmatching in te schakelen.',
    'private followup' => 'privéreactie',
    'Purge audit logs' => 'Auditlogboek opschonen',
    'Regex presets' => 'Regex voorbeelden',
    'Reopen (<%dd)' => 'Heropenen (<%dd)',
    'Reopen closed tickets' => 'Gesloten tickets heropenen',
    'Reopen private note' => 'Privénotitie bij heropenen',
    'Reopen ticket if closed' => 'Ticket heropenen indien gesloten',
    'Reopen within' => 'Heropenen binnen',
    'Requesters match' => 'Aanvragers komen overeen',
    'Requesters match (ticket creator only)' => 'Aanvragers komen overeen (alleen maker van het ticket)',
    'Rules used to match incoming emails to existing tickets and merge them automatically.' => 'Regels om inkomende e-mails te koppelen aan bestaande tickets en automatisch samen te voegen.',
    'Sample email subject' => 'Voorbeeld e-mailonderwerp',
    'Sample sender email' => 'Voorbeeld afzender e-mail',
    'Save pattern' => 'Filterregel opslaan',
    'Saving...' => 'Bezig met opslaan...',
    'Select Closed if monitoring systems send recovery messages that should close tickets directly.' => 'Selecteer Gesloten als monitoringsystemen herstelmeldingen sturen die tickets direct moeten sluiten.',
    'Sender authorization' => 'Afzenderautorisatie',
    'Sender domain matches' => 'Afzenderdomein komt overeen',
    'Sender domain matches (@company.com)' => 'Afzenderdomein komt overeen (@bedrijf.nl)',
    'Sender "%s" is not authorized on ticket #%d under policy "%s". Merge skipped.' => 'Afzender "%s" is niet geautoriseerd op ticket #%d volgens beleid "%s". Samenvoeging overgeslagen.',
    'Single email tester' => 'Enkele e-mail testen',
    'Solved' => 'Opgelost',
    'Status' => 'Status',
    'System diagnostics' => 'Systeemdiagnose',
    'Target status when resolved' => 'Doelstatus bij oplossing',
    'Target tickets' => 'Doeltickets',
    'Test pattern' => 'Test patroon',
    'Test results' => 'Testresultaten',
    'Ticket filter' => 'Ticketfilter',
    'TicketFilter' => 'TicketFilter',
    'TicketFilter administration' => 'TicketFilter beheer',
    'TicketFilter dashboard' => 'TicketFilter dashboard',
    'Ticket identifier pattern (regex, must contain (?<match>...))' => 'Ticketidentificatiepatroon (regex, moet (?<match>...) bevatten)',
    'Ticket match regex is required' => 'Ticket match regex is verplicht',
    'Web UI' => 'Webinterface',
    'days' => 'dagen',
];

$nlPluralDict = [
    'TicketFilter: %d email successfully merged into existing ticket.' => [
        'TicketFilter: %d e-mail succesvol samengevoegd met bestaand ticket.',
        'TicketFilter: %d e-mails succesvol samengevoegd met bestaande tickets.'
    ],
    '%d log entries pruned.' => [
        '%d logregel verwijderd.',
        '%d logregels verwijderd.'
    ]
];

function generatePo(string $lang, string $pluralForms, array $entries, array $singleDict, array $pluralDict): string
{
    $date = date('Y-m-d H:iO');
    $header = <<<PO
# TicketFilter Plugin for GLPI - {$lang} translation
# Copyright (C) 2023-2026 Chris Gralike
# This file is distributed under the same license as the TicketFilter package.
#
msgid ""
msgstr ""
"Project-Id-Version: TicketFilter 2.0.0\\n"
"Report-Msgid-Bugs-To: https://github.com/DonutsNL/ticketfilter/issues\\n"
"POT-Creation-Date: {$date}\\n"
"PO-Revision-Date: {$date}\\n"
"Last-Translator: Chris Gralike\\n"
"Language-Team: {$lang}\\n"
"Language: {$lang}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: {$pluralForms}\\n"


PO;

    $po = $header;

    foreach ($entries as $msgid => $meta) {
        $po .= implode("\n", $meta['comments']) . "\n";
        $po .= 'msgid "' . escapePo($msgid) . "\"\n";

        if ($meta['plural'] !== null) {
            $po .= 'msgid_plural "' . escapePo($meta['plural']) . "\"\n";
            if (isset($pluralDict[$msgid])) {
                $po .= 'msgstr[0] "' . escapePo($pluralDict[$msgid][0]) . "\"\n";
                $po .= 'msgstr[1] "' . escapePo($pluralDict[$msgid][1]) . "\"\n\n";
            } else {
                $po .= 'msgstr[0] "' . escapePo($msgid) . "\"\n";
                $po .= 'msgstr[1] "' . escapePo($meta['plural']) . "\"\n\n";
            }
        } else {
            $trans = $singleDict[$msgid] ?? $msgid;
            $po .= 'msgstr "' . escapePo($trans) . "\"\n\n";
        }
    }

    return $po;
}

// Generate en_GB.po
$enPo = generatePo('en_GB', 'nplurals=2; plural=(n != 1);', $entries, [], []);
file_put_contents($localesDir . '/en_GB.po', $enPo);

// Generate nl_NL.po
$nlPo = generatePo('nl_NL', 'nplurals=2; plural=(n != 1);', $entries, $nlDict, $nlPluralDict);
file_put_contents($localesDir . '/nl_NL.po', $nlPo);

echo "Wrote locales/en_GB.po and locales/nl_NL.po\n";

// Compile MO files
$msgfmt = '/usr/bin/msgfmt';
if (file_exists($msgfmt)) {
    exec("$msgfmt -o $localesDir/en_GB.mo $localesDir/en_GB.po", $out1, $ret1);
    exec("$msgfmt -o $localesDir/nl_NL.mo $localesDir/nl_NL.po", $out2, $ret2);
    if ($ret1 === 0 && $ret2 === 0) {
        echo "Successfully compiled locales/en_GB.mo and locales/nl_NL.mo\n";
    } else {
        echo "Error compiling MO files ($ret1, $ret2)\n";
    }
}
