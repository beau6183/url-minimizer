<?php

header('Content-Type: text/plain');

$DB_NAME='test_ttly';
// $DB_NAME='ttly';

$COL_NAME='ttly_links';

$mDate = new MongoDate();

$mongo = new Mongo();
$db = $mongo->{$DB_NAME};
$links = $db->{$COL_NAME};

//$db2 = $mongo->test_ttly;
//$links2 = $db2->ttly_links;

    $links->update(array('locators.createDate' => null), 
                    array('$set' => array('locators.$.createDate' => new MongoDate())));
                    
$no_date = $links->find(array('locators.createDate' => null));
while ($no_date->hasNext()) {
    echo "\n";
    $foo = $no_date->getNext();
  // //  $links2->insert($foo);
    print_r($foo);    
}


