<?php

$ajax = $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
$layout = $ajax ? '' : '<!DOCTYPE html>
<html>
  <head>
    <script src="http://jqueryjs.googlecode.com/files/jquery-1.3.2.min.js"></script>
    <script src="http://jquery-elastic.googlecode.com/files/jquery.elastic-1.4.js"></script>
    <script src="http://plugins.jquery.com/files/jquery.caret-range-1.0.js.txt"></script>
    <script src="<?= base_url("js/notes.js") ?>"></script>
    <script>$(function(){ $.notesApp("<?= base_url(); ?>"); })</script>
    <link rel="stylesheet" href="css/notes.css"/>
    <title><?= $data["title"] ?></title>
  </head>
  <body>
    <?=h $content_for_layout ?>
  </body>
</html>';

$markdown_template = '<!DOCTYPE html>
<html>
  <head>
    <title><?= $data["title"] ?></title>
  </head>
  <body>
    <?=h $data["note"] ?>
  </body>
</html>';

$search_form_template = '
<form id="search-form">
  <input id="search" name="search" autocomplete="off" value="<?= $_GET["search"] ?>" />
  <input type="submit" value="search" />
</form>';

$search_results_template = '
<ul id="search-results">
<? if($data["results"]) foreach($data["results"] as $note) { ?>
    <li class="<?= ++$i % 2 ? "odd" : "even" ?>">
      <a href="<?= $note->url ?>"><?=h search_context_string($note) ?></a>
    </li>
<? } ?></ul>';

$search_template = $ajax ? $search_results_template : '
<div id="search-container">
  <div id="search-form-container"><?=h template("search_form_template", $data) ?></div>
  <div id="search-results-container"><?=h template("search_results_template", $data) ?></div>
</div>';

$note_template = '
<div id="note">
  <h1>
    <a href="<?= base_url() ?>"><?= base_url() ?></a>
    <a href="<?= $data["url"] ?>"><?= $data["title"] ? urldecode($data["title"]) : "New note" ?></a>
    <a href="<?= $data["url"] ?>.md">&#x02605;</a>
  </h1>
  <form id="note-form" method="post" action="<?= $data["url"] ?>">
    <textarea name="note"><?=h $data["note"] ?></textarea>
    <input type="submit"/>
  </form>
  <script>$.noteLoaded("<?= $data["title"] ?>", "<?= base_url() ?>")</script>
</div>';

$db = new DB();

get('/',                  'search_notes');
get('/notes/(.+)\.md',    'view_note_markdown');
get('/notes/(.+)',        'view_note');

post('/notes/(.+)',       'update_note');

function search_notes() {
  $data = array(
    'title' => 'Search' . ($search ? ": {$search}" : ''),
    'results' => Note::search(rtrim($_GET['search'])));
  echo template('search_template', $data, 'layout');
}

function view_note($title) {
  echo template('note_template', (array)Note::load($title), 'layout');
}

function view_note_markdown($title) {
  require_once '../markdown/markdown.php';
  $note = (array)Note::load($title);
  $note['note'] = Markdown($note['note']);
  echo template('markdown_template', $note);
  exit;
}

function update_note($title) {
  Note::save($title, $_POST['note']);
  if(!$GLOBALS['ajax']) return view_note($title);
  $note = Note::load($title);
  echo $note->note;
}

//view helper function
function search_context_string($note, $title_length = 26, $context_length = 200, $context_pre = 50, $mod_length = 14) {
  $search = $_GET["search"];
  $first_occurrence = strlen($search) ? strpos(strtoupper($note->note_clean), strtoupper($search)) : 0;
  $context = substr($note->note_clean, max(0, $first_occurrence - $context_pre), $context_length);
  $context = preg_replace( '/(' . preg_quote(str_replace("\0", '', $search), '/') . ')/i',
    '<strong>$1</strong>', $context); // <-- doesn't work so good
  $mod_date = str_replace(' ', '&nbsp;', str_pad($note->updated ? substr(pastime($note->updated), 0, $mod_length) : '', $mod_length, ' '));
  $title = str_replace(' ', '&nbsp;', str_pad(substr($note->title, 0, $title_length), $title_length, ' '));
  return $title . '<span class="mod_date"> . ' . $mod_date . ' . </span>' . $context;
}

class DB extends PDO {

  function __construct($filename = '../data/notes.sqlite') {
    if(!file_exists($filename)) {
      @mkdir(dirname($filename), 0755, true);
      sqlite_close(sqlite_open($filename));
    }
    parent::__construct("sqlite:{$filename}");
    $this->exec('CREATE TABLE IF NOT EXISTS notes (
      title TEXT PRIMARY KEY,
      note TEXT,
      updated INT)');
  }

  function escape($in) {
    return count($args = func_get_args()) > 1 ?
      array_map(array($this, 'escape'), $args) :
      str_replace("'", "''", $in);
  }
}

class Note {
  var $note;
  var $note_clean;
  var $title;
  var $updated;
  var $url;
  function __construct($defaults = array()) {
    $defaults = array_map(stripslashes, (array)$defaults);
    foreach($defaults as $key => $value) {
      if(!($this->$key)) $this->$key = $value;
    }
    $this->note = stripslashes($this->note);
    $this->title = stripslashes($this->title);
    if(is_null($this->note_clean))
      $this->note_clean = trim(str_replace("\r", ' ', str_replace("\n", ' ', $this->note)));
    if(is_null($this->url)) $this->url = base_url('notes/') . $this->title;
  }

  static function search($str) {
    // select title matches before content matches
    $title_matches = self::note_query(
      "SELECT * FROM notes WHERE title LIKE '%{$str}%' ORDER BY updated DESC"
    )->fetchAll();
    $note_matches = self::note_query(
      "SELECT * FROM notes WHERE note LIKE '%{$str}%' ORDER BY updated DESC"
    )->fetchAll();
    $notes = array_unique(array_merge($title_matches, $note_matches), SORT_REGULAR);
    if($str) if(!self::db()->query("SELECT * FROM notes WHERE title = '{$str}'")->fetch()) {
      array_unshift($notes, new Note(array('title' => $str,
        'note_clean' => '[create new note entitled ' . $str . ']')));
    }
    return $notes;
  }

  static function load($title) {
    $default = array('title' => $title);
    $sql = "SELECT * FROM notes WHERE title = '{$title}'";
    $result = self::note_query($sql)->fetch();
    return $result ? $result : new Note($default);
  }

  static function save($title, $note) {
    list($title, $note) = self::db()->escape($title, $note);
    $updated = mktime();
    $sql = ($note == '' ?
      "DELETE FROM notes WHERE title = '{$title}'" :
      (self::exists($title) ?
        "UPDATE notes SET note = '{$note}', updated = '{$updated}' WHERE title = '{$title}'" :
        "INSERT INTO notes VALUES ('{$title}', '{$note}', '{$updated}')"));
    self::db()->exec($sql);
  }

  private static function db() {
    return $GLOBALS['db'];
  }
  
  private static function exists($title) {
    return !!self::db()->query("SELECT title FROM notes WHERE title = '{$title}'")->fetch();
  }

  private static function note_query($sql, $default = array()) {
    $stmt = self::db()->query($sql);
    $stmt->setFetchMode(PDO::FETCH_CLASS, Note, $default);
    return $stmt;
  }

}

function route_method($method, $pattern, $callback) {
  return strtolower($method) == strtolower($_SERVER['REQUEST_METHOD']) ? route($pattern, $callback) : 0;
}

function get($pattern, $callback) {
  route_method('get', $pattern, $callback);
}

function post($pattern, $callback) {
  route_method('post', $pattern, $callback);
}

function route($pattern, $callback) {
  $pattern = sprintf('/^%s$/', str_replace('/', '\/', $pattern));
  list($url, $querystring) = explode('?', $_SERVER['REQUEST_URI'], 2);
  $base_url_pattern = sprintf('/^%s/', str_replace('/', '\/', base_url()));
  $url = sprintf('/%s', preg_replace($base_url_pattern, '', $url));
  if(!preg_match($pattern, $url, $matches)) return;
  call_user_func_array($callback, array_map(urldecode, array_splice($matches, 1)));
}

function template($template, $data = null, $layout = null, $content_for_layout = null) {

  // figure out what template is
  $template = is_file($template) ? file_get_contents($template) :
    (isset($GLOBALS[$template]) ? $GLOBALS[$template] :
      $template);

  // deal with empty template (or layout)
  if(!$template) return is_null($content_for_layout) ? (string)$data : $content_for_layout;

  // escape html for regular <?= passages
  $template = preg_replace('/<\?= (.*)[;]?[\s]?\?>/Usm', '<?= htmlspecialchars($1); ?>', $template);

  // allow unescaped html to be echoed with <?=h
  $template = str_replace('<?=h ', '<?= ', $template);

  // swap out short tags if you don't have them
  if(!ini_get('short_open_tag')) {
    $template = str_replace('<? ', '<?php ', $template);
    $template = str_replace('<?=', '<?php echo ', $template);
  }

  // evaluate the template
  ob_start();
  eval('?>' . $template);

  // if there's a layout, render the template into it
  return $layout ?
    template($layout, $data, null, ob_get_clean()) :
    ob_get_clean();
}

function base_url($append = '') {
  if($append && count($args = func_get_args()) > 1) $append = call_user_func_array('sprintf', $args);
  return '/' . ltrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__FILE__)) . '/' . $append, '/');
}

//////////////////// pastime

function pastime($date) {
  $minute_in_secs =                   60;
  $hour_in_secs   = $minute_in_secs * 60;
  $day_in_secs    = $hour_in_secs   * 24;
  $week_in_secs   = $day_in_secs    * 7;
  $month_in_secs  = $day_in_secs    * 30;
  $year_in_secs   = $day_in_secs    * 365;
  $date_diff = mktime() - $date;
  if($date_diff < 0) return "in the future!";
  if($years = floor($date_diff / $year_in_secs)) {
    return plural($years, "last year", "%d years ago");
  } else if($months = floor($date_diff / $month_in_secs)) {
    return plural($months, "%d month") . " ago";
  } else if($weeks = floor($date_diff / $week_in_secs)) {
    return plural($weeks, "last week", "%d weeks ago");
  } else if($days = floor($date_diff / $day_in_secs)) {
    return plural($days, "yesterday", "%d days ago");
  } else if($hours = floor($date_diff / $hour_in_secs)) {
    return plural($hours, "%d hour") . " ago";
  } else if($minutes = floor($date_diff / $minute_in_secs)) {
    return plural($minutes, "%d minute") . " ago";
  } else {
    return "seconds ago";
  }
}

function plural($num, $singular, $plural = null) {
  $plural = is_null($plural) ? "{$singular}s": $plural;
  return sprintf($num == 1 ? $singular : $plural, $num);
}