<?php

session_start();
header("content-type: application/json");
$id = @$_GET["id"];


if($id != ""){

  // If todo exist in session.
  if (array_key_exists($id, $_SESSION["todos"]))
  {


    $_SESSION["todos"][$id]["termine"] = true;

    echo json_encode(array("success" => true));
  }
    else
    {
      echo json_encode(array("success" => false));
    }

}
else
{
  echo json_encode(array("success" => false));
}
?>
