<?php

/*
 * Init the todos array in session
 */
function init_todos(){
  if(!is_array($_SESSION["todos"])){
    $_SESSION["todos"] = array();
  }
}

function to_bool_01($b){
  return $b?1:0;
}

/*
 * Cleanup the HTML to avoid XSS
 */
 function sanitize_html($data) {
     include_once('./libs/htmlpurifier/HTMLPurifier.auto.php');

     $config = HTMLPurifier_Config::createDefault();
     $purifier = new HTMLPurifier($config);
     $data = $purifier->purify($data);
     return $data;
 }

?>
