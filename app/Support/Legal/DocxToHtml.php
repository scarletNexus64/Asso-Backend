<?php

namespace App\Support\Legal;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Convertit un document Word (.docx) en HTML simple, compatible avec
 * l'éditeur Quill du back-office : titres h2-h4, paragraphes, gras /
 * italique / souligné, listes, liens. Les tableaux deviennent des
 * paragraphes (Quill 1.3 ne gère pas les tableaux).
 *
 * Niveaux : le titre du document → h2, « heading 1 » → h3, « heading 2 » → h4
 * (le h1 est réservé au titre de la page légale).
 */
class DocxToHtml
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @var array<string, string> styleId => nom du style en minuscules */
    private array $styles = [];

    /** @var array<string, string> numId:ilvl => format (bullet, decimal…) */
    private array $numbering = [];

    /** @var array<string, string> id de relation => URL */
    private array $links = [];

    private DOMXPath $xpath;

    public static function convert(string $path): string
    {
        return (new self())->run($path);
    }

    private function run(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Fichier Word illisible (.docx attendu).');
        }

        try {
            $document = $zip->getFromName('word/document.xml');
            if ($document === false) {
                throw new RuntimeException('Fichier Word invalide : contenu introuvable.');
            }
            $this->loadStyles($zip->getFromName('word/styles.xml') ?: null);
            $this->loadNumbering($zip->getFromName('word/numbering.xml') ?: null);
            $this->loadLinks($zip->getFromName('word/_rels/document.xml.rels') ?: null);
        } finally {
            $zip->close();
        }

        $dom = $this->load($document);
        $this->xpath = new DOMXPath($dom);
        $this->xpath->registerNamespace('w', self::W);
        $this->xpath->registerNamespace('r', self::R);

        $body = $this->xpath->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            return '';
        }

        $blocks = [];
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }
            if ($node->localName === 'p') {
                $blocks[] = $this->paragraph($node);
            } elseif ($node->localName === 'tbl') {
                foreach ($this->table($node) as $row) {
                    $blocks[] = ['tag' => 'p', 'html' => $row, 'list' => null];
                }
            } elseif ($node->localName === 'sdt') {
                foreach ($this->xpath->query('.//w:p', $node) as $p) {
                    $blocks[] = $this->paragraph($p);
                }
            }
        }

        return $this->render(array_filter($blocks, fn ($b) => $b !== null && trim(strip_tags($b['html'])) !== ''));
    }

    /** Regroupe les éléments de liste consécutifs dans <ul>/<ol>. */
    private function render(array $blocks): string
    {
        $html = '';
        $openList = null;

        foreach ($blocks as $block) {
            if ($block['list'] !== $openList) {
                if ($openList) {
                    $html .= "</{$openList}>";
                }
                if ($block['list']) {
                    $html .= "<{$block['list']}>";
                }
                $openList = $block['list'];
            }
            $tag = $block['list'] ? 'li' : $block['tag'];
            $html .= "<{$tag}>{$block['html']}</{$tag}>";
        }

        if ($openList) {
            $html .= "</{$openList}>";
        }

        return $html;
    }

    private function paragraph(DOMElement $p): ?array
    {
        $style = $this->attr($p, 'w:pPr/w:pStyle');
        $name = $style !== null ? ($this->styles[$style] ?? strtolower($style)) : '';

        $html = $this->inline($p);
        if (trim(strip_tags($html)) === '') {
            return null;
        }

        $tag = match (true) {
            $name === 'title' => 'h2',
            in_array($name, ['heading 1', 'titre 1'], true) => 'h3',
            in_array($name, ['heading 2', 'titre 2'], true) => 'h4',
            str_starts_with($name, 'heading') || str_starts_with($name, 'titre') => 'p',
            default => $this->sizedHeading($p) ?? 'p',
        };

        if ($name === 'subtitle') {
            $html = '<em>' . $html . '</em>';
        }
        // Le titre porte déjà sa propre mise en forme : on retire le gras doublon.
        if ($tag !== 'p') {
            $html = preg_replace('#</?(strong)>#', '', $html);
        }

        $list = null;
        if ($tag === 'p') {
            $numId = $this->attr($p, 'w:pPr/w:numPr/w:numId');
            if ($numId !== null && $numId !== '0') {
                $level = $this->attr($p, 'w:pPr/w:numPr/w:ilvl') ?? '0';
                $list = ($this->numbering["{$numId}:{$level}"] ?? 'bullet') === 'bullet' ? 'ul' : 'ol';
            } elseif (str_contains($name, 'list bullet') || str_contains($name, 'liste à puces')) {
                $list = 'ul';
            } elseif (str_contains($name, 'list number') || str_contains($name, 'liste numéros')) {
                $list = 'ol';
            }
        }

        return ['tag' => $tag, 'html' => $html, 'list' => $list];
    }

    /** Paragraphe sans style mais écrit en gros caractères : traité comme un titre. */
    private function sizedHeading(DOMElement $p): ?string
    {
        $max = 0;
        foreach ($this->xpath->query('.//w:r[w:t]/w:rPr/w:sz', $p) as $sz) {
            $max = max($max, (int) $sz->getAttributeNS(self::W, 'val'));
        }

        // Taille en demi-points : 36 = 18 pt.
        return $max >= 36 ? 'h2' : ($max >= 30 ? 'h3' : null);
    }

    private function inline(DOMElement $parent): string
    {
        $html = '';
        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }
            switch ($node->localName) {
                case 'r':
                    $html .= $this->run_($node);
                    break;
                case 'hyperlink':
                    $inner = $this->inline($node);
                    $href = $this->links[$node->getAttributeNS(self::R, 'id')] ?? null;
                    $html .= $href && trim(strip_tags($inner)) !== ''
                        ? '<a href="' . e($href) . '">' . $inner . '</a>'
                        : $inner;
                    break;
                case 'ins':
                case 'smartTag':
                case 'sdt':
                case 'sdtContent':
                case 'fldSimple':
                    $html .= $this->inline($node);
                    break;
            }
        }

        return $this->mergeTags($html);
    }

    private function run_(DOMElement $r): string
    {
        $text = '';
        foreach ($r->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }
            $text .= match ($node->localName) {
                't' => e($node->textContent),
                'tab' => ' ',
                'br', 'cr' => $node->getAttributeNS(self::W, 'type') === 'page' ? '' : '<br>',
                'noBreakHyphen' => '-',
                default => '',
            };
        }
        if ($text === '') {
            return '';
        }

        if ($this->flag($r, 'w:rPr/w:u') && $this->attr($r, 'w:rPr/w:u') !== 'none') {
            $text = "<u>{$text}</u>";
        }
        if ($this->flag($r, 'w:rPr/w:i')) {
            $text = "<em>{$text}</em>";
        }
        if ($this->flag($r, 'w:rPr/w:b')) {
            $text = "<strong>{$text}</strong>";
        }

        return $text;
    }

    /** Fusionne « <strong>a</strong><strong>b</strong> » produit par des runs voisins. */
    private function mergeTags(string $html): string
    {
        do {
            $before = $html;
            $html = preg_replace('#</(strong|em|u)><\1>#', '', $html);
        } while ($html !== $before);

        return $html;
    }

    /**
     * Tableau → une ligne de texte par rangée. Deux colonnes : « libellé : valeur ».
     *
     * @return array<int, string>
     */
    private function table(DOMElement $tbl): array
    {
        $rows = [];
        foreach ($this->xpath->query('w:tr', $tbl) as $tr) {
            $cells = [];
            foreach ($this->xpath->query('w:tc', $tr) as $tc) {
                $parts = [];
                foreach ($this->xpath->query('.//w:p', $tc) as $p) {
                    $inline = trim($this->inline($p));
                    if (trim(strip_tags($inline)) !== '') {
                        $parts[] = $inline;
                    }
                }
                $cells[] = implode('<br>', $parts);
            }
            $cells = array_values(array_filter($cells, fn ($c) => $c !== ''));
            if ($cells === []) {
                continue;
            }
            if (count($cells) === 2) {
                $label = str_contains($cells[0], '<strong>') ? $cells[0] : '<strong>' . $cells[0] . '</strong>';
                $rows[] = $label . ' : ' . $cells[1];
            } else {
                $rows[] = implode(' — ', $cells);
            }
        }

        return $rows;
    }

    /** Propriété booléenne Word (<w:b/>, <w:b w:val="0"/>…). */
    private function flag(DOMElement $node, string $path): bool
    {
        $el = $this->xpath->query($path, $node)->item(0);
        if (! $el instanceof DOMElement) {
            return false;
        }
        $val = $el->getAttributeNS(self::W, 'val');

        return ! in_array($val, ['0', 'false', 'off'], true);
    }

    private function attr(DOMElement $node, string $path): ?string
    {
        $el = $this->xpath->query($path, $node)->item(0);

        return $el instanceof DOMElement && $el->hasAttributeNS(self::W, 'val')
            ? $el->getAttributeNS(self::W, 'val')
            : null;
    }

    private function load(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Fichier Word invalide : XML illisible.');
        }

        return $dom;
    }

    private function loadStyles(?string $xml): void
    {
        if (! $xml) {
            return;
        }
        $xpath = new DOMXPath($this->load($xml));
        $xpath->registerNamespace('w', self::W);
        foreach ($xpath->query('//w:style') as $style) {
            $name = $xpath->query('w:name', $style)->item(0);
            if ($name instanceof DOMElement) {
                $this->styles[$style->getAttributeNS(self::W, 'styleId')] = strtolower($name->getAttributeNS(self::W, 'val'));
            }
        }
    }

    private function loadNumbering(?string $xml): void
    {
        if (! $xml) {
            return;
        }
        $xpath = new DOMXPath($this->load($xml));
        $xpath->registerNamespace('w', self::W);

        $abstract = [];
        foreach ($xpath->query('//w:abstractNum') as $node) {
            foreach ($xpath->query('w:lvl', $node) as $lvl) {
                $fmt = $xpath->query('w:numFmt', $lvl)->item(0);
                $abstract[$node->getAttributeNS(self::W, 'abstractNumId')][$lvl->getAttributeNS(self::W, 'ilvl')]
                    = $fmt instanceof DOMElement ? $fmt->getAttributeNS(self::W, 'val') : 'bullet';
            }
        }
        foreach ($xpath->query('//w:num') as $num) {
            $ref = $xpath->query('w:abstractNumId', $num)->item(0);
            if ($ref instanceof DOMElement) {
                foreach ($abstract[$ref->getAttributeNS(self::W, 'val')] ?? [] as $level => $fmt) {
                    $this->numbering[$num->getAttributeNS(self::W, 'numId') . ':' . $level] = $fmt;
                }
            }
        }
    }

    private function loadLinks(?string $xml): void
    {
        if (! $xml) {
            return;
        }
        $dom = $this->load($xml);
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            if (str_ends_with($rel->getAttribute('Type'), '/hyperlink')) {
                $target = $rel->getAttribute('Target');
                if (preg_match('#^(https?:|mailto:|tel:)#i', $target)) {
                    $this->links[$rel->getAttribute('Id')] = $target;
                }
            }
        }
    }
}
