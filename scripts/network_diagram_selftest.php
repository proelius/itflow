#!/usr/bin/env php
<?php
/**
 * Network diagram self-test - functions/network_diagram.php
 * What the model puts where, that the SVG is well formed, escaped, self-contained and
 * inside the A4 landscape page, and that the PDF is one landscape page.
 *
 * CLI only - protected from web access
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$itflow_dir = __DIR__ . '/..';
require_once $itflow_dir . '/functions/network.php';
require_once $itflow_dir . '/functions/network_diagram.php';

$passed = 0;
$failed = 0;

function nd_test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ $name\n";
        $passed++;
    } else {
        echo "  ✗ $name\n";
        $failed++;
    }
}

function nd_network($id, $name, $type, $extra = []) {
    return $extra + ['network_id' => $id, 'network_name' => $name, 'network_type' => $type, 'network' => "10.$id.0.0/24",
        'network_gateway' => "10.$id.0.1", 'network_description' => '', 'network_vlan' => $id * 10, 'location_name' => '', 'vendor_name' => ''];
}

/** Every number the SVG draws, to see that none is off the page. */
function nd_bounds($svg) {
    $max_x = 0;
    $max_y = 0;
    $xml = simplexml_load_string($svg);
    foreach ($xml->children() as $el) {
        $a = $el->attributes();
        if ($el->getName() === 'rect' && floatval($a['width']) >= NETWORK_DIAGRAM_WIDTH) {
            continue; // the background
        }
        $x = floatval($a['x'] ?? 0);
        $y = floatval($a['y'] ?? 0);
        if ($el->getName() === 'rect') {
            $max_x = max($max_x, $x + floatval($a['width']));
            $max_y = max($max_y, $y + floatval($a['height']));
        } elseif ($el->getName() === 'text') {
            $max_y = max($max_y, $y);
            $anchor = (string) ($a['text-anchor'] ?? 'start');
            $width = networkDiagramTextWidth((string) $el, floatval($a['font-size']), (string) ($a['font-weight'] ?? '') === 'bold');
            if ($anchor === 'start') {
                $max_x = max($max_x, $x + $width);
            } elseif ($anchor === 'middle') {
                $max_x = max($max_x, $x + $width / 2);
            }
        }
    }
    return [$max_x, $max_y];
}

echo "Network diagram\n";

// Text helpers
nd_test('a short text is kept', networkDiagramFit('LAN', 10, 200) === 'LAN');
nd_test('a long text is cut with an ellipsis and fits', ($t = networkDiagramFit(str_repeat('abcdefghij', 10), 10, 100)) !== '' && mb_substr($t, -1) === '…' && networkDiagramTextWidth($t, 10) <= 100);
$lines = networkDiagramWrap('Postes de travail, imprimantes et téléphones des collaborateurs du siège', 9.5, 120, 2);
nd_test('a wrapped text has at most the lines asked, the last cut', count($lines) === 2 && mb_substr($lines[1], -1) === '…');
nd_test('a word wider than the line is broken', count(networkDiagramWrap(str_repeat('x', 80), 10, 100, 3)) >= 2);
nd_test('a tint of 1 keeps the colour, of 0 is white', networkDiagramTint('#3b82f6', 1) === '#3b82f6' && networkDiagramTint('#3b82f6', 0) === '#ffffff');

// The model
$networks = [
    nd_network(1, 'LAN', 'LAN', ['network_description' => "Office\nworkstations", 'location_name' => 'Geneva']),
    nd_network(2, 'Swisscom', 'WAN', ['vendor_name' => 'Swisscom']),
    nd_network(3, 'Guests', 'Guest'),
    nd_network(4, 'Backup line', 'WAN', ['network_description' => 'Standby']),
    nd_network(5, 'Servers', 'Servers'),
    nd_network(6, 'Spare', 'Other'),
    nd_network(7, 'Zzz', 'Not a type'),
];
$devices = [
    1 => [
        ['asset_id' => 10, 'asset_name' => 'sw-b', 'asset_type' => 'Switch'], ['asset_id' => 10, 'asset_name' => 'sw-b', 'asset_type' => 'Switch'],
        ['asset_id' => 11, 'asset_name' => 'SW-A', 'asset_type' => 'Switch'], ['asset_id' => 12, 'asset_name' => 'PC-1', 'asset_type' => 'Desktop'],
        ['asset_id' => 13, 'asset_name' => 'AP-1', 'asset_type' => 'Access Point'], ['asset_id' => 14, 'asset_name' => 'FW-01', 'asset_type' => 'Firewall/Router'],
    ],
    5 => [['asset_id' => 20, 'asset_name' => 'SRV-1', 'asset_type' => 'Server']],
];
$model = networkDiagramModel($networks, $devices, ['FW-02', 'FW-01', 'FW-01', ' ']);
nd_test('internet connections are apart from the other networks', count($model['wan']) === 2 && count($model['cards']) === 5);
$names = array_column($model['cards'], 'name');
nd_test('networks are put in the order of the types', array_search('LAN', $names) < array_search('Guests', $names) && array_search('Guests', $names) < array_search('Servers', $names) && array_search('Servers', $names) < array_search('Spare', $names));
$unknown = array_values(array_filter($model['cards'], function ($c) { return $c['name'] === 'Zzz'; }))[0];
nd_test('a type that is not one is read from the name, as the network page does', $unknown['type'] === networkType(nd_network(7, 'Zzz', 'Not a type')));
nd_test('an asset is counted once, however many interfaces are on the network', $model['cards'][0]['count'] === 5);
nd_test('main equipment is named, the rest only counted', $model['cards'][0]['key'] === ['SW-A', 'sw-b', 'AP-1']);
nd_test('the firewalls are listed once each, in order', $model['hub'] === ['FW-01', 'FW-02']);
nd_test('the description is the role, on one line', $model['cards'][0]['role'] === 'Office workstations');
$guests = $model['cards'][array_search('Guests', $names)];
nd_test('with no description a network has what its type is for', $guests['role'] === NETWORK_TYPES['Guest'][2]);
nd_test('an internet connection with no description names its provider', $model['wan'][1]['role'] === 'Internet connection from Swisscom' || $model['wan'][0]['role'] === 'Internet connection from Swisscom');
nd_test('the total counts every network', $model['total'] === 7);

$many = [];
for ($i = 1; $i <= 15; $i++) {
    $many[] = nd_network($i, "Network $i", 'LAN');
}
$model_many = networkDiagramModel($many, [], []);
nd_test('more than twelve networks: eleven cards and the rest counted', count($model_many['cards']) === 11 && $model_many['left_out'] === 4 && $model_many['total'] === 15);
$wans = [];
for ($i = 1; $i <= 6; $i++) {
    $wans[] = nd_network($i, "Line $i", 'WAN');
}
$model_wans = networkDiagramModel($wans, [], []);
nd_test('more than four internet connections: four and the rest counted', count($model_wans['wan']) === 4 && $model_wans['wan_left_out'] === 2);

// The SVG
$svg = networkDiagramSvg($model, 'Société <Éléphant> & "Fils"', '2026-11-11');
$dom = new DOMDocument();
nd_test('the SVG is well-formed XML', $dom->loadXML($svg) !== false);
nd_test('it is 1123 x 794, an A4 landscape page', strpos($svg, 'width="1123" height="794" viewBox="0 0 1123 794"') !== false);
nd_test('text from the data is escaped', strpos($svg, '<Éléphant>') === false && strpos($svg, '&lt;Éléphant&gt; &amp;') !== false);
nd_test('it is self-contained: no link, image, script or style', !preg_match('/<(image|script|style|use|a|foreignObject)\b|href=|url\(|https?:\/\/(?!www\.w3\.org)/i', $svg));
nd_test('it names the networks and the firewalls', strpos($svg, 'Swisscom') !== false && strpos($svg, 'FW-01') !== false && strpos($svg, 'VLAN 10') !== false);

foreach ([[[], 'no network'], [array_slice($networks, 0, 1), 'one network'], [$networks, 'six networks'], [$many, 'fifteen networks'], [$wans, 'six internet connections']] as [$set, $label]) {
    $s = networkDiagramSvg(networkDiagramModel($set, $devices, ['FW-01']), 'Client', '2026-11-11');
    list($max_x, $max_y) = nd_bounds($s);
    nd_test("$label: everything is inside the page", $max_x <= NETWORK_DIAGRAM_WIDTH - 4 && $max_y <= NETWORK_DIAGRAM_HEIGHT - 4);
}
$no_fw = networkDiagramSvg(networkDiagramModel($networks, $devices, []), 'Client', '2026-11-11');
nd_test('with no firewall the picture says so', strpos($no_fw, 'Not documented') !== false);
$left = networkDiagramSvg($model_many, 'Client', '2026-11-11');
nd_test('left-out networks are counted on the last card', strpos($left, '+4') !== false && strpos($left, 'other networks') !== false);

// The PDF
$pdf = networkDiagramPdf($svg, 'Test');
nd_test('the PDF is a PDF', substr($pdf, 0, 5) === '%PDF-');
nd_test('it is one page', preg_match_all('/\/Type\s*\/Page\b(?!s)/', $pdf) === 1);
nd_test('it is A4 landscape', (bool) preg_match('/\/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+841\.89\d*\s+595\.27\d*\s*\]/', $pdf));

echo "\n=== Summary ===\nTests passed: $passed\nTests failed: $failed\n";
if ($failed > 0) {
    exit(1);
}
echo "\n✓ All tests passed!\n";
