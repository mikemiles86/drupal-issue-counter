<?php

function get_log_filename($name) {
  global $tag;

  $clean_tag = trim(preg_replace('/[^a-z\d]+/','', strtolower($tag)));
  $clean_name = trim(preg_replace('/[^a-z\d]+/','', strtolower($name)));

  return 'logs/' . $clean_name . '-' . $clean_tag . '.log';
}

function set_log($name, $data) {
  $data = serialize($data);
  file_put_contents(get_log_filename($name), $data);
}

function get_log($name) {
  $filename = get_log_filename($name);
  if (file_exists($filename)) {
    $data = file_get_contents($filename);
    $data = unserialize($data);
  } else {
    $data = array();
  }

  return $data;
}

function add_issues($new_issues) {
  $processed  = get_log('processed');
  $unprocessed= get_log('unprocessed');
  $issues = get_log('issues');

  //filter out existing issues
  $new_issues = array_diff_key($new_issues, $issues, array_flip($processed), array_flip($unprocessed));

  if (sizeof($new_issues) > 0) {

    $unprocessed = array_merge($unprocessed, array_keys($new_issues));
    $issues      = array_merge($issues, $new_issues);

    set_log('unprocessed', $unprocessed);
    set_log('issues', $issues);
  }
}

//TODO: Handle multiple pages.
function fetch_issues() {
  global $tag;

  $issues = FALSE;

  $url = 'https://drupal.org/project/issues/search/rss?order=last_comment_timestamp&sort=desc&issue_tags=' . $tag;

  if ($data = simplexml_load_file($url)){
    $data = (array)$data;

    if (isset($data['channel'])) {
      $issues =  array();

      foreach ($data['channel']->item as $issue) {
        $issue = (array)$issue;
        if (isset($issue['link'])) {
          $issues[$issue['link']] = array(
            'title' => $issue['title'],
            'link'  => $issue['link'],
          );
        }
      }
    }
  }

  if ($issues) {
    add_issues($issues);
  }
}

function find_issue_user($link) {
  global $tag;

  $user = FALSE;
  $issue = new DOMDocument();
  @$issue->loadHTMLFile($link);

  $xpath = new DOMXpath($issue);

  $elements = $xpath->query("//div[contains(concat(' ',normalize-space(@class),' '),' comment ')]");

  if ($elements->length > 0) {
    foreach ($elements as $element) {
      if ($xpath->query(".//a[text()='" . $tag . "']", $element)->length > 0) {
        $username = $xpath->query(".//a[@class='username']",$element)->item(0)->nodeValue;
        $user_id =  $xpath->query(".//a[@class='username']/@href",$element)->item(0)->nodeValue;
        $user_id = explode('/', $user_id);
        $user_id = array_pop($user_id);

        $user = array(
          'name'  => $username,
          'id'   => $user_id,
        );
      }
    }
  }

  return $user;
}

function rank_users() {
  $users = get_log('users');

  $counts= array();

  foreach ($users as $id => $user) {
    $count = sizeof($user['issues']);

    $counts[$count][] = $id;
  }

  krsort($counts);

  $rank = array();

  foreach ($counts as $count => $ids) {
    foreach ($ids as $id) {
      $rank[$id] = $users[$id];
      $rank[$id]['count'] = $count;
    }
  }

  return $rank;
}

function cron() {
  global $batch_size;

  //fetch new issues
  fetch_issues();
  //get unprocessed issue log
  $unprocessed = get_log('unprocessed');
  //get proccessed issue log
  $processed = get_log('processed');
  //get list of issues
  $issues = get_log('issues');
  //get a batch
  $batch = array_slice($unprocessed, 0, $batch_size);

  //get users
  $users = get_log('users');

  //run through the batch
  foreach ($batch as $link) {
    if ($user = find_issue_user($link)) {

      if (!isset($users[$user['id']])) {
        $users[$user['id']] = $user;
      }

      $users[$user['id']]['issues'][] = $link;
      $issues[$link]['user'] = $user;
      //add to processed
      $processed[] = $link;
      //remove from unprocessed
      unset($unprocessed[$link]);
    }
  }

  set_log('users', $users);
  set_log('issues', $issues);
  set_log('processed', $processed);
  set_log('unprocessed', $unprocessed);
}
