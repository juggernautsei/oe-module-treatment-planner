<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

class PlanSectionParser
{
    public function extract(string $content): array
    {
        $content = preg_replace('/<\s*(?:br\b[^>]*|\/p|\/div|\/li|\/h[1-6])\s*>/i', "\n", $content);
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace(['**', '__'], '', $content);
        $names = [
            'diagnosis' => 'diagnosis(?:\s*/\s*problem statement)?|problem statement|clinical impression',
            'goals' => 'goals|long.term goals|treatment goals',
            'objectives' => 'short.term objectives|objectives|treatment objectives',
            'interventions' => 'interventions(?:\s+and\s+strategies)?|strategies|treatment strategies',
            'timeline' => 'timeline(?:\s+for\s+treatment)?|treatment duration|timeframe',
            'progress' => 'criteria for success|success criteria|progress measurement(?:\s+methods)?|assessment|evaluation'
        ];
        $all = implode('|', $names);
        $pattern = '~^\h*(?:\#{1,6}\h*)?(?:\d+[.)]\h*)?(' . $all . ')(?:\h*\([^\r\n)]*\))?\h*(?::\h*([^\r\n]*))?\h*\r?$~im';
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        $sections = [];
        foreach ($matches[0] as $i => $heading) {
            $start = $heading[1] + strlen($heading[0]);
            $end = $matches[0][$i + 1][1] ?? strlen($content);
            $text = trim(($matches[2][$i][0] ?? '') . "\n" . substr($content, $start, $end - $start));
            foreach ($names as $key => $aliases) {
                if (preg_match('~^(?:' . $aliases . ')$~i', $matches[1][$i][0])) {
                    $sections[$key] = isset($sections[$key]) ? $sections[$key] . "\n" . $text : $text;
                    break;
                }
            }
        }
        return $sections;
    }
}
