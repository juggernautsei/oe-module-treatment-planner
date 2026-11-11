<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

/** A deliberately limited Markdown display renderer. Stored content stays unchanged. */
class PlanContentRenderer
{
    public function render(string $content): string
    {
        $lines = preg_split('/\R/u', $content) ?: [$content];
        $html = '';
        $paragraph = [];
        $lists = [];
        $flush = function () use (&$html, &$paragraph): void {
            if ($paragraph) {
                $html .= '<p>' . implode('<br>', $paragraph) . '</p>';
                $paragraph = [];
            }
        };
        $close = function () use (&$html, &$lists): void {
            while ($lists) { $list = array_pop($lists); $html .= '</li></' . $list['tag'] . '>'; }
        };
        foreach ($lines as $line) {
            if (preg_match('/^(\s*)(?:([-+*])\s+|(\d{1,6})[.)]\s+)(.*)$/u', $line, $item)) {
                $flush();
                $indent = strlen(str_replace("\t", '    ', $item[1]));
                $tag = $item[2] !== '' ? 'ul' : 'ol';
                while ($lists && ($indent < end($lists)['indent'] || ($indent === end($lists)['indent'] && $tag !== end($lists)['tag']))) {
                    $list = array_pop($lists); $html .= '</li></' . $list['tag'] . '>';
                }
                if (!$lists || $indent > end($lists)['indent']) {
                    $html .= '<' . $tag . ($tag === 'ol' ? ' start="' . max(1, (int)$item[3]) . '"' : '') . '><li>';
                    $lists[] = ['tag' => $tag, 'indent' => $indent];
                } else { $html .= '</li><li>'; }
                $html .= $this->inline($item[4]);
                continue;
            }
            // Blank lines inside an indented list must not split it into separate lists.
            if (trim($line) === '') { $flush(); continue; }
            if ($lists && preg_match('/^\s+\S/u', $line) && strlen($line) - strlen(ltrim($line)) > end($lists)['indent']) {
                $html .= '<br>' . $this->inline(trim($line));
                continue;
            }
            $close();
            if (preg_match('/^\s{0,3}(#{1,6})\s+(.+?)\s*#*$/u', $line, $heading)) {
                $flush();
                $level = max(2, min(4, strlen($heading[1]) - 1));
                $html .= '<h' . $level . '>' . $this->inline($heading[2]) . '</h' . $level . '>';
            } elseif (preg_match('/^\s{0,3}(?:-{3,}|\*{3,}|_{3,})\s*$/u', $line)) {
                $flush(); $html .= '<hr>';
            } else { $paragraph[] = $this->inline($line); }
        }
        $flush(); $close();
        return $html;
    }

    private function inline(string $text): string
    {
        // Escape before introducing our fixed markup. Links, images and raw HTML are not enabled.
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/u', '<strong>$1</strong>', $text);
        return preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $text);
    }
}
