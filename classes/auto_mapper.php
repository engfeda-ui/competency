<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace qbank_comp_ext;

use stdClass;

/**
 * Question-to-Competency Auto-Mapping Engine.
 *
 * Implements bilingual (Arabic & English) stem/normalization psychometric
 * token matching to map question text to course competencies.
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_mapper {
    /** @var float Minimum similarity score threshold (matching SANAD platform engine) */
    const MIN_SCORE = 0.20;

    /** @var array English stop words */
    private static $stop_en = [
        'a', 'an', 'the', 'of', 'and', 'or', 'for', 'to', 'in', 'on', 'with', 'by', 'from',
        'is', 'are', 'was', 'were', 'be', 'as', 'at', 'that', 'this', 'these', 'those', 'it',
        'its', 'what', 'which', 'how', 'when', 'explain', 'describe', 'define', 'following',
        'choose', 'select', 'correct', 'statement', 'statements', 'answer', 'answers', 'given',
        'using', 'use', 'used', 'value', 'values', 'shown', 'below', 'above', 'example',
        'figure', 'order', 'system', 'systems', 'device', 'devices', 'equipment', 'question', 'questions'
    ];

    /** @var array Arabic stop words */
    private static $stop_ar = [
        'من', 'في', 'على', 'إلى', 'عن', 'أن', 'إن', 'التي', 'الذي', 'هذا', 'هذه', 'ذلك', 'تلك',
        'أو', 'ثم', 'بين', 'كل', 'بعض', 'عند', 'مع', 'هل', 'ماذا', 'كيف', 'أي', 'يتم', 'تكون',
        'كان', 'يكون', 'ما', 'لا', 'هو', 'هي', 'هم', 'كما', 'بعد', 'قبل', 'حيث', 'إذا', 'قد',
        'أحد', 'عندما', 'أثناء', 'خلال', 'دون', 'غير', 'بينما', 'وهو', 'وهي', 'يلي', 'التالي',
        'التالية', 'صح', 'خطأ', 'اختر', 'حدد', 'اشرح', 'عرف', 'نظام', 'انظمه', 'جهاز', 'اجهزه',
        'معد', 'سؤال', 'اسئله'
    ];

    /**
     * Arabic word normalization.
     *
     * @param string $w
     * @return string
     */
    public static function norm_ar_word(string $w): string {
        // Strip diacritics and tatweel.
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $w);
        // Unify alifs.
        $s = preg_replace('/[أإآ]/u', 'ا', $s);
        // Normalize ending taa marbuta and alif maqsura.
        $s = preg_replace('/ة$/u', 'ه', $s);
        $s = preg_replace('/ى$/u', 'ي', $s);
        // Strip definite article 'ال'.
        $s = preg_replace('/^ال/u', '', $s);
        // Strip leading 'و' if word length > 3.
        if (mb_strlen($s) > 3) {
            $s = preg_replace('/^و/u', '', $s);
        }
        // Strip common plural suffixes (ات, ون, ين).
        $s = preg_replace('/(ات|ون|ين)$/u', '', $s);
        return $s;
    }

    /**
     * English word stemming.
     *
     * @param string $w
     * @return string
     */
    public static function stem_en_word(string $w): string {
        $w = strtolower(trim($w));
        $len = strlen($w);
        if ($len > 5 && preg_match('/(ing|ed)$/', $w)) {
            $stem = preg_replace('/(ing|ed)$/', '', $w);
            if (strlen($stem) >= 4 && preg_match('/[aeiou]/', $stem)) {
                return $stem;
            }
        }
        if ($len > 4) {
            if (str_ends_with($w, 'ies')) {
                return substr($w, 0, -3) . 'y';
            }
            if (preg_match('/(ses|xes|ches|shes|zes)$/', $w)) {
                return substr($w, 0, -2);
            }
            if (str_ends_with($w, 's') && !str_ends_with($w, 'ss')) {
                return substr($w, 0, -1);
            }
        }
        return $w;
    }

    /**
     * Extract token set from arbitrary bilingual text.
     *
     * @param string $text
     * @return array Array of unique token keys: ['ar:word', 'en:word', ...]
     */
    public static function comp_tokens(string $text): array {
        $tokens = [];
        // Add spacing around camelCase.
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text);
        // Split words by punctuation/spaces.
        $words = preg_split('/[^a-z0-9\x{0600}-\x{06FF}]+/u', mb_strtolower($spaced));

        $stop_en_map = array_flip(self::$stop_en);
        $stop_ar_map = array_flip(self::$stop_ar);

        foreach ($words as $raw) {
            if ($raw === '' || is_numeric($raw)) {
                continue;
            }
            if (preg_match('/^[\x{0600}-\x{06FF}]+$/u', $raw)) {
                $p = self::norm_ar_word($raw);
                if (mb_strlen($p) < 2 || isset($stop_ar_map[$p])) {
                    continue;
                }
                $tokens['ar:' . $p] = true;
            } else if (preg_match('/^[a-z0-9]+$/', $raw)) {
                $p = self::stem_en_word($raw);
                if (strlen($p) < 2 || isset($stop_en_map[$p])) {
                    continue;
                }
                $tokens['en:' . $p] = true;
            }
        }
        return array_keys($tokens);
    }

    /**
     * Build weighted keyword map for a competency.
     *
     * @param stdClass $comp Competency object with shortname, idnumber, description.
     * @return array [token => weight]
     */
    public static function comp_keyword_set(stdClass $comp): array {
        $kw = [];
        $add = function($src, $w) use (&$kw) {
            if (empty($src)) {
                return;
            }
            foreach (self::comp_tokens(strip_tags((string)$src)) as $t) {
                $kw[$t] = max($kw[$t] ?? 0, $w);
            }
        };

        // Code / idnumber gets higher weight 2.
        $cleanid = preg_replace('/^comp[-_]/i', '', $comp->idnumber ?? '');
        $add($cleanid, 2);
        $add($comp->shortname ?? '', 1);
        $add($comp->description ?? '', 1);

        return $kw;
    }

    /**
     * Suggest the best competency match for a question given a list of candidate competencies.
     *
     * @param string $questiontext Question title and content text.
     * @param array  $candidates   Array of competency stdClass records.
     * @return stdClass|null Matched competency stdClass with ->score, or null if below threshold.
     */
    public static function suggest_competency(string $questiontext, array $candidates): ?stdClass {
        if (empty($candidates)) {
            return null;
        }

        $qtokens = array_flip(self::comp_tokens(strip_tags($questiontext)));
        if (empty($qtokens)) {
            return null;
        }

        $bestcomp = null;
        $bestscore = 0.0;

        foreach ($candidates as $comp) {
            $kw = self::comp_keyword_set($comp);
            if (empty($kw)) {
                continue;
            }

            $score_sum = 0.0;
            $hits = 0;
            $norm = 0.0;

            foreach ($kw as $tok => $w) {
                $norm += $w;
                if (isset($qtokens[$tok])) {
                    $score_sum += $w;
                    $hits++;
                }
            }

            if ($norm <= 0) {
                continue;
            }

            $score = $score_sum / $norm;
            if ($hits >= 1 && $score >= self::MIN_SCORE && $score > $bestscore) {
                $bestscore = $score;
                $bestcomp = clone $comp;
                $bestcomp->auto_score = round($score, 2);
            }
        }

        return $bestcomp;
    }

    /**
     * Auto-map unmapped questions in a course to its linked competencies.
     *
     * @param int $courseid
     * @return array ['mapped' => int, 'total' => int]
     */
    public static function auto_map_course_questions(int $courseid): array {
        global $DB;

        // Fetch course-linked competencies.
        $competencies = $DB->get_records_sql("
            SELECT c.id, c.shortname, c.idnumber, c.description
              FROM {competency} c
              JOIN {competency_coursecomp} cc ON cc.competencyid = c.id
             WHERE cc.courseid = ?
             ORDER BY c.shortname
        ", [$courseid]);

        if (empty($competencies)) {
            return ['mapped' => 0, 'total' => 0];
        }

        // Fetch course questions that do NOT have a mapping yet in qbank_comp_ext_qmap.
        $context = \context_course::instance($courseid);
        $questions = (array)$DB->get_records_sql("
            SELECT DISTINCT q.id, q.name, q.questiontext
              FROM {question} q
              JOIN {question_categories} qc ON qc.id = q.category
             WHERE qc.contextid = :contextid
               AND q.parent = 0
               AND NOT EXISTS (
                   SELECT 1 FROM {qbank_comp_ext_qmap} m
                    WHERE m.questionid = q.id AND m.courseid = :courseid
               )
        ", ['contextid' => $context->id, 'courseid' => $courseid]);

        // Also include questions actively attempted in quizzes in this course.
        $attemptedquestions = (array)$DB->get_records_sql("
            SELECT DISTINCT q.id, q.name, q.questiontext
              FROM {question} q
              JOIN {question_attempts} qa ON qa.questionid = q.id
              JOIN {question_usages} qu ON qu.id = qa.questionusageid
              JOIN {quiz_attempts} quiza ON quiza.uniqueid = qu.id
              JOIN {quiz} quiz ON quiz.id = quiza.quiz
             WHERE quiz.course = :courseid
               AND q.parent = 0
               AND NOT EXISTS (
                   SELECT 1 FROM {qbank_comp_ext_qmap} m
                    WHERE m.questionid = q.id AND m.courseid = :courseid2
               )
        ", ['courseid' => $courseid, 'courseid2' => $courseid]);

        foreach ($attemptedquestions as $qid => $qq) {
            if (!isset($questions[$qid])) {
                $questions[$qid] = $qq;
            }
        }

        $mapped = 0;
        $total = count($questions);

        foreach ($questions as $q) {
            $fulltext = $q->name . ' ' . $q->questiontext;
            $suggested = self::suggest_competency($fulltext, $competencies);

            if ($suggested) {
                $record = (object)[
                    'questionid'   => (int)$q->id,
                    'courseid'     => $courseid,
                    'competencyid' => (int)$suggested->id,
                    'timecreated'  => time(),
                ];
                try {
                    $DB->insert_record('qbank_comp_ext_qmap', $record);
                    $mapped++;
                } catch (\Throwable $e) {
                    unset($e);
                }
            }
        }

        return ['mapped' => $mapped, 'total' => $total];
    }
}
