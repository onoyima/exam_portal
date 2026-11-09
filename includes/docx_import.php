<?php
/**
 * Word (.docx) question import.
 *
 * Lecturers type science papers in Word: subscripts (H2O), superscripts (x2,
 * cm3), Greek from the Symbol font, and equations made with Insert > Equation.
 * Saving that as .txt throws most of it away, so this reads the .docx directly
 * and turns each paragraph into one line of the normal TXT question format:
 *
 *   superscript / subscript runs  ->  <sup>..</sup> / <sub>..</sub>
 *   bold / italic / underline     ->  <b> <i> <u>
 *   Symbol / Wingdings characters ->  the real Unicode character
 *   Word equations (OMML)         ->  LaTeX inside \( .. \) or \[ .. \] (MathJax)
 *   pictures                      ->  saved to uploads/, attached as IMAGE:
 *   automatic list numbering      ->  "A." / "1." written in front of the text
 *
 * The produced text then goes through qe_parse_txt(), so the question layout
 * rules are exactly the same as for a .txt upload.
 */

const DX_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const DX_M = 'http://schemas.openxmlformats.org/officeDocument/2006/math';
const DX_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
const DX_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
const DX_V = 'urn:schemas-microsoft-com:vml';

/**
 * @return array{text: string, warnings: string[]}|array{error: string}
 */
function dx_docx_to_txt(string $path, string $upload_dir): array
{
    if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) {
        return ['error' => 'Word import needs the PHP "zip" and "dom" extensions. In XAMPP open php.ini, remove the ";" in front of extension=zip and restart Apache.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['error' => 'This file is not a valid .docx document. Old .doc files must first be saved as .docx (File > Save As > Word Document).'];
    }
    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) {
        $zip->close();
        return ['error' => 'The Word file has no document body.'];
    }

    $ctx = [
        'zip' => $zip,
        'rels' => dx_relationships($zip),
        'numbering' => dx_numbering($zip),
        'styles' => dx_style_numbering($zip),
        'counters' => [],
        'upload_dir' => rtrim($upload_dir, '/\\'),
        'images' => 0,
        'old_equations' => 0,
        'warnings' => [],
    ];

    $doc = new DOMDocument();
    $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('w', DX_W);
    $xp->registerNamespace('m', DX_M);

    $lines = [];
    // Paragraphs in document order, including those inside tables. A
    // paragraph nested inside another (text boxes) is skipped to avoid
    // reading it twice.
    foreach ($xp->query('//w:body//w:p[not(ancestor::w:p)]') as $p) {
        [$text, $images] = dx_paragraph($p, $ctx);
        $text = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $text));
        if ($text !== '') {
            $lines[] = $text;
        } elseif (!$images) {
            $lines[] = '';
        }
        foreach ($images as $img) {
            $lines[] = 'IMAGE: ' . $img;
        }
    }
    $zip->close();

    if ($ctx['old_equations']) {
        $ctx['warnings'][] = $ctx['old_equations'] . ' equation(s) were made with the old Equation Editor or MathType and could not be read. '
            . 'Retype them with Insert > Equation in Word, or paste them as a picture.';
    }
    return ['text' => implode("\n", $lines), 'warnings' => $ctx['warnings']];
}

function dx_relationships(ZipArchive $zip): array
{
    $out = [];
    $xml = $zip->getFromName('word/_rels/document.xml.rels');
    if ($xml === false) {
        return $out;
    }
    $d = new DOMDocument();
    $d->loadXML($xml, LIBXML_NONET);
    foreach ($d->getElementsByTagName('Relationship') as $r) {
        $out[$r->getAttribute('Id')] = $r->getAttribute('Target');
    }
    return $out;
}

/** numId => [ilvl => [fmt, lvlText, start]] */
function dx_numbering(ZipArchive $zip): array
{
    $xml = $zip->getFromName('word/numbering.xml');
    if ($xml === false) {
        return [];
    }
    $d = new DOMDocument();
    $d->loadXML($xml, LIBXML_NONET);
    $xp = new DOMXPath($d);
    $xp->registerNamespace('w', DX_W);
    $abstract = [];
    foreach ($xp->query('//w:abstractNum') as $an) {
        $levels = [];
        foreach ($xp->query('w:lvl', $an) as $lvl) {
            $levels[(int) $lvl->getAttributeNS(DX_W, 'ilvl')] = [
                'fmt' => dx_attr($xp, 'w:numFmt', $lvl) ?: 'decimal',
                'text' => dx_attr($xp, 'w:lvlText', $lvl) ?? '%1.',
                'start' => (int) (dx_attr($xp, 'w:start', $lvl) ?: 1),
            ];
        }
        $abstract[$an->getAttributeNS(DX_W, 'abstractNumId')] = $levels;
    }
    $out = [];
    foreach ($xp->query('//w:num') as $num) {
        $aid = dx_attr($xp, 'w:abstractNumId', $num);
        $levels = $abstract[$aid] ?? [];
        foreach ($xp->query('w:lvlOverride', $num) as $ov) {
            $il = (int) $ov->getAttributeNS(DX_W, 'ilvl');
            $start = dx_attr($xp, 'w:startOverride', $ov);
            if ($start !== null && isset($levels[$il])) {
                $levels[$il]['start'] = (int) $start;
            }
        }
        $out[$num->getAttributeNS(DX_W, 'numId')] = $levels;
    }
    return $out;
}

function dx_attr(DOMXPath $xp, string $child, DOMNode $ctx): ?string
{
    $n = $xp->query($child, $ctx)->item(0);
    return $n instanceof DOMElement ? $n->getAttributeNS(DX_W, 'val') : null;
}

function dx_child(DOMElement $el, string $ns, string $name): ?DOMElement
{
    foreach ($el->childNodes as $c) {
        if ($c instanceof DOMElement && $c->namespaceURI === $ns && $c->localName === $name) {
            return $c;
        }
    }
    return null;
}

/** @return array{0: string, 1: string[]} text and saved image names */
function dx_paragraph(DOMElement $p, array &$ctx): array
{
    $text = '';
    $images = [];

    $pPr = dx_child($p, DX_W, 'pPr');
    $numPr = $pPr ? dx_child($pPr, DX_W, 'numPr') : null;
    if ($numPr && dx_child($numPr, DX_W, 'numId')) {
        $il = dx_child($numPr, DX_W, 'ilvl');
        $text .= dx_list_label(dx_child($numPr, DX_W, 'numId')->getAttributeNS(DX_W, 'val'), $il ? (int) $il->getAttributeNS(DX_W, 'val') : 0, $ctx);
    } elseif ($pPr && ($ps = dx_child($pPr, DX_W, 'pStyle'))) {
        // numbering that comes from the paragraph style ("List Number", "List Alpha")
        $sid = $ps->getAttributeNS(DX_W, 'val');
        for ($guard = 0; $sid !== '' && $guard < 10; $guard++) {
            $st = $ctx['styles'][$sid] ?? null;
            if (!$st) {
                break;
            }
            if ($st['numId'] !== null) {
                $text .= dx_list_label($st['numId'], $st['ilvl'], $ctx);
                break;
            }
            $sid = $st['basedOn'];
        }
    }
    $text .= dx_inline($p, $ctx, $images);
    $lines = array_map('dx_tidy_line', explode("\n", $text));
    return [implode("\n", $lines), $images];
}

/**
 * Word splits text into many runs, and lecturers often bold the "A." or the
 * whole ANSWER line. The TXT layout rules look at plain text, so formatting is
 * merged and pulled off the parts the parser has to recognise.
 */
function dx_tidy_line(string $line): string
{
    $tags = 'sup|sub|b|i|u';
    do {
        $before = $line;
        $line = preg_replace("#</($tags)>(\s*)<\\1>#", '$2', $line);
        $line = preg_replace("#<($tags)>(\s*)</\\1>#", '$2', $line);
    } while ($line !== $before);

    $plain = trim(preg_replace("#</?($tags)>#", '', $line));
    if (strncmp($plain, '//', 2) === 0 || preg_match('/^(TYPE|MARKS?|IMAGE|SCORING|ANSWERS?|ANS|KEY|CORRECT)\s*[:=]/i', $plain)) {
        return $plain;
    }
    // leading label such as "<b>A.</b> text", "<b>1. What</b>", "<i>(c)</i> text"
    if (preg_match("#^((?:<(?:$tags)>\s*)+)((?:Q(?:uestion)?\s*)?\(?[A-Za-z0-9]{1,4}\s*[\.\):]\s*)(.*)$#s", trim($line), $m)
        && preg_match('/^(?:Q(?:uestion)?\s*)?\(?([A-Ja-j]|\d{1,4}|i{1,3}|iv|vi{0,3}|ix|x)\s*[\.\):]/i', $m[2])) {
        $line = $m[2] . $m[1] . $m[3];
        $line = preg_replace("#<($tags)>(\s*)</\\1>#", '$2', $line);
    }
    // trailing true/false marker in bold: "... <b>(T)</b>"
    $line = preg_replace("#<($tags)>\s*([\[\(]\s*(?:T|F|TRUE|FALSE)\s*[\]\)])\s*</\\1>\s*$#i", ' $2', $line);
    return rtrim($line);
}

/** styleId => numId / ilvl / basedOn, for styles that carry list numbering */
function dx_style_numbering(ZipArchive $zip): array
{
    $xml = $zip->getFromName('word/styles.xml');
    if ($xml === false) {
        return [];
    }
    $d = new DOMDocument();
    $d->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
    $xp = new DOMXPath($d);
    $xp->registerNamespace('w', DX_W);
    $out = [];
    foreach ($xp->query('//w:style[@w:type="paragraph"]') as $st) {
        $numId = dx_attr($xp, 'w:pPr/w:numPr/w:numId', $st);
        $ilvl = dx_attr($xp, 'w:pPr/w:numPr/w:ilvl', $st);
        $out[$st->getAttributeNS(DX_W, 'styleId')] = [
            'numId' => $numId,
            'ilvl' => (int) $ilvl,
            'basedOn' => (string) dx_attr($xp, 'w:basedOn', $st),
        ];
    }
    return $out;
}

function dx_list_label(string $numId, int $ilvl, array &$ctx): string
{
    $levels = $ctx['numbering'][$numId] ?? null;
    if ($numId === '' || $numId === '0' || !$levels || !isset($levels[$ilvl])) {
        return '';
    }
    $c = &$ctx['counters'][$numId];
    if ($c === null) {
        $c = [];
    }
    $c[$ilvl] = isset($c[$ilvl]) ? $c[$ilvl] + 1 : $levels[$ilvl]['start'];
    foreach (array_keys($c) as $k) {
        if ($k > $ilvl) {
            unset($c[$k]);
        }
    }
    if ($levels[$ilvl]['fmt'] === 'bullet' || $levels[$ilvl]['fmt'] === 'none') {
        return '';
    }
    $label = preg_replace_callback('/%(\d)/', function ($m) use ($c, $levels) {
        $lv = (int) $m[1] - 1;
        $n = $c[$lv] ?? ($levels[$lv]['start'] ?? 1);
        return dx_format_number($n, $levels[$lv]['fmt'] ?? 'decimal');
    }, $levels[$ilvl]['text']);
    return $label . ' ';
}

function dx_format_number(int $n, string $fmt): string
{
    switch ($fmt) {
        case 'upperLetter':
            return dx_letters($n);
        case 'lowerLetter':
            return strtolower(dx_letters($n));
        case 'upperRoman':
            return dx_roman($n);
        case 'lowerRoman':
            return strtolower(dx_roman($n));
        default:
            return (string) $n;
    }
}

function dx_letters(int $n): string
{
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + $n % 26) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

function dx_roman(int $n): string
{
    $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
    $s = '';
    foreach ($map as $r => $v) {
        while ($n >= $v) {
            $s .= $r;
            $n -= $v;
        }
    }
    return $s;
}

/** Walks runs, hyperlinks, fields and equations inside a paragraph. */
function dx_inline(DOMElement $el, array &$ctx, array &$images): string
{
    $out = '';
    foreach ($el->childNodes as $c) {
        if (!$c instanceof DOMElement) {
            continue;
        }
        if ($c->namespaceURI === DX_M && $c->localName === 'oMathPara') {
            $parts = [];
            foreach ($c->childNodes as $m) {
                if ($m instanceof DOMElement && $m->localName === 'oMath') {
                    $parts[] = dx_math($m);
                }
            }
            $out .= ' \\[' . implode(' \\\\ ', $parts) . '\\] ';
            continue;
        }
        if ($c->namespaceURI === DX_M && $c->localName === 'oMath') {
            $out .= '\\(' . dx_math($c) . '\\)';
            continue;
        }
        if ($c->namespaceURI !== DX_W) {
            // mc:AlternateContent etc.: take the first readable branch
            if ($c->localName === 'AlternateContent') {
                foreach ($c->childNodes as $alt) {
                    if ($alt instanceof DOMElement) {
                        $out .= dx_inline($alt, $ctx, $images);
                        break;
                    }
                }
            }
            continue;
        }
        switch ($c->localName) {
            case 'r':
                $out .= dx_run($c, $ctx, $images);
                break;
            case 'hyperlink':
            case 'smartTag':
            case 'ins':
            case 'fldSimple':
            case 'customXml':
            case 'sdt':
            case 'sdtContent':
                $out .= dx_inline($c, $ctx, $images);
                break;
            // w:del (tracked deletions) and w:pPr are ignored on purpose
        }
    }
    return $out;
}

function dx_run(DOMElement $r, array &$ctx, array &$images): string
{
    $rPr = dx_child($r, DX_W, 'rPr');
    $fmt = ['sup' => false, 'sub' => false, 'b' => false, 'i' => false, 'u' => false, 'font' => ''];
    if ($rPr) {
        $va = dx_child($rPr, DX_W, 'vertAlign');
        if ($va) {
            $v = $va->getAttributeNS(DX_W, 'val');
            $fmt['sup'] = $v === 'superscript';
            $fmt['sub'] = $v === 'subscript';
        }
        $pos = dx_child($rPr, DX_W, 'position');
        if ($pos && !$va) {
            // raised / lowered text used as a poor man's superscript
            $pv = (int) $pos->getAttributeNS(DX_W, 'val');
            $fmt['sup'] = $pv >= 4;
            $fmt['sub'] = $pv <= -4;
        }
        foreach (['b' => 'b', 'i' => 'i', 'u' => 'u'] as $tag => $name) {
            $n = dx_child($rPr, DX_W, $name);
            if ($n) {
                $val = $n->getAttributeNS(DX_W, 'val');
                $fmt[$tag] = !in_array($val, ['0', 'false', 'none'], true);
            }
        }
        $fonts = dx_child($rPr, DX_W, 'rFonts');
        if ($fonts) {
            $fmt['font'] = $fonts->getAttributeNS(DX_W, 'ascii') ?: $fonts->getAttributeNS(DX_W, 'hAnsi');
        }
    }

    $text = '';
    foreach ($r->childNodes as $c) {
        if (!$c instanceof DOMElement) {
            continue;
        }
        if ($c->namespaceURI === DX_W) {
            switch ($c->localName) {
                case 't':
                    $text .= dx_font_text($c->textContent, $fmt['font']);
                    break;
                case 'tab':
                    $text .= ' ';
                    break;
                case 'br':
                case 'cr':
                    // A manual line break inside a paragraph: keep the line
                    // structure so "A. ..." on the next line is still an option.
                    $text .= "\n";
                    break;
                case 'sym':
                    $text .= dx_symbol_char($c->getAttributeNS(DX_W, 'char'), $c->getAttributeNS(DX_W, 'font'));
                    break;
                case 'noBreakHyphen':
                    $text .= '-';
                    break;
                case 'drawing':
                    foreach ($c->getElementsByTagNameNS(DX_A, 'blip') as $blip) {
                        $name = dx_save_image($blip->getAttributeNS(DX_R, 'embed'), $ctx);
                        if ($name) {
                            $images[] = $name;
                        }
                    }
                    break;
                case 'pict':
                case 'object':
                    $isEquation = false;
                    foreach ($c->getElementsByTagName('*') as $sub) {
                        if ($sub->localName === 'OLEObject' && preg_match('/Equation|MathType/i', $sub->getAttribute('ProgID'))) {
                            $isEquation = true;
                        }
                    }
                    if ($isEquation) {
                        $ctx['old_equations']++;
                        break;
                    }
                    foreach ($c->getElementsByTagNameNS(DX_V, 'imagedata') as $im) {
                        $name = dx_save_image($im->getAttributeNS(DX_R, 'id'), $ctx);
                        if ($name) {
                            $images[] = $name;
                        }
                    }
                    break;
            }
        } elseif ($c->localName === 'AlternateContent') {
            $text .= dx_run_alternate($c, $ctx, $images);
        }
    }
    if ($text === '') {
        return '';
    }

    // Escape now; the TXT parser and qe_safe_html() only let the formatting
    // tags below through, so a literal "<" typed by the lecturer stays text.
    $lines = explode("\n", $text);
    foreach ($lines as &$ln) {
        if ($ln === '') {
            continue;
        }
        $ln = dx_tex_safe($ln);
        foreach (['u', 'i', 'b'] as $t) {
            if ($fmt[$t]) {
                $ln = "<$t>$ln</$t>";
            }
        }
        if ($fmt['sup']) {
            $ln = "<sup>$ln</sup>";
        } elseif ($fmt['sub']) {
            $ln = "<sub>$ln</sub>";
        }
    }
    unset($ln);
    return implode("\n", $lines);
}

function dx_run_alternate(DOMElement $alt, array &$ctx, array &$images): string
{
    foreach ($alt->childNodes as $branch) {
        if ($branch instanceof DOMElement) {
            $fake = $branch->ownerDocument->createElementNS(DX_W, 'w:r');
            foreach ($branch->childNodes as $n) {
                $fake->appendChild($n->cloneNode(true));
            }
            return dx_run($fake, $ctx, $images);
        }
    }
    return '';
}

/**
 * A literal "<" from the document must not turn into a tag later. Wrapping it
 * as an entity-like placeholder is not possible in plain TXT, so a lone "<"
 * followed by a letter gets a thin space, which reads the same.
 */
function dx_tex_safe(string $s): string
{
    return preg_replace('/<(?=[A-Za-z\/])/', "<\u{2009}", $s);
}

function dx_save_image(string $rid, array &$ctx): ?string
{
    $target = $ctx['rels'][$rid] ?? '';
    if ($target === '' || preg_match('#^[a-z]+://#i', $target)) {
        return null;
    }
    $inZip = 'word/' . ltrim(str_replace('\\', '/', $target), '/');
    $inZip = preg_replace('#/[^/]+/\.\./#', '/', $inZip);
    $data = $ctx['zip']->getFromName($inZip);
    if ($data === false) {
        return null;
    }
    $info = @getimagesizefromstring($data);
    if (!$info) {
        $ctx['warnings'][] = 'A picture in ' . basename($inZip) . ' is in a format browsers cannot show (EMF/WMF). Paste it again as PNG or JPG.';
        return null;
    }
    $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', IMAGETYPE_BMP => 'bmp'][$info[2]] ?? null;
    if ($ext === null) {
        return null;
    }
    $name = 'docx_' . substr(sha1($data), 0, 16) . '.' . $ext;
    if (!is_dir($ctx['upload_dir'])) {
        @mkdir($ctx['upload_dir'], 0755, true);
    }
    $file = $ctx['upload_dir'] . '/' . $name;
    if (!is_file($file)) {
        file_put_contents($file, $data);
    }
    $ctx['images']++;
    return $name;
}

/* ---------------- Symbol / Wingdings fonts ---------------- */

/** Adobe Symbol font layout (0x20–0xFE) to Unicode. */
function dx_symbol_table(): array
{
    static $t = null;
    if ($t !== null) {
        return $t;
    }
    $t = [
        0x22 => '∀', 0x24 => '∃', 0x27 => '∋', 0x2A => '∗', 0x2D => '−', 0x40 => '≅',
        0x41 => 'Α', 0x42 => 'Β', 0x43 => 'Χ', 0x44 => 'Δ', 0x45 => 'Ε', 0x46 => 'Φ', 0x47 => 'Γ', 0x48 => 'Η',
        0x49 => 'Ι', 0x4A => 'ϑ', 0x4B => 'Κ', 0x4C => 'Λ', 0x4D => 'Μ', 0x4E => 'Ν', 0x4F => 'Ο', 0x50 => 'Π',
        0x51 => 'Θ', 0x52 => 'Ρ', 0x53 => 'Σ', 0x54 => 'Τ', 0x55 => 'Υ', 0x56 => 'ς', 0x57 => 'Ω', 0x58 => 'Ξ',
        0x59 => 'Ψ', 0x5A => 'Ζ', 0x5C => '∴', 0x5E => '⊥', 0x60 => '‾',
        0x61 => 'α', 0x62 => 'β', 0x63 => 'χ', 0x64 => 'δ', 0x65 => 'ε', 0x66 => 'φ', 0x67 => 'γ', 0x68 => 'η',
        0x69 => 'ι', 0x6A => 'ϕ', 0x6B => 'κ', 0x6C => 'λ', 0x6D => 'μ', 0x6E => 'ν', 0x6F => 'ο', 0x70 => 'π',
        0x71 => 'θ', 0x72 => 'ρ', 0x73 => 'σ', 0x74 => 'τ', 0x75 => 'υ', 0x76 => 'ϖ', 0x77 => 'ω', 0x78 => 'ξ',
        0x79 => 'ψ', 0x7A => 'ζ', 0x7E => '∼',
        0xA1 => 'ϒ', 0xA2 => '′', 0xA3 => '≤', 0xA4 => '⁄', 0xA5 => '∞', 0xA6 => 'ƒ', 0xA7 => '♣', 0xA8 => '♦',
        0xA9 => '♥', 0xAA => '♠', 0xAB => '↔', 0xAC => '←', 0xAD => '↑', 0xAE => '→', 0xAF => '↓', 0xB0 => '°',
        0xB1 => '±', 0xB2 => '″', 0xB3 => '≥', 0xB4 => '×', 0xB5 => '∝', 0xB6 => '∂', 0xB7 => '•', 0xB8 => '÷',
        0xB9 => '≠', 0xBA => '≡', 0xBB => '≈', 0xBC => '…', 0xBD => '|', 0xBE => '—', 0xBF => '↵',
        0xC0 => 'ℵ', 0xC1 => 'ℑ', 0xC2 => 'ℜ', 0xC3 => '℘', 0xC4 => '⊗', 0xC5 => '⊕', 0xC6 => '∅', 0xC7 => '∩',
        0xC8 => '∪', 0xC9 => '⊃', 0xCA => '⊇', 0xCB => '⊄', 0xCC => '⊂', 0xCD => '⊆', 0xCE => '∈', 0xCF => '∉',
        0xD0 => '∠', 0xD1 => '∇', 0xD2 => '®', 0xD3 => '©', 0xD4 => '™', 0xD5 => '∏', 0xD6 => '√', 0xD7 => '⋅',
        0xD8 => '¬', 0xD9 => '∧', 0xDA => '∨', 0xDB => '⇔', 0xDC => '⇐', 0xDD => '⇑', 0xDE => '⇒', 0xDF => '⇓',
        0xE0 => '◊', 0xE1 => '⟨', 0xE5 => '∑', 0xF1 => '⟩', 0xF2 => '∫', 0xF3 => '⌠', 0xF5 => '⌡',
    ];
    return $t;
}

function dx_symbol_char(string $hex, string $font): string
{
    $code = hexdec($hex);
    if ($code >= 0xF000) {
        $code -= 0xF000;
    }
    if (stripos($font, 'symbol') !== false) {
        $t = dx_symbol_table();
        if (isset($t[$code])) {
            return $t[$code];
        }
    }
    if (stripos($font, 'wingdings') !== false) {
        $wing = [0xFC => '✓', 0xFB => '✗', 0xE0 => '→', 0xDF => '←', 0xE8 => '➔', 0x9F => '•', 0x6C => '●', 0x6F => '□', 0xA8 => '◻'];
        return $wing[$code] ?? '';
    }
    return $code >= 0x20 ? mb_chr($code, 'UTF-8') : '';
}

/** Text typed with the Symbol font as ordinary letters ("a" shown as alpha). */
function dx_font_text(string $s, string $font): string
{
    if ($font === '' || stripos($font, 'symbol') === false) {
        return $s;
    }
    $t = dx_symbol_table();
    $out = '';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $code = mb_ord($ch, 'UTF-8');
        if ($code >= 0xF000) {
            $code -= 0xF000;
        }
        $out .= $t[$code] ?? $ch;
    }
    return $out;
}

/* ---------------- Word equations (OMML) to LaTeX ---------------- */

function dx_math(DOMElement $m): string
{
    return trim(preg_replace('/\s+/', ' ', dx_m_children($m)));
}

function dx_m_children(?DOMElement $el): string
{
    if (!$el) {
        return '';
    }
    $out = '';
    foreach ($el->childNodes as $c) {
        if ($c instanceof DOMElement) {
            $out .= dx_m_node($c);
        }
    }
    return $out;
}

function dx_m_arg(DOMElement $el, string $name): string
{
    return dx_m_children(dx_child($el, DX_M, $name));
}

function dx_m_val(DOMElement $el, string $prop, string $name, ?string $default = null): ?string
{
    $pr = dx_child($el, DX_M, $prop);
    $n = $pr ? dx_child($pr, DX_M, $name) : null;
    if (!$n) {
        return $default;
    }
    $v = $n->getAttributeNS(DX_M, 'val');
    return $v === '' ? $default : $v;
}

function dx_m_node(DOMElement $n): string
{
    if ($n->namespaceURI === DX_W) {
        // w:r inside math (rare) or properties
        return $n->localName === 'r' ? dx_m_text($n->textContent) : '';
    }
    if ($n->namespaceURI !== DX_M) {
        return '';
    }
    switch ($n->localName) {
        case 'r':
            $t = '';
            foreach ($n->childNodes as $c) {
                if ($c instanceof DOMElement && $c->localName === 't') {
                    $t .= $c->textContent;
                }
            }
            $plain = dx_m_val($n, 'rPr', 'nor') !== null || dx_m_val($n, 'rPr', 'sty') === 'p';
            $tex = dx_m_text($t);
            if ($plain && preg_match('/[A-Za-z]{2,}/', $t)) {
                return '\\mathrm{' . $tex . '}';
            }
            return $tex;
        case 'f':
            $type = dx_m_val($n, 'fPr', 'type', 'bar');
            $num = dx_m_arg($n, 'num');
            $den = dx_m_arg($n, 'den');
            if ($type === 'lin') {
                return '{' . $num . '}/{' . $den . '}';
            }
            if ($type === 'noBar') {
                return '\\genfrac{}{}{0pt}{}{' . $num . '}{' . $den . '}';
            }
            return '\\frac{' . $num . '}{' . $den . '}';
        case 'sSup':
            return '{' . dx_m_arg($n, 'e') . '}^{' . dx_m_arg($n, 'sup') . '}';
        case 'sSub':
            return '{' . dx_m_arg($n, 'e') . '}_{' . dx_m_arg($n, 'sub') . '}';
        case 'sSubSup':
            return '{' . dx_m_arg($n, 'e') . '}_{' . dx_m_arg($n, 'sub') . '}^{' . dx_m_arg($n, 'sup') . '}';
        case 'sPre':
            return '{}_{' . dx_m_arg($n, 'sub') . '}^{' . dx_m_arg($n, 'sup') . '}{' . dx_m_arg($n, 'e') . '}';
        case 'rad':
            $deg = dx_m_arg($n, 'deg');
            $hide = dx_m_val($n, 'radPr', 'degHide');
            if ($deg === '' || in_array($hide, ['1', 'on', 'true'], true)) {
                return '\\sqrt{' . dx_m_arg($n, 'e') . '}';
            }
            return '\\sqrt[' . $deg . ']{' . dx_m_arg($n, 'e') . '}';
        case 'd':
            $beg = dx_m_val($n, 'dPr', 'begChr', '(');
            $end = dx_m_val($n, 'dPr', 'endChr', ')');
            $sep = dx_m_val($n, 'dPr', 'sepChr', '|');
            $parts = [];
            foreach ($n->childNodes as $c) {
                if ($c instanceof DOMElement && $c->localName === 'e') {
                    $parts[] = dx_m_children($c);
                }
            }
            return '\\left' . dx_m_delim($beg) . ' ' . implode(' ' . dx_m_text($sep) . ' ', $parts) . ' \\right' . dx_m_delim($end);
        case 'nary':
            $chr = dx_m_val($n, 'naryPr', 'chr', '∫');
            $ops = ['∑' => '\\sum', '∏' => '\\prod', '∐' => '\\coprod', '∫' => '\\int', '∬' => '\\iint', '∭' => '\\iiint', '∮' => '\\oint',
                '⋃' => '\\bigcup', '⋂' => '\\bigcap', '⋁' => '\\bigvee', '⋀' => '\\bigwedge'];
            $op = $ops[$chr] ?? dx_m_text($chr);
            $sub = dx_m_arg($n, 'sub');
            $sup = dx_m_arg($n, 'sup');
            return $op . ($sub !== '' ? '_{' . $sub . '}' : '') . ($sup !== '' ? '^{' . $sup . '}' : '') . ' ' . dx_m_arg($n, 'e');
        case 'func':
            $name = trim(dx_m_arg($n, 'fName'));
            $plain = preg_replace('/^\\\\mathrm\{(.*)\}$/', '$1', $name);
            $known = ['sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'log', 'ln', 'exp', 'lim', 'max', 'min', 'sinh', 'cosh', 'tanh', 'arcsin', 'arccos', 'arctan', 'det'];
            if (in_array($plain, $known, true)) {
                $name = '\\' . $plain;
            }
            return $name . ' ' . dx_m_arg($n, 'e');
        case 'limLow':
            $base = trim(dx_m_arg($n, 'e'));
            $base = preg_replace('/^\\\\mathrm\{(lim|max|min)\}$/', '\\\\$1', $base);
            if (in_array($base, ['lim', 'max', 'min'], true)) {
                $base = '\\' . $base;
            }
            return $base . '_{' . dx_m_arg($n, 'lim') . '}';
        case 'limUpp':
            return '\\overset{' . dx_m_arg($n, 'lim') . '}{' . dx_m_arg($n, 'e') . '}';
        case 'acc':
            $chr = dx_m_val($n, 'accPr', 'chr', "\u{0302}");
            $acc = ["\u{0302}" => '\\hat', "\u{0303}" => '\\tilde', "\u{0304}" => '\\bar', "\u{0305}" => '\\overline', "\u{0307}" => '\\dot',
                "\u{0308}" => '\\ddot', "\u{20D7}" => '\\vec', "\u{20D1}" => '\\vec', "\u{0306}" => '\\breve', "\u{030C}" => '\\check', "\u{0301}" => '\\acute', "\u{0300}" => '\\grave'];
            return ($acc[$chr] ?? '\\hat') . '{' . dx_m_arg($n, 'e') . '}';
        case 'bar':
            $pos = dx_m_val($n, 'barPr', 'pos', 'bot');
            return ($pos === 'top' ? '\\overline{' : '\\underline{') . dx_m_arg($n, 'e') . '}';
        case 'groupChr':
            $chr = dx_m_val($n, 'groupChrPr', 'chr', '⏟');
            $pos = dx_m_val($n, 'groupChrPr', 'pos', 'bot');
            if ($chr === '⏟' || $chr === '⏞') {
                return ($pos === 'top' ? '\\overbrace{' : '\\underbrace{') . dx_m_arg($n, 'e') . '}';
            }
            // arrows with text over them, common in chemistry: A ->(heat) B
            $arrow = dx_m_text($chr);
            return ($pos === 'top' ? '\\overset{' : '\\underset{') . dx_m_arg($n, 'e') . '}{' . $arrow . '}';
        case 'eqArr':
            $rows = [];
            foreach ($n->childNodes as $c) {
                if ($c instanceof DOMElement && $c->localName === 'e') {
                    $rows[] = dx_m_children($c);
                }
            }
            return '\\begin{aligned}' . implode(' \\\\ ', $rows) . '\\end{aligned}';
        case 'm':
            $rows = [];
            foreach ($n->childNodes as $mr) {
                if ($mr instanceof DOMElement && $mr->localName === 'mr') {
                    $cells = [];
                    foreach ($mr->childNodes as $c) {
                        if ($c instanceof DOMElement && $c->localName === 'e') {
                            $cells[] = dx_m_children($c);
                        }
                    }
                    $rows[] = implode(' & ', $cells);
                }
            }
            return '\\begin{matrix}' . implode(' \\\\ ', $rows) . '\\end{matrix}';
        case 'box':
        case 'borderBox':
        case 'phant':
            return dx_m_arg($n, 'e');
        case 'e':
        case 'num':
        case 'den':
        case 'oMath':
            return dx_m_children($n);
        default:
            // property elements (*Pr), ctrlPr, argPr: nothing to print
            if (substr($n->localName, -2) === 'Pr') {
                return '';
            }
            return dx_m_children($n);
    }
}

function dx_m_delim(string $c): string
{
    $map = ['(' => '(', ')' => ')', '[' => '[', ']' => ']', '{' => '\\{', '}' => '\\}', '|' => '|', '‖' => '\\|',
        '⟨' => '\\langle', '⟩' => '\\rangle', '〈' => '\\langle', '〉' => '\\rangle', '⌊' => '\\lfloor', '⌋' => '\\rfloor', '⌈' => '\\lceil', '⌉' => '\\rceil', '' => '.'];
    return $map[$c] ?? '.';
}

/** Plain characters inside an equation, with TeX specials escaped. */
function dx_m_text(string $s): string
{
    static $map = null;
    if ($map === null) {
        $map = [
            '\\' => '\\backslash ', '{' => '\\{', '}' => '\\}', '#' => '\\#', '$' => '\\$', '%' => '\\%', '&' => '\\&', '_' => '\\_', '^' => '\\hat{}', '~' => '\\sim ',
            '×' => '\\times ', '÷' => '\\div ', '±' => '\\pm ', '∓' => '\\mp ', '·' => '\\cdot ', '⋅' => '\\cdot ', '∙' => '\\cdot ', '−' => '-',
            '≤' => '\\le ', '≥' => '\\ge ', '≠' => '\\ne ', '≈' => '\\approx ', '≡' => '\\equiv ', '∝' => '\\propto ', '∼' => '\\sim ', '≅' => '\\cong ',
            '→' => '\\rightarrow ', '←' => '\\leftarrow ', '↔' => '\\leftrightarrow ', '⇌' => '\\rightleftharpoons ', '⇒' => '\\Rightarrow ', '⇔' => '\\Leftrightarrow ',
            '⟶' => '\\longrightarrow ', '↑' => '\\uparrow ', '↓' => '\\downarrow ',
            '∞' => '\\infty ', '∂' => '\\partial ', '∇' => '\\nabla ', '∈' => '\\in ', '∉' => '\\notin ', '⊂' => '\\subset ', '⊆' => '\\subseteq ',
            '∪' => '\\cup ', '∩' => '\\cap ', '∅' => '\\emptyset ', '∀' => '\\forall ', '∃' => '\\exists ', '¬' => '\\neg ', '∧' => '\\wedge ', '∨' => '\\vee ',
            '°' => '^{\\circ}', '′' => "'", '″' => "''", '…' => '\\ldots ', '⋯' => '\\cdots ', 'ℏ' => '\\hbar ', 'Å' => '\\mathring{A}',
            'α' => '\\alpha ', 'β' => '\\beta ', 'γ' => '\\gamma ', 'δ' => '\\delta ', 'ε' => '\\varepsilon ', 'ϵ' => '\\epsilon ', 'ζ' => '\\zeta ', 'η' => '\\eta ',
            'θ' => '\\theta ', 'ϑ' => '\\vartheta ', 'ι' => '\\iota ', 'κ' => '\\kappa ', 'λ' => '\\lambda ', 'μ' => '\\mu ', 'µ' => '\\mu ', 'ν' => '\\nu ', 'ξ' => '\\xi ',
            'π' => '\\pi ', 'ρ' => '\\rho ', 'σ' => '\\sigma ', 'ς' => '\\varsigma ', 'τ' => '\\tau ', 'υ' => '\\upsilon ', 'φ' => '\\varphi ', 'ϕ' => '\\phi ',
            'χ' => '\\chi ', 'ψ' => '\\psi ', 'ω' => '\\omega ',
            'Γ' => '\\Gamma ', 'Δ' => '\\Delta ', 'Θ' => '\\Theta ', 'Λ' => '\\Lambda ', 'Ξ' => '\\Xi ', 'Π' => '\\Pi ', 'Σ' => '\\Sigma ', 'Υ' => '\\Upsilon ',
            'Φ' => '\\Phi ', 'Ψ' => '\\Psi ', 'Ω' => '\\Omega ',
            // Word's math italic letters (U+1D400 block) back to plain letters
        ];
    }
    $s = strtr($s, $map);
    // Mathematical alphanumerics (𝑥, 𝐀 ...) come out of some Word versions
    $s = preg_replace_callback('/[\x{1D400}-\x{1D7FF}]/u', function ($m) {
        $cp = mb_ord($m[0], 'UTF-8');
        if ($cp >= 0x1D7CE && $cp <= 0x1D7FF) {
            return (string) (($cp - 0x1D7CE) % 10);
        }
        $i = ($cp - 0x1D400) % 52;
        return chr($i < 26 ? 65 + $i : 97 + $i - 26);
    }, $s);
    return $s;
}
