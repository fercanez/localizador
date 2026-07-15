<?php
function redirect($loc)
{
  header("Location: {$loc}");
}

function generate_token()
{
  return md5(microtime() . mt_rand());
}

function set_message($msg, $level = "danger")
{
  if (
    $level != "primary" &&
    $level != "success" &&
    $level != "info" &&
    $level != "danger"
  ) {
    $level = "danger";
  }
  if (empty($msg)) {
    unset($_SESSION["message"]);
  } else {
    $_SESSION["message"] = "<h4 class='bg-{$level} text-center'>{$msg}</h4>";
  }
}

function show_message()
{
  if (isset($_SESSION["message"])) {
    echo $_SESSION["message"];
    unset($_SESSION["message"]);
  }
}

function send_email($to, $subject, $body, $from, $reply)
{
  $headers =
    "From: {$from}" .
    "\r\n" .
    "Reply-To: {$reply} " .
    " \r\n " .
    "X-Mailer: PHP/" .
    phpversion();
  if ($_SERVER["SERVER_NAME"] != "localhost") {
    mail($to, $subject, $body, $headers);
    set_message(
      "Se ha enviado un correo a {$to}. Revise su bandeja de entrada y correo no deseado."
    );
    redirect("index.php");
  } else {
    echo "<hr><p>To: {$to}</p><p>Subject: {$subject}</p><p>{$body}</p><p>" .
      $headers .
      "</p><hr>";
  }
}

function logged_in()
{
  if (!empty($_SESSION["user"]["id"])) {
    return true;
  }

  if (!empty($_SESSION["email"])) {
    return true;
  }

  if (!empty($_COOKIE["email"])) {
    $_SESSION["email"] = $_COOKIE["email"];
    return true;
  }

  return false;
}
// Database Functions

function clean_array($array)
{
  foreach ($array as $key => $val) {
    $array[$key] = htmlentities($val);
  }
  return $array;
}

function count_field_val($pdo, $tbl, $fld, $val)
{
  try {
    $sql = "SELECT {$fld} FROM {$tbl} WHERE {$fld} =:value";
    $stmnt = $pdo->prepare($sql);
    $stmnt->execute([":value" => $val]);
    return $stmnt->rowCount();
  } catch (PDOException $e) {
    return $e->getMessage();
  }
}

function return_field_data($pdo, $tbl, $fld, $val)
{
  try {
    $sql = "SELECT * FROM {$tbl} WHERE {$fld} =:value";
    $stmnt = $pdo->prepare($sql);
    $stmnt->execute([":value" => $val]);
    return $stmnt->fetch();
  } catch (PDOException $e) {
    return $e->getMessage();
  }
}

function get_validationcode($user, $pdo)
{
  try {
    $sql = "SELECT validationcode FROM usuarios WHERE email =:email";
    $stmnt = $pdo->prepare($sql);
    $stmnt->execute([":email" => $user]);
    $row = $stmnt->fetch();
    return $row["validationcode"];
  } catch (PDOException $e) {
    return $e->getMessage();
  }
}

function update_login_date($pdo, $user)
{
  try {
    $sql = "UPDATE users SET last_login=current_date WHERE username =:username";
    $stmnt = $pdo->prepare($sql);
    $stmnt->execute([":username" => $user]);
  } catch (PDOException $e) {
    return $e->getMessage();
  }
}

function verify_user_group($pdo, $user, $group)
{
  $user_row = return_field_data($pdo, "users", "username", $user);
  $user_id = $user_row["id"];
  $group_row = return_field_data($pdo, "groups", "name", $group);
  $group_id = $group_row["id"];
  try {
    $sql = "SELECT id FROM user_group_link WHERE user_id={$user_id} AND group_id={$group_id}";
    $stmnt = $pdo->query($sql);
    if ($stmnt->rowCount() > 0) {
      return true;
    } else {
      return false;
    }
  } catch (PDOException $e) {
    echo $e->getMessage();
    return false;
  }
}
?>