<?php

require_once "functions.php";

$tag = 'SprintWeekend2014';
$rank_limit = 5;
$batch_size = 10;


cron();
$user_list  = rank_users();
$listings = array(
  3 => 'Top 3',
  5 => 'Top 5',
  sizeof($user_list) => 'All',
);

$issues     = get_log('issues');
$processed  = get_log('processed');
?>

<h1>Contributors to "<?php echo $tag; ?>" Issues</h1>
<span>last updated: <i><?php echo @date('m/d/y H:i:s'); ?></i></span>
<br />

<?php foreach($listings as $count => $title): ?>
  <h2><?php echo $title; ?> Contributors</h2>
  <ol>
  <?php $sub_list = array_slice($user_list, 0 ,$count); ?>
  <?php foreach($sub_list as $user): ?>
    <li>
      <a href="http://www.drupal.org/user/<?php echo $user['id']; ?>"><?php echo $user['name']; ?></a> (<?php echo $user['count']; ?>)
    </li>
  <?php endforeach; ?>
</ol>
<br />
<?php endforeach; ?>


<h3>Reviewed Issues (<?php echo sizeof($processed); ?> of <?php echo sizeof($issues); ?>)</h3>
<ul>
  <?php foreach($processed as $link): ?>
  <li><a href="<?php echo $link; ?>"><?php echo $issues[$link]['title']; ?></a> (<?php echo $issues[$link]['user']['name']; ?>)</li>
<?php endforeach; ?>
</ul>
<br />


