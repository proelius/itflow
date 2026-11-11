<?php

/*
 * Network diagram - a one-page, A4 landscape picture of a client's networks:
 * what each one is for, how it is addressed and the main equipment on it.
 *
 * It is a diagram of principle, not an inventory. The internet connections
 * (WAN networks) sit on top, the firewall / router in the middle and one card
 * for each of the other networks below it. A card says what the network is
 * for, its subnet, VLAN and gateway, lists the main equipment on it (switches,
 * access points, servers...) and counts the rest.
 *
 * Everything is drawn here, on the server, as plain SVG - nothing is fetched
 * from, or sent to, another site. The same SVG is shown in the page, offered
 * as a download and set on an A4 landscape PDF with TCPDF.
 *
 * networkDiagramModel() and networkDiagramSvg() take plain arrays and touch no
 * database, so they can be tested alone (scripts/network_diagram_selftest.php);
 * networkDiagramLoad() reads what they need.
 */

const NETWORK_DIAGRAM_WIDTH = 1123;   // A4 landscape at 96 dpi
const NETWORK_DIAGRAM_HEIGHT = 794;
const NETWORK_DIAGRAM_MARGIN = 36;
const NETWORK_DIAGRAM_MAX_CARDS = 12; // more than this and the last card says how many are left out
const NETWORK_DIAGRAM_MAX_WAN = 4;

/** The colour of each network type's card. */
const NETWORK_DIAGRAM_COLORS = [
    'LAN' => '#3b82f6', 'Wireless' => '#0ea5e9', 'Guest' => '#f59e0b', 'Voice' => '#a855f7',
    'Servers' => '#6366f1', 'Storage' => '#65a30d', 'Management' => '#64748b', 'IoT' => '#ec4899',
    'DMZ' => '#ef4444', 'VPN' => '#14b8a6', 'WAN' => '#475569', 'Other' => '#94a3b8',
];

/** The equipment worth naming on a card, in the order it is listed - the rest is only counted. */
const NETWORK_DIAGRAM_KEY_TYPES = ['Switch', 'Access Point', 'Server', 'NAS', 'Virtual Machine', 'Modem/ONT', 'NVR/DVR', 'UPS'];

const NETWORK_DIAGRAM_FONT = 'Helvetica, Arial, sans-serif';

/** Escape text for SVG. */
function networkDiagramEsc($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** About how wide a text is, in pixels - Helvetica averages a little over half its size a character. */
function networkDiagramTextWidth($text, $size, $bold = false) {
    return mb_strlen((string) $text) * $size * ($bold ? 0.6 : 0.55);
}

/** A text cut to fit a width, ending in an ellipsis when it was cut. */
function networkDiagramFit($text, $size, $max_width, $bold = false) {
    $text = trim((string) $text);
    if (networkDiagramTextWidth($text, $size, $bold) <= $max_width) {
        return $text;
    }
    $chars = max(1, (int) floor($max_width / ($size * ($bold ? 0.6 : 0.55))) - 1);
    return rtrim(mb_substr($text, 0, $chars)) . '…';
}

/** A text broken into lines no wider than $max_width, at most $max_lines of them (the last one cut with an ellipsis). */
function networkDiagramWrap($text, $size, $max_width, $max_lines, $bold = false) {
    $words = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $try = $line === '' ? $word : "$line $word";
        if (networkDiagramTextWidth($try, $size, $bold) <= $max_width) {
            $line = $try;
            continue;
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        $line = $word;
        // A word wider than the line is cut
        while (networkDiagramTextWidth($line, $size, $bold) > $max_width && mb_strlen($line) > 1) {
            $chars = max(1, (int) floor($max_width / ($size * ($bold ? 0.6 : 0.55))));
            $lines[] = mb_substr($line, 0, $chars);
            $line = mb_substr($line, $chars);
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    if (count($lines) > $max_lines) {
        $lines = array_slice($lines, 0, $max_lines);
        $lines[$max_lines - 1] = networkDiagramFit(rtrim($lines[$max_lines - 1]) . '…', $size, $max_width, $bold);
    }
    return $lines;
}

/** A colour mixed with white - $amount is how much of the colour is kept (0 to 1). */
function networkDiagramTint($hex, $amount) {
    $hex = ltrim($hex, '#');
    $out = '#';
    foreach ([0, 2, 4] as $i) {
        $value = hexdec(substr($hex, $i, 2));
        $out .= sprintf('%02x', (int) round(255 - (255 - $value) * $amount));
    }
    return $out;
}

/** What a network is for, in a sentence: the one written on it, or failing that what its type is usually for. */
function networkDiagramRole(array $network, $type) {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($network['network_description'] ?? ''))));
    if ($text === '' && $type === 'WAN' && trim((string) ($network['vendor_name'] ?? '')) !== '') {
        return 'Internet connection from ' . trim($network['vendor_name']);
    }
    if ($text === '' && defined('NETWORK_TYPES') && isset(NETWORK_TYPES[$type][2])) {
        $text = NETWORK_TYPES[$type][2];
    }
    return $text;
}

/**
 * What the diagram shows, worked out from plain arrays.
 *
 * $networks   the client's networks (rows of the networks table, with location_name and vendor_name)
 * $devices    [network_id => rows of asset_id, asset_name, asset_type - one for each interface on that network]
 * $firewalls  names of the client's firewalls and routers, wherever they are
 *
 * Returns ['wan' => cards, 'hub' => names, 'cards' => cards, 'left_out' => networks that did not fit,
 * 'total' => number of networks]. A card is name, type, color, cidr, vlan, gateway, role, location,
 * key (main equipment names) and count (every device on the network).
 */
function networkDiagramModel(array $networks, array $devices, array $firewalls) {
    $order = defined('NETWORK_TYPES') ? array_flip(array_keys(NETWORK_TYPES)) : [];
    $cards = [];
    foreach ($networks as $network) {
        $type = function_exists('networkType') ? networkType($network) : (string) ($network['network_type'] ?? 'Other');
        if (!isset(NETWORK_DIAGRAM_COLORS[$type])) {
            $type = 'Other';
        }

        // Each asset once, whatever number of its interfaces are on the network
        $assets = [];
        foreach ($devices[intval($network['network_id'] ?? 0)] ?? [] as $device) {
            $assets[intval($device['asset_id'])] = $device;
        }
        $key = [];
        foreach ($assets as $asset) {
            $rank = array_search($asset['asset_type'], NETWORK_DIAGRAM_KEY_TYPES, true);
            if ($rank !== false) {
                $key[] = [$rank, (string) $asset['asset_name']];
            }
        }
        usort($key, function ($a, $b) {
            return [$a[0], strtolower($a[1])] <=> [$b[0], strtolower($b[1])];
        });

        $vlan = intval($network['network_vlan'] ?? 0);
        $cards[] = [
            'name' => trim((string) $network['network_name']),
            'type' => $type,
            'color' => NETWORK_DIAGRAM_COLORS[$type],
            'cidr' => trim((string) ($network['network'] ?? '')),
            'vlan' => $vlan > 0 ? $vlan : 0,
            'gateway' => trim((string) ($network['network_gateway'] ?? '')),
            'role' => networkDiagramRole($network, $type),
            'location' => trim((string) ($network['location_name'] ?? '')),
            'key' => array_column($key, 1),
            'count' => count($assets),
            'order' => [$order[$type] ?? 99, $vlan > 0 ? $vlan : 99999, strtolower(trim((string) $network['network_name']))],
        ];
    }
    usort($cards, function ($a, $b) {
        return $a['order'] <=> $b['order'];
    });

    $wan = [];
    $inner = [];
    foreach ($cards as $card) {
        if ($card['type'] === 'WAN') {
            $wan[] = $card;
        } else {
            $inner[] = $card;
        }
    }

    $left_out = 0;
    $wan_left_out = 0;
    if (count($wan) > NETWORK_DIAGRAM_MAX_WAN) {
        $wan_left_out = count($wan) - NETWORK_DIAGRAM_MAX_WAN;
        $wan = array_slice($wan, 0, NETWORK_DIAGRAM_MAX_WAN);
    }
    if (count($inner) > NETWORK_DIAGRAM_MAX_CARDS) {
        $left_out = count($inner) - (NETWORK_DIAGRAM_MAX_CARDS - 1);
        $inner = array_slice($inner, 0, NETWORK_DIAGRAM_MAX_CARDS - 1);
    }

    $hub = array_values(array_unique(array_filter(array_map('trim', $firewalls), 'strlen')));
    sort($hub, SORT_NATURAL | SORT_FLAG_CASE);

    return ['wan' => $wan, 'hub' => $hub, 'cards' => $inner, 'left_out' => $left_out, 'wan_left_out' => $wan_left_out, 'total' => count($networks)];
}

/** A text element. */
function networkDiagramText($x, $y, $text, $size, $fill, $options = []) {
    $font = $options['font'] ?? NETWORK_DIAGRAM_FONT;
    $bold = !empty($options['bold']);
    $anchor = $options['anchor'] ?? 'start';
    return '<text x="' . round($x, 1) . '" y="' . round($y, 1) . '" font-family="' . $font . '" font-size="' . $size . '"'
        . ($bold ? ' font-weight="bold"' : '') . ($anchor !== 'start' ? ' text-anchor="' . $anchor . '"' : '')
        . ' fill="' . $fill . '">' . networkDiagramEsc($text) . '</text>';
}

/** A card - white, with a shadow and a strip of the network's colour along its top. */
function networkDiagramCard($x, $y, $w, $h, array $card) {
    $color = $card['color'];
    $pad = 14;
    $inner_w = $w - 2 * $pad;
    $svg = '<rect x="' . round($x, 1) . '" y="' . round($y + 2.5, 1) . '" width="' . round($w, 1) . '" height="' . round($h, 1) . '" rx="14" fill="#e6ebf2"/>';
    $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($w, 1) . '" height="' . round($h, 1) . '" rx="14" fill="#ffffff" stroke="#e2e8f0" stroke-width="1"/>';
    // The strip: the card's own rounded top, cut off 5 px down
    $svg .= '<path d="M' . round($x + 3.28, 2) . ',' . round($y + 5, 2) . ' A14,14 0 0 1 ' . round($x + 14, 2) . ',' . round($y, 2)
        . ' H' . round($x + $w - 14, 2) . ' A14,14 0 0 1 ' . round($x + $w - 3.28, 2) . ',' . round($y + 5, 2) . ' Z" fill="' . $color . '"/>';

    $cx = $x + $pad;
    $cy = $y + 34;

    // The type, as a pill; the VLAN to its right
    $type_w = networkDiagramTextWidth(strtoupper($card['type']), 9, true) + 16;
    $svg .= '<rect x="' . round($cx, 1) . '" y="' . round($cy - 12, 1) . '" width="' . round($type_w, 1) . '" height="18" rx="9" fill="' . networkDiagramTint($color, 0.14) . '"/>';
    $svg .= networkDiagramText($cx + $type_w / 2, $cy + 0.5, strtoupper($card['type']), 9, $color, ['bold' => true, 'anchor' => 'middle']);
    if ($card['vlan'] > 0) {
        $svg .= networkDiagramText($x + $w - $pad, $cy + 0.5, 'VLAN ' . $card['vlan'], 10, '#64748b', ['anchor' => 'end', 'bold' => true]);
    }
    $cy += 26;

    // The name, on one or two lines
    foreach (networkDiagramWrap($card['name'], 13, $inner_w, 2, true) as $line) {
        $svg .= networkDiagramText($cx, $cy, $line, 13, '#0f172a', ['bold' => true]);
        $cy += 16;
    }
    $cy += 1;

    if ($card['cidr'] !== '') {
        $svg .= networkDiagramText($cx, $cy, networkDiagramFit($card['cidr'], 10.5, $inner_w), 10.5, '#334155', ['font' => 'Courier']);
        $cy += 14;
    }
    if ($card['gateway'] !== '') {
        $svg .= networkDiagramText($cx, $cy, networkDiagramFit('Gateway ' . $card['gateway'], 9.5, $inner_w), 9.5, '#94a3b8');
        $cy += 14;
    }
    $cy += 6;

    // What it is for
    $footer_h = 26;
    $role_lines = $h >= 230 ? 3 : 2;
    $key_room = $h - ($cy - $y) - $footer_h - 8;
    // The role gives way to the equipment on a short card
    $role_lines = max(0, min($role_lines, (int) floor(($key_room - 14) / 12.5)));
    if ($card['role'] !== '' && $role_lines > 0) {
        foreach (networkDiagramWrap($card['role'], 9.5, $inner_w, $role_lines, false) as $line) {
            $svg .= networkDiagramText($cx, $cy, $line, 9.5, '#64748b');
            $cy += 12.5;
        }
        $cy += 4;
    }

    // The main equipment
    $key_lines = (int) floor(($h - ($cy - $y) - $footer_h) / 13.5);
    $key_lines = max(0, $key_lines);
    $shown = array_slice($card['key'], 0, $key_lines);
    $more = 0;
    if (count($card['key']) > $key_lines && $key_lines >= 2) {
        // The last line says how many more there are
        $shown = array_slice($card['key'], 0, $key_lines - 1);
        $more = count($card['key']) - ($key_lines - 1);
    }
    foreach ($shown as $name) {
        $svg .= '<circle cx="' . round($cx + 3, 1) . '" cy="' . round($cy - 3, 1) . '" r="2.5" fill="' . $color . '"/>';
        $svg .= networkDiagramText($cx + 12, $cy, networkDiagramFit($name, 10, $inner_w - 12), 10, '#334155');
        $cy += 13.5;
    }
    if ($more > 0) {
        $svg .= networkDiagramText($cx + 12, $cy, '+' . $more . ' more', 10, '#94a3b8');
    }

    // The foot: how many devices, and where
    $foot_y = $y + $h - 12;
    $svg .= '<line x1="' . round($cx, 1) . '" y1="' . round($foot_y - 14, 1) . '" x2="' . round($x + $w - $pad, 1) . '" y2="' . round($foot_y - 14, 1) . '" stroke="#eef2f6" stroke-width="1"/>';
    $count_text = $card['count'] . ($card['count'] === 1 ? ' device' : ' devices');
    $svg .= networkDiagramText($cx, $foot_y, $count_text, 9.5, '#475569', ['bold' => true]);
    if ($card['location'] !== '') {
        $room = $inner_w - networkDiagramTextWidth($count_text, 9.5, true) - 10;
        if ($room > 30) {
            $svg .= networkDiagramText($x + $w - $pad, $foot_y, networkDiagramFit($card['location'], 9.5, $room), 9.5, '#94a3b8', ['anchor' => 'end']);
        }
    }
    return $svg;
}

/** The card that says how many networks did not fit. */
function networkDiagramMoreCard($x, $y, $w, $h, $left_out) {
    $svg = '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($w, 1) . '" height="' . round($h, 1) . '" rx="14" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="6,5"/>';
    $svg .= networkDiagramText($x + $w / 2, $y + $h / 2 - 2, '+' . $left_out, 26, '#94a3b8', ['bold' => true, 'anchor' => 'middle']);
    $svg .= networkDiagramText($x + $w / 2, $y + $h / 2 + 18, $left_out === 1 ? 'other network' : 'other networks', 10.5, '#94a3b8', ['anchor' => 'middle']);
    return $svg;
}

/** A line of the picture, drawn behind the cards. */
function networkDiagramLine($x1, $y1, $x2, $y2) {
    return '<line x1="' . round($x1, 1) . '" y1="' . round($y1, 1) . '" x2="' . round($x2, 1) . '" y2="' . round($y2, 1) . '" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round"/>';
}

/**
 * The diagram as SVG, 1123 x 794 - an A4 landscape page.
 *
 * $model is networkDiagramModel()'s; $client_name and $date_text go in the heading.
 */
function networkDiagramSvg(array $model, $client_name, $date_text) {
    $W = NETWORK_DIAGRAM_WIDTH;
    $H = NETWORK_DIAGRAM_HEIGHT;
    $M = NETWORK_DIAGRAM_MARGIN;
    $wan = $model['wan'];
    $cards = $model['cards'];
    $gap = 16;

    $lines = '';   // connectors, drawn first so the cards sit on top
    $boxes = '';

    // The heading
    $head = networkDiagramText($M, 52, networkDiagramFit($client_name, 26, $W - 2 * $M - 220, true), 26, '#0f172a', ['bold' => true]);
    $head .= networkDiagramText($M, 74, 'Network overview', 13, '#64748b');
    $head .= networkDiagramText($W - $M, 52, $date_text, 11, '#64748b', ['anchor' => 'end']);
    $head .= networkDiagramText($W - $M, 70, 'Diagram of principle', 10, '#94a3b8', ['anchor' => 'end']);
    $head .= '<line x1="' . $M . '" y1="92" x2="' . ($W - $M) . '" y2="92" stroke="#e2e8f0" stroke-width="1"/>';

    $y = 112;

    // Internet connections
    $hub_w = 420;
    $hub_x = ($W - $hub_w) / 2;
    $wan_bottom = null;
    if ($wan) {
        $wan_h = 66;
        $wan_gap = 18;
        $wan_w = min(250, (($W - 2 * $M) - $wan_gap * (count($wan) - 1)) / count($wan));
        $total = $wan_w * count($wan) + $wan_gap * (count($wan) - 1);
        $x = ($W - $total) / 2;
        $boxes .= networkDiagramText($W / 2, $y + 2, 'INTERNET', 9, '#94a3b8', ['bold' => true, 'anchor' => 'middle']);
        $y += 14;
        $centers = [];
        foreach ($wan as $card) {
            $boxes .= networkDiagramWanCard($x, $y, $wan_w, $wan_h, $card);
            $centers[] = $x + $wan_w / 2;
            $x += $wan_w + $wan_gap;
        }
        $wan_bottom = $y + $wan_h;
        $y = $wan_bottom + 28;
        $bus_y = $wan_bottom + 14;
        foreach ($centers as $c) {
            $lines .= networkDiagramLine($c, $wan_bottom, $c, $bus_y);
        }
        if (count($centers) > 1) {
            $lines .= networkDiagramLine(min($centers), $bus_y, max($centers), $bus_y);
        }
        $lines .= networkDiagramLine($W / 2, $bus_y, $W / 2, $y);
        if ($model['wan_left_out'] > 0) {
            $boxes .= networkDiagramText($W - $M, $wan_bottom - 6, '+' . $model['wan_left_out'] . ' other internet connection' . ($model['wan_left_out'] === 1 ? '' : 's'), 9.5, '#94a3b8', ['anchor' => 'end']);
        }
    }

    // The firewall / router
    $hub_h = 50;
    $hub_names = $model['hub'];
    if ($hub_names) {
        $hub_text = implode('  ·  ', array_slice($hub_names, 0, 3)) . (count($hub_names) > 3 ? '  +' . (count($hub_names) - 3) : '');
        $boxes .= '<rect x="' . round($hub_x, 1) . '" y="' . round($y, 1) . '" width="' . $hub_w . '" height="' . $hub_h . '" rx="16" fill="#0f172a"/>';
        $boxes .= networkDiagramText($W / 2, $y + 20, count($hub_names) > 1 ? 'FIREWALLS / ROUTERS' : 'FIREWALL / ROUTER', 9, '#94a3b8', ['bold' => true, 'anchor' => 'middle']);
        $boxes .= networkDiagramText($W / 2, $y + 39, networkDiagramFit($hub_text, 15, $hub_w - 40, true), 15, '#ffffff', ['bold' => true, 'anchor' => 'middle']);
    } else {
        $boxes .= '<rect x="' . round($hub_x, 1) . '" y="' . round($y, 1) . '" width="' . $hub_w . '" height="' . $hub_h . '" rx="16" fill="#ffffff" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="6,5"/>';
        $boxes .= networkDiagramText($W / 2, $y + 22, 'FIREWALL / ROUTER', 9, '#94a3b8', ['bold' => true, 'anchor' => 'middle']);
        $boxes .= networkDiagramText($W / 2, $y + 40, 'Not documented', 13, '#94a3b8', ['anchor' => 'middle']);
    }
    $hub_bottom = $y + $hub_h;

    // The networks
    $count = count($cards) + ($model['left_out'] > 0 ? 1 : 0);
    if ($count > 0) {
        $rows = $count <= 6 ? 1 : 2;
        $cols = (int) ceil($count / $rows);
        $card_w = min(300, (($W - 2 * $M) - $gap * ($cols - 1)) / $cols);
        $top = $hub_bottom + 34;
        $bottom = $H - 50;
        $row_gap = 26;
        $card_h = min(250, ($bottom - $top - $row_gap * ($rows - 1)) / $rows);
        $all = $cards;
        if ($model['left_out'] > 0) {
            $all[] = ['more' => $model['left_out']];
        }

        $bus1 = $hub_bottom + 17;
        $lines .= networkDiagramLine($W / 2, $hub_bottom, $W / 2, $bus1);
        $row_centers = [];
        foreach ($all as $i => $card) {
            $row = intdiv($i, $cols);
            $col = $i % $cols;
            $in_row = min($cols, $count - $row * $cols);
            // A short last row is centred
            $row_w = $in_row * $card_w + ($in_row - 1) * $gap;
            $x = ($W - $row_w) / 2 + $col * ($card_w + $gap);
            $cy = $top + $row * ($card_h + $row_gap);
            $row_centers[$row][] = $x + $card_w / 2;
            $boxes .= isset($card['more']) ? networkDiagramMoreCard($x, $cy, $card_w, $card_h, $card['more']) : networkDiagramCard($x, $cy, $card_w, $card_h, $card);
        }
        foreach ($row_centers as $row => $centers) {
            $row_top = $top + $row * ($card_h + $row_gap);
            $bus = $row === 0 ? $bus1 : $row_top - $row_gap / 2;
            foreach ($centers as $c) {
                $lines .= networkDiagramLine($c, $bus, $c, $row_top);
            }
            if ($row > 0) {
                // The second row is fed down the left margin, past the first row's cards
                $lines .= networkDiagramLine($M / 2, $bus1, $M / 2, $bus);
                $lines .= networkDiagramLine($M / 2, $bus, max($centers), $bus);
            } else {
                $left = min(min($centers), $W / 2);
                $lines .= networkDiagramLine($left, $bus, max(max($centers), $W / 2), $bus);
                if ($rows > 1) {
                    $lines .= networkDiagramLine($M / 2, $bus, $left, $bus);
                }
            }
        }
    } else {
        $boxes .= networkDiagramText($W / 2, $hub_bottom + 70, 'No other networks are documented for this client.', 13, '#94a3b8', ['anchor' => 'middle']);
    }

    $foot = networkDiagramText($M, $H - 22, 'Built from the networks and assets documented in ITFlow. Not every device is shown.', 9, '#94a3b8');
    $foot .= networkDiagramText($W - $M, $H - 22, $model['total'] . ($model['total'] === 1 ? ' network' : ' networks'), 9, '#94a3b8', ['anchor' => 'end']);

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $W . '" height="' . $H . '" viewBox="0 0 ' . $W . ' ' . $H . '">'
        . '<rect x="0" y="0" width="' . $W . '" height="' . $H . '" fill="#f8fafc"/>'
        . $head . $lines . $boxes . $foot . '</svg>';
}

/** An internet connection: a darker, flatter card than a network's. */
function networkDiagramWanCard($x, $y, $w, $h, array $card) {
    $pad = 14;
    $inner_w = $w - 2 * $pad;
    $svg = '<rect x="' . round($x, 1) . '" y="' . round($y + 2.5, 1) . '" width="' . round($w, 1) . '" height="' . $h . '" rx="14" fill="#e6ebf2"/>';
    $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($w, 1) . '" height="' . $h . '" rx="14" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>';
    $svg .= '<circle cx="' . round($x + $pad + 6, 1) . '" cy="' . round($y + 22, 1) . '" r="6" fill="' . $card['color'] . '"/>';
    $svg .= networkDiagramText($x + $pad + 20, $y + 26, networkDiagramFit($card['name'], 13, $inner_w - 20, true), 13, '#0f172a', ['bold' => true]);
    $detail = trim($card['cidr'] . ($card['gateway'] !== '' ? ($card['cidr'] !== '' ? '  ·  ' : '') . 'Gateway ' . $card['gateway'] : ''));
    $row_y = $y + 46;
    if ($detail !== '') {
        $svg .= networkDiagramText($x + $pad, $row_y, networkDiagramFit($detail, 9.5, $inner_w), 9.5, '#334155', ['font' => 'Courier']);
        $row_y += 14;
    }
    if ($card['role'] !== '') {
        $svg .= networkDiagramText($x + $pad, $row_y, networkDiagramFit($card['role'], 9.5, $inner_w), 9.5, '#64748b');
    }
    return $svg;
}

/** The client's networks, the equipment on them and its firewalls, ready for networkDiagramModel(). */
function networkDiagramLoad($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $networks = [];
    $sql = mysqli_query($mysqli, "SELECT networks.*, location_name, vendor_name
        FROM networks
        LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0
        LEFT JOIN vendors ON vendor_id = network_vendor_id AND network_vendor_id > 0
        WHERE network_client_id = $client_id AND network_archived_at IS NULL
        ORDER BY network_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $networks[] = $row;
    }

    $devices = [];
    if ($networks) {
        $in = implode(',', array_map('intval', array_column($networks, 'network_id')));
        $sql = mysqli_query($mysqli, "SELECT interface_network_id, asset_id, asset_name, asset_type
            FROM asset_interfaces
            JOIN assets ON asset_id = interface_asset_id
            WHERE interface_network_id IN ($in) AND interface_archived_at IS NULL AND asset_archived_at IS NULL
            ORDER BY asset_name");
        while ($row = mysqli_fetch_assoc($sql)) {
            $devices[intval($row['interface_network_id'])][] = $row;
        }
    }

    $firewalls = [];
    $sql = mysqli_query($mysqli, "SELECT asset_name FROM assets
        WHERE asset_client_id = $client_id AND asset_type = 'Firewall/Router' AND asset_archived_at IS NULL
        ORDER BY asset_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $firewalls[] = $row['asset_name'];
    }

    return [$networks, $devices, $firewalls];
}

/** The diagram for a client, as SVG. */
function networkDiagramForClient($client_id, $client_name) {
    list($networks, $devices, $firewalls) = networkDiagramLoad($client_id);
    return networkDiagramSvg(networkDiagramModel($networks, $devices, $firewalls), $client_name, date('Y-m-d'));
}

/** The diagram on an A4 landscape PDF (the whole page), as the PDF's bytes. TCPDF draws the SVG itself. */
function networkDiagramPdf($svg, $title) {
    if (!class_exists('TCPDF')) {
        require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    }
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('ITFlow');
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();
    $pdf->ImageSVG('@' . $svg, 0, 0, 297, 210, '', '', '', 0, false);
    return $pdf->Output('network-diagram.pdf', 'S');
}
