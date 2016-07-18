<?php
/**
 * Module.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Module class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_module {

    const STATE_TERMS = 'terms';
    const STATE_LOCAL = 'local';
    const STATE_GLOBAL = 'global';
    const STATE_END = 'end';

    protected static $states = [
        self::STATE_TERMS,
        self::STATE_LOCAL,
        self::STATE_GLOBAL,
        self::STATE_END,
    ];

    protected $course;
    protected $cm;
    protected $context;
    protected $mod;

    protected function __construct($course, $cm, $mod = null) {
        global $DB;
        $this->course = $course;
        $this->cm = $cm;
        $this->mod = $DB->get_record('flashcards', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = context_module::instance($cm->id);
    }

    public function delete() {
        global $DB;
        $modid = $this->get_id();
        $DB->execute('DELETE FROM {flashcards_seen} s
                       WHERE s.termid IN (
                            SELECT t.id
                              FROM {flashcards_terms} t
                             WHERE t.modid = ?
                            )', [$modid]);
        $DB->delete_records('flashcards_terms', array('modid' => $modid));
        $DB->delete_records('flashcards_progress', array('modid' => $modid));
        $DB->delete_records('flashcards', array('id' => $modid));
    }

    public function delete_term($termid) {
        global $DB;
        $DB->set_field('flashcards_terms', 'deleted', 1, ['modid' => $this->get_id(), 'id' => $termid]);
    }

    public static function get_all_states() {
        return self::$states;
    }

    public function get_allowed_states() {
        list($state) = $this->get_state();
        if ($state == self::STATE_END) {
            return self::$states;
        }
        return [$state];
    }

    public function get_cm() {
        return $this->cm;
    }

    public function get_cmid() {
        return $this->cm->id;
    }

    public function get_context() {
        return $this->context;
    }

    public function get_course() {
        return $this->course;
    }

    public function get_id() {
        return $this->mod->id;
    }

    public function get_local_terms() {
        list($state, $statedata) = $this->get_state();

        $records = $this->get_terms();
        shuffle($records);
        $statedata = array_slice($records, 0, $this->mod->localtermcount);

        return $statedata;
    }

    public function get_state() {
        global $DB, $USER;

        // Teachers are always considered done.
        if ($this->can_manage()) {
            return [self::STATE_END, null];
        }

        $record = $DB->get_record('flashcards_progress', ['modid' => $this->get_id(), 'userid' => $USER->id]);
        if (!$record) {
            return [self::STATE_TERMS, null];
        }
        return [$record->state, json_decode($record->statedata)];
    }

    public function get_terms($includedeleted = false) {
        global $DB;
        $params = ['modid' => $this->mod->id];
        if (!$includedeleted) {
            $params['deleted'] = 0;
        }
        return $DB->get_records('flashcards_terms', $params, 'id ASC');
    }

    public function get_terms_seen() {
        global $DB, $USER;

        $sql = 'SELECT s.*
                  FROM {flashcards_seen} s
                  JOIN {flashcards_terms} t
                    ON s.termid = t.id
                   AND t.deleted = 0
                 WHERE t.modid = ?
                   AND s.userid = ?';

        return $DB->get_records_sql($sql, [$this->mod->id, $USER->id]);
    }

    public function has_seen_all_terms() {
        global $DB, $USER;

        if (!$this->has_terms()) {
            return false;
        }

        $sql = "SELECT 'x'
                  FROM {flashcards_terms} t
             LEFT JOIN {flashcards_seen} s
                    ON t.id = s.termid
                   AND s.userid = ?
                 WHERE t.deleted = 0
                   AND s.id IS NULL";

        // We've seen it all when there is no null entries.
        return !$DB->record_exists_sql($sql, [$USER->id]);
    }

    public function has_terms() {
        global $DB;
        return $DB->record_exists('flashcards_terms', ['modid' => $this->get_id()]);
    }

    public function resume_progress($currentstate) {
        $this->update_state();
        list($state, $statedata) = $this->get_state();

        if ($state == self::STATE_END) {
            // They can go wherever they want when finished.
            return;

        } else if ($state == $currentstate) {
            // They do not need to be sent elsewhere.
            return;
        }

        switch ($state) {
            case self::STATE_TERMS:
                redirect(new moodle_url('/mod/flashcards/view.php', ['id' => $this->get_cmid()]));
                break;

            case self::STATE_LOCAL:
                redirect(new moodle_url('/mod/flashcards/local.php', ['id' => $this->get_cmid()]));
                break;

            case self::STATE_GLOBAL:
                redirect(new moodle_url('/mod/flashcards/global.php', ['id' => $this->get_cmid()]));
                break;
        }
    }

    protected function set_state($state, $statedata = null) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'modid' => $this->get_id()];
        if ($record = $DB->get_record('flashcards_progress', $params)) {
        } else {
            $record = (object) $params;
        }

        $record->state = $state;
        $record->statedata = json_encode($statedata);
        if (!empty($record->id)) {
            $DB->update_record('flashcards_progress', $record);
        } else {
            $DB->insert_record('flashcards_progress', $record);
        }
    }

    protected function update_state() {
        list($state) = $this->get_state();

        if ($state == self::STATE_END) {
            // Nothing to do.
            return;

        } else if ($state == self::STATE_TERMS) {
            if ($this->has_seen_all_terms()) {
                $this->set_state(self::STATE_LOCAL);
                return;
            }

        } else if ($state == self::STATE_LOCAL) {


        } else if ($state == self::STATE_GLOBAL) {

        }
    }

    // Factories.
    public static function get_by_cmid($cmid) {
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'flashcards');
        return new static($course, $cm);
    }

    public static function get_by_modid($modid) {
        list($course, $cm) = get_course_and_cm_from_instance($modid, 'flashcards');
        return new static($course, $cm);
    }

    // Capabilities.
    public function can_manage() {
        return  has_capability('mod/flashcards:addinstance', $this->context);
    }

    public function require_manage() {
        require_capability('mod/flashcards:addinstance', $this->context);
    }

    public function can_view() {
        return  has_capability('mod/flashcards:view', $this->context);
    }

    public function require_view() {
        self::require_view_in_context($this->context);
    }

    public static function require_view_in_context(context $context) {
        require_capability('mod/flashcards:view', $context);
    }

}
